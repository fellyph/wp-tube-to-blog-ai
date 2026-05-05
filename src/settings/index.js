/**
 * Settings page interactions.
 */
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { parseError } from '../shared/api';
import './style.scss';

const config = window.wttbaSettingsConfig || {};
const root = document.getElementById( 'wttba-ai-test' );

if ( root ) {
	const button = root.querySelector( '#wttba-ai-test-button' );
	const spinner = root.querySelector( '#wttba-ai-test-spinner' );
	const result = root.querySelector( '#wttba-ai-test-result' );
	const sample = root.querySelector( '#wttba-ai-test-sample' );

	const setBusy = ( isBusy ) => {
		if ( button ) {
			button.disabled = isBusy;
			button.textContent = isBusy
				? __( 'Testing…', 'wp-tube-to-blog-ai' )
				: __( 'Test AI Connection', 'wp-tube-to-blog-ai' );
		}

		if ( spinner ) {
			spinner.classList.toggle( 'is-active', isBusy );
		}
	};

	const clearElement = ( element ) => {
		if ( element ) {
			element.replaceChildren();
		}
	};

	const renderNotice = ( type, message ) => {
		if ( ! result ) {
			return;
		}

		result.className = `notice notice-${ type } inline`;
		clearElement( result );
		result.appendChild( document.createElement( 'p' ) ).textContent =
			message;
	};

	const appendParagraph = ( element, text ) => {
		const paragraph = document.createElement( 'p' );
		paragraph.textContent = text;
		element.appendChild( paragraph );
	};

	const renderSample = ( response ) => {
		if ( ! sample ) {
			return;
		}

		clearElement( sample );

		if ( response.summary ) {
			appendParagraph(
				sample,
				sprintf(
					/* translators: %s: short AI generated test response. */
					__( 'Test generation: %s', 'wp-tube-to-blog-ai' ),
					response.summary
				)
			);
		}

		const metadata = response.ai_metadata || {};
		const provider = metadata.provider?.name || metadata.provider?.id || '';
		const model = metadata.model?.name || metadata.model?.id || '';

		if ( provider || model ) {
			appendParagraph(
				sample,
				sprintf(
					/* translators: 1: provider name, 2: model name. */
					__( 'Provider: %1$s. Model: %2$s.', 'wp-tube-to-blog-ai' ),
					provider || __( 'unknown', 'wp-tube-to-blog-ai' ),
					model || __( 'unknown', 'wp-tube-to-blog-ai' )
				)
			);
		}

		if ( response.localhost?.message ) {
			appendParagraph( sample, response.localhost.message );
		} else if ( config.localhost?.message ) {
			appendParagraph( sample, config.localhost.message );
		}

		sample.hidden = false;
	};

	const renderError = ( err ) => {
		const parsed = parseError( err );
		renderNotice( 'error', parsed.message );

		if (
			parsed.category !== 'configuration' ||
			! result ||
			! ( parsed.configurationUrl || config.configurationUrl )
		) {
			return;
		}

		const paragraph = document.createElement( 'p' );
		const link = document.createElement( 'a' );
		link.className = 'button button-secondary button-small';
		link.href = parsed.configurationUrl || config.configurationUrl;
		link.textContent =
			parsed.configurationLabel ||
			config.configurationLabel ||
			__( 'Configure AI Provider', 'wp-tube-to-blog-ai' );
		paragraph.appendChild( link );
		result.appendChild( paragraph );
	};

	button?.addEventListener( 'click', async () => {
		setBusy( true );
		if ( sample ) {
			sample.hidden = true;
			clearElement( sample );
		}
		clearElement( result );
		if ( result ) {
			result.className = '';
		}

		try {
			const response = await apiFetch( {
				path: config.testPath || '/wttba/v1/ai/test',
				method: 'POST',
			} );

			renderNotice(
				'success',
				response.message ||
					__(
						'AI provider connection test succeeded.',
						'wp-tube-to-blog-ai'
					)
			);
			renderSample( response );
		} catch ( err ) {
			renderError( err );
		} finally {
			setBusy( false );
		}
	} );
}
