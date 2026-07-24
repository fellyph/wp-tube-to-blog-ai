/**
 * CreatorStack 3D Reader.
 *
 * Progressive enhancement for single posts: when the reader navigates to the
 * next/previous post, the current page is snapshotted into a WebGL texture
 * using the experimental HTML-in-Canvas API (layoutsubtree canvas attribute +
 * texElementImage2D) and torn away in 3D, revealing the incoming post
 * underneath. Browsers without the API keep regular full-page navigation.
 *
 * API reference: https://github.com/WICG/html-in-canvas
 * Requires Chromium with chrome://flags/#canvas-draw-element or an
 * origin trial token (Chrome 148–150).
 */
( function () {
	'use strict';

	const DURATION = 1200;
	const GRID = 96;
	const MAX_DPR = 2;
	const FETCH_TIMEOUT = 8000;
	const PAINT_TIMEOUT = 400;

	const reducedMotion = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	);

	const state = {
		data: null,
		busy: false,
		pushed: false,
	};

	const prefetched = new Map();

	/* ------------------------------------------------------------------ *
	 * Feature detection
	 * ------------------------------------------------------------------ */

	function texUploadName( gl ) {
		if ( typeof gl.texElementImage2D === 'function' ) {
			return 'texElementImage2D';
		}
		// Pre-rename builds of the origin trial.
		if ( typeof gl.texElement2D === 'function' ) {
			return 'texElement2D';
		}
		return null;
	}

	function isSupported() {
		if ( reducedMotion.matches ) {
			return false;
		}
		const probe = document.createElement( 'canvas' );
		if ( ! ( 'layoutSubtree' in probe ) ) {
			return false;
		}
		const gl = probe.getContext( 'webgl' );
		if ( ! gl ) {
			return false;
		}
		const ok = !! texUploadName( gl );
		const lose = gl.getExtension( 'WEBGL_lose_context' );
		if ( lose ) {
			lose.loseContext();
		}
		return ok;
	}

	/* ------------------------------------------------------------------ *
	 * Page data + fetching
	 * ------------------------------------------------------------------ */

	function getData( doc ) {
		const el = doc.getElementById( 'wttba-r3d-data' );
		if ( ! el ) {
			return null;
		}
		try {
			return JSON.parse( el.textContent );
		} catch ( err ) {
			return null;
		}
	}

	function fetchPage( url ) {
		if ( ! prefetched.has( url ) ) {
			prefetched.set(
				url,
				window
					.fetch( url, { credentials: 'same-origin' } )
					.then( ( res ) => {
						if ( ! res.ok ) {
							throw new Error( 'HTTP ' + res.status );
						}
						return res.text();
					} )
					.catch( ( err ) => {
						prefetched.delete( url );
						throw err;
					} )
			);
		}
		return prefetched.get( url );
	}

	function withTimeout( promise, ms ) {
		return Promise.race( [
			promise,
			new Promise( ( resolve, reject ) => {
				setTimeout( () => reject( new Error( 'timeout' ) ), ms );
			} ),
		] );
	}

	function sameOrigin( url ) {
		try {
			return (
				new URL( url, window.location.href ).origin ===
				window.location.origin
			);
		} catch ( err ) {
			return false;
		}
	}

	/* ------------------------------------------------------------------ *
	 * DOM snapshots (live DOM -> canvas children)
	 * ------------------------------------------------------------------ */

	/**
	 * Waits for the canvas to record a rendering snapshot of its children.
	 *
	 * @param {HTMLCanvasElement} canvas Overlay canvas.
	 * @return {Promise<boolean>} Resolves true when a paint event fired.
	 */
	function nextPaint( canvas ) {
		return new Promise( ( resolve ) => {
			let settled = false;
			const finish = ( ok ) => {
				if ( ! settled ) {
					settled = true;
					canvas.removeEventListener( 'paint', onPaint );
					resolve( ok );
				}
			};
			const onPaint = () => finish( true );
			canvas.addEventListener( 'paint', onPaint );
			if ( typeof canvas.requestPaint === 'function' ) {
				canvas.requestPaint();
			}
			setTimeout( () => finish( false ), PAINT_TIMEOUT );
		} );
	}

	/**
	 * Clones the current page view into the overlay canvas as a direct child
	 * so it can be uploaded as a WebGL texture.
	 *
	 * @param {HTMLCanvasElement} canvas       Overlay canvas.
	 * @param {number}            scrollOffset Vertical scroll to compensate.
	 * @return {HTMLElement} The snapshot element (canvas child).
	 */
	function makeShot( canvas, scrollOffset ) {
		const shot = document.createElement( 'div' );
		shot.className = 'wttba-r3d-shot';
		shot.style.width = window.innerWidth + 'px';
		shot.style.height = window.innerHeight + 'px';

		const bodyBg = window.getComputedStyle( document.body ).backgroundColor;
		shot.style.background =
			bodyBg && bodyBg !== 'rgba(0, 0, 0, 0)' ? bodyBg : '#fff';

		const inner = document.createElement( 'div' );
		inner.className = 'wttba-r3d-shot-inner';
		inner.style.width = window.innerWidth + 'px';
		inner.style.transform = 'translateY(' + -scrollOffset + 'px)';

		const clone = document.body.cloneNode( true );
		clone
			.querySelectorAll(
				'script,noscript,iframe,canvas,video,audio,embed,object,.wttba-r3d-nav'
			)
			.forEach( ( node ) => node.remove() );

		while ( clone.firstChild ) {
			inner.appendChild( clone.firstChild );
		}

		shot.appendChild( inner );
		canvas.appendChild( shot );
		return shot;
	}

	/* ------------------------------------------------------------------ *
	 * Soft navigation (document swap)
	 * ------------------------------------------------------------------ */

	function syncHeadStyles( newDoc ) {
		const head = document.head;
		const links = new Set(
			Array.from( head.querySelectorAll( 'link[rel="stylesheet"]' ) ).map(
				( l ) => l.href
			)
		);
		newDoc
			.querySelectorAll( 'head link[rel="stylesheet"]' )
			.forEach( ( sheet ) => {
				if ( ! links.has( sheet.href ) ) {
					head.appendChild( document.importNode( sheet ) );
				}
			} );

		const styleIds = new Set(
			Array.from( head.querySelectorAll( 'style[id]' ) ).map(
				( s ) => s.id
			)
		);
		newDoc.querySelectorAll( 'head style[id]' ).forEach( ( style ) => {
			if ( ! styleIds.has( style.id ) ) {
				head.appendChild( document.importNode( style, true ) );
			}
		} );
	}

	/**
	 * Replaces the current document content with the fetched one. Scripts
	 * parsed via DOMParser are inert, so theme JS is not re-executed; links
	 * and styles keep working, which is enough for a reading flow.
	 *
	 * @param {Document}          newDoc  Parsed incoming document.
	 * @param {string}            url     Destination URL.
	 * @param {HTMLCanvasElement} overlay Overlay canvas to preserve.
	 */
	function swapDocument( newDoc, url, overlay ) {
		document.title = newDoc.title || document.title;
		syncHeadStyles( newDoc );

		const selector = state.data && state.data.selector;
		const current = selector ? document.querySelector( selector ) : null;
		const incoming = selector ? newDoc.querySelector( selector ) : null;

		if ( current && incoming ) {
			current.replaceWith( document.importNode( incoming, true ) );
		} else {
			const body = document.body;
			body.className = newDoc.body.className;
			Array.from( body.children ).forEach( ( child ) => {
				if ( child !== overlay ) {
					child.remove();
				}
			} );
			Array.from( newDoc.body.children ).forEach( ( child ) => {
				body.insertBefore(
					document.importNode( child, true ),
					overlay
				);
			} );
		}

		state.data = getData( document );
		window.history.pushState( { wttbaR3d: true }, '', url );
		state.pushed = true;
	}

	/* ------------------------------------------------------------------ *
	 * WebGL
	 * ------------------------------------------------------------------ */

	const GLSL_NOISE = `
	float hash( float n ) {
		return fract( sin( n ) * 43758.5453123 );
	}
	float noise1( float x ) {
		float i = floor( x );
		float f = fract( x );
		float u = f * f * ( 3.0 - 2.0 * f );
		return mix( hash( i ), hash( i + 1.0 ), u );
	}
	float tearX( float y, float seed ) {
		return 0.5 +
			( noise1( y * 6.0 + seed * 43.0 ) - 0.5 ) * 0.16 +
			( noise1( y * 23.0 + seed * 91.0 ) - 0.5 ) * 0.05;
	}
	`;

	const VS_FLAT = `
	attribute vec2 aPos;
	uniform float uScale;
	uniform float uTilt;
	varying vec2 vUv;
	void main() {
		vUv = vec2( aPos.x, 1.0 - aPos.y );
		vec2 p = vec2( aPos.x * 2.0 - 1.0, 1.0 - aPos.y * 2.0 ) * uScale;
		float w = 1.0 + uTilt * ( aPos.y - 0.5 );
		gl_Position = vec4( p, 0.0, w );
	}
	`;

	const FS_FLAT = `
	precision mediump float;
	varying vec2 vUv;
	uniform sampler2D uTex;
	uniform float uBright;
	void main() {
		vec4 c = texture2D( uTex, vUv );
		gl_FragColor = vec4( c.rgb * uBright, 1.0 );
	}
	`;

	const VS_TEAR = `
	attribute vec2 aPos;
	uniform float uProgress;
	uniform float uSeed;
	varying vec2 vUv;
	varying float vSide;
	varying float vLight;
	${ GLSL_NOISE }
	void main() {
		vUv = vec2( aPos.x, 1.0 - aPos.y );
		float tx = tearX( aPos.y, uSeed );
		float side = aPos.x < tx ? -1.0 : 1.0;
		vSide = side;

		// The tear front sweeps from the top of the page downward.
		float front = uProgress * 1.7;
		float local = clamp( ( front - aPos.y ) * 1.5, 0.0, 1.0 );
		local *= smoothstep( 0.0, 0.06, uProgress );

		float dTear = abs( aPos.x - tx );
		vec2 p = vec2( aPos.x * 2.0 - 1.0, 1.0 - aPos.y * 2.0 );

		// Each half separates sideways, lifts near the torn edge and
		// falls away downward, with a paper-like curl.
		float sep = local * local * ( 0.25 + 0.55 * ( 1.0 - dTear ) );
		float fall = local * local *
			( 0.55 + 0.25 * noise1( aPos.x * 4.0 + uSeed * 17.0 ) );
		float lift = local * ( 1.0 - dTear ) * 0.9;
		float curl =
			sin( min( dTear * 9.0, 3.14159 ) - local * 2.2 ) * 0.12 * local;

		p.x += side * sep;
		p.y -= fall;

		float z = lift + curl;
		float w = max( 1.0 - z * 0.35, 0.35 );

		vLight = clamp( 1.0 - local * 0.45 + curl * 1.8, 0.0, 1.3 );
		gl_Position = vec4( p, 0.0, w );
	}
	`;

	const FS_TEAR = `
	precision mediump float;
	varying vec2 vUv;
	varying float vSide;
	varying float vLight;
	uniform sampler2D uTex;
	uniform float uSeed;
	${ GLSL_NOISE }
	void main() {
		// Triangles straddling the tear seam interpolate vSide across
		// the split; discard them so the halves fully separate.
		if ( abs( vSide ) < 0.999 ) {
			discard;
		}
		float y = 1.0 - vUv.y;
		float tx = tearX( y, uSeed );
		float d = ( vUv.x - tx ) * vSide;
		if ( d < 0.0 ) {
			discard;
		}
		float edge = 0.006 + 0.016 * noise1( y * 120.0 + uSeed * 7.0 );
		vec4 c = texture2D( uTex, vUv );
		vec3 col = c.rgb * vLight;
		float fiber = smoothstep( edge, 0.0, d );
		col = mix( col, vec3( 0.97, 0.96, 0.94 ) * vLight, fiber * 0.9 );
		gl_FragColor = vec4( col, 1.0 );
	}
	`;

	function compile( gl, type, source ) {
		const shader = gl.createShader( type );
		gl.shaderSource( shader, source );
		gl.compileShader( shader );
		if ( ! gl.getShaderParameter( shader, gl.COMPILE_STATUS ) ) {
			throw new Error( gl.getShaderInfoLog( shader ) || 'shader' );
		}
		return shader;
	}

	function link( gl, vsSource, fsSource ) {
		const program = gl.createProgram();
		gl.attachShader( program, compile( gl, gl.VERTEX_SHADER, vsSource ) );
		gl.attachShader( program, compile( gl, gl.FRAGMENT_SHADER, fsSource ) );
		gl.linkProgram( program );
		if ( ! gl.getProgramParameter( program, gl.LINK_STATUS ) ) {
			throw new Error( gl.getProgramInfoLog( program ) || 'link' );
		}
		return program;
	}

	function buildGrid( cols, rows ) {
		const verts = new Float32Array( ( cols + 1 ) * ( rows + 1 ) * 2 );
		let v = 0;
		for ( let y = 0; y <= rows; y++ ) {
			for ( let x = 0; x <= cols; x++ ) {
				verts[ v++ ] = x / cols;
				verts[ v++ ] = y / rows;
			}
		}
		const idx = new Uint16Array( cols * rows * 6 );
		let i = 0;
		for ( let y = 0; y < rows; y++ ) {
			for ( let x = 0; x < cols; x++ ) {
				const a = y * ( cols + 1 ) + x;
				const b = a + 1;
				const c = a + cols + 1;
				const d = c + 1;
				idx[ i++ ] = a;
				idx[ i++ ] = c;
				idx[ i++ ] = b;
				idx[ i++ ] = b;
				idx[ i++ ] = c;
				idx[ i++ ] = d;
			}
		}
		return { verts, idx };
	}

	/**
	 * Creates the fixed overlay canvas with a WebGL context able to upload
	 * its DOM children as textures.
	 *
	 * @return {Object} Overlay handle with render helpers.
	 */
	function createOverlay() {
		const canvas = document.createElement( 'canvas' );
		canvas.className = 'wttba-r3d-canvas';
		canvas.setAttribute( 'layoutsubtree', '' );
		canvas.setAttribute( 'aria-hidden', 'true' );

		const dpr = Math.min( window.devicePixelRatio || 1, MAX_DPR );
		canvas.width = Math.round( window.innerWidth * dpr );
		canvas.height = Math.round( window.innerHeight * dpr );
		document.body.appendChild( canvas );

		const gl = canvas.getContext( 'webgl', {
			alpha: true,
			antialias: true,
			premultipliedAlpha: true,
		} );
		if ( ! gl ) {
			canvas.remove();
			throw new Error( 'no webgl' );
		}
		const uploadName = texUploadName( gl );
		if ( ! uploadName ) {
			canvas.remove();
			throw new Error( 'no texElementImage2D' );
		}

		gl.pixelStorei( gl.UNPACK_FLIP_Y_WEBGL, true );
		gl.viewport( 0, 0, canvas.width, canvas.height );
		gl.disable( gl.DEPTH_TEST );
		gl.clearColor( 0, 0, 0, 0 );

		const progFlat = link( gl, VS_FLAT, FS_FLAT );
		const progTear = link( gl, VS_TEAR, FS_TEAR );

		const quad = gl.createBuffer();
		gl.bindBuffer( gl.ARRAY_BUFFER, quad );
		gl.bufferData(
			gl.ARRAY_BUFFER,
			new Float32Array( [ 0, 0, 1, 0, 0, 1, 1, 1 ] ),
			gl.STATIC_DRAW
		);

		const grid = buildGrid( GRID, GRID );
		const gridBuf = gl.createBuffer();
		gl.bindBuffer( gl.ARRAY_BUFFER, gridBuf );
		gl.bufferData( gl.ARRAY_BUFFER, grid.verts, gl.STATIC_DRAW );
		const gridIdx = gl.createBuffer();
		gl.bindBuffer( gl.ELEMENT_ARRAY_BUFFER, gridIdx );
		gl.bufferData( gl.ELEMENT_ARRAY_BUFFER, grid.idx, gl.STATIC_DRAW );

		const textures = [ gl.createTexture(), gl.createTexture() ];
		const seed = ( ( Date.now() % 1000 ) / 1000 ) * 0.9 + 0.05;

		function bindTexture( slot ) {
			gl.activeTexture( gl.TEXTURE0 );
			gl.bindTexture( gl.TEXTURE_2D, textures[ slot ] );
		}

		function upload( slot, element ) {
			bindTexture( slot );
			gl[ uploadName ]( gl.TEXTURE_2D, gl.RGBA, element, {
				width: canvas.width,
				height: canvas.height,
			} );
			gl.texParameteri( gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR );
			gl.texParameteri( gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR );
			gl.texParameteri(
				gl.TEXTURE_2D,
				gl.TEXTURE_WRAP_S,
				gl.CLAMP_TO_EDGE
			);
			gl.texParameteri(
				gl.TEXTURE_2D,
				gl.TEXTURE_WRAP_T,
				gl.CLAMP_TO_EDGE
			);
		}

		function attrib( program, buffer ) {
			const loc = gl.getAttribLocation( program, 'aPos' );
			gl.bindBuffer( gl.ARRAY_BUFFER, buffer );
			gl.enableVertexAttribArray( loc );
			gl.vertexAttribPointer( loc, 2, gl.FLOAT, false, 0, 0 );
		}

		function u( program, name ) {
			return gl.getUniformLocation( program, name );
		}

		function drawFlat( slot, scale, bright, tilt ) {
			gl.useProgram( progFlat );
			attrib( progFlat, quad );
			bindTexture( slot );
			gl.uniform1i( u( progFlat, 'uTex' ), 0 );
			gl.uniform1f( u( progFlat, 'uScale' ), scale );
			gl.uniform1f( u( progFlat, 'uBright' ), bright );
			gl.uniform1f( u( progFlat, 'uTilt' ), tilt );
			gl.drawArrays( gl.TRIANGLE_STRIP, 0, 4 );
		}

		function drawTear( slot, progress ) {
			gl.useProgram( progTear );
			attrib( progTear, gridBuf );
			gl.bindBuffer( gl.ELEMENT_ARRAY_BUFFER, gridIdx );
			bindTexture( slot );
			gl.uniform1i( u( progTear, 'uTex' ), 0 );
			gl.uniform1f( u( progTear, 'uProgress' ), progress );
			gl.uniform1f( u( progTear, 'uSeed' ), seed );
			gl.drawElements(
				gl.TRIANGLES,
				grid.idx.length,
				gl.UNSIGNED_SHORT,
				0
			);
		}

		const easeOutCubic = ( t ) => 1 - Math.pow( 1 - t, 3 );

		return {
			canvas,
			upload,
			cover() {
				gl.clear( gl.COLOR_BUFFER_BIT );
				drawFlat( 0, 1, 1, 0 );
			},
			frame( p ) {
				gl.clear( gl.COLOR_BUFFER_BIT );
				const settle = easeOutCubic( p );
				drawFlat(
					1,
					0.955 + 0.045 * settle,
					0.7 + 0.3 * settle,
					( 1 - settle ) * 0.06
				);
				drawTear( 0, p );
			},
			destroy() {
				canvas.remove();
				const lose = gl.getExtension( 'WEBGL_lose_context' );
				if ( lose ) {
					lose.loseContext();
				}
			},
		};
	}

	/* ------------------------------------------------------------------ *
	 * Transition orchestration
	 * ------------------------------------------------------------------ */

	function easeInOutCubic( t ) {
		return t < 0.5 ? 4 * t * t * t : 1 - Math.pow( -2 * t + 2, 3 ) / 2;
	}

	function animate( overlay ) {
		return new Promise( ( resolve ) => {
			const start = performance.now();
			const tick = ( now ) => {
				const raw = Math.min( ( now - start ) / DURATION, 1 );
				overlay.frame( easeInOutCubic( raw ) );
				if ( raw < 1 ) {
					window.requestAnimationFrame( tick );
				} else {
					resolve();
				}
			};
			window.requestAnimationFrame( tick );
		} );
	}

	async function transitionTo( url ) {
		if ( state.busy ) {
			return;
		}
		state.busy = true;
		document.documentElement.classList.add( 'wttba-r3d-lock' );
		let overlay = null;

		try {
			const pagePromise = fetchPage( url );

			overlay = createOverlay();
			makeShot( overlay.canvas, window.scrollY );
			await nextPaint( overlay.canvas );
			overlay.upload( 0, overlay.canvas.firstElementChild );
			overlay.cover();

			const html = await withTimeout( pagePromise, FETCH_TIMEOUT );
			const newDoc = new window.DOMParser().parseFromString(
				html,
				'text/html'
			);

			swapDocument( newDoc, url, overlay.canvas );
			window.scrollTo( 0, 0 );

			const shotNew = makeShot( overlay.canvas, 0 );
			await nextPaint( overlay.canvas );
			overlay.upload( 1, shotNew );

			await animate( overlay );
			overlay.destroy();
		} catch ( err ) {
			if ( overlay ) {
				overlay.destroy();
			}
			window.location.assign( url );
			return;
		} finally {
			document.documentElement.classList.remove( 'wttba-r3d-lock' );
			state.busy = false;
		}
	}

	/* ------------------------------------------------------------------ *
	 * Wiring
	 * ------------------------------------------------------------------ */

	function findNavLink( target ) {
		if ( ! ( target instanceof window.Element ) ) {
			return null;
		}
		return target.closest(
			'a[rel~="next"], a[rel~="prev"], .wttba-r3d-nav a'
		);
	}

	function init() {
		state.data = getData( document );

		if ( ! isSupported() ) {
			return;
		}

		document.addEventListener( 'click', ( event ) => {
			if (
				event.defaultPrevented ||
				event.button !== 0 ||
				event.metaKey ||
				event.ctrlKey ||
				event.shiftKey ||
				event.altKey
			) {
				return;
			}
			const anchor = findNavLink( event.target );
			if ( ! anchor || ! sameOrigin( anchor.href ) ) {
				return;
			}
			event.preventDefault();
			transitionTo( anchor.href );
		} );

		// Warm the cache as soon as the reader shows intent.
		document.addEventListener(
			'pointerenter',
			( event ) => {
				const anchor = findNavLink( event.target );
				if ( anchor && sameOrigin( anchor.href ) ) {
					fetchPage( anchor.href ).catch( () => {} );
				}
			},
			true
		);

		window.addEventListener( 'popstate', () => {
			if ( state.pushed ) {
				window.location.reload();
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
