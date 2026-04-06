/**
 * Dashboard widget entry point.
 */
import { createElement, render, useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { fetchVideos, generatePost } from '../shared/api';
import LanguageModal from '../shared/language-modal';
import './style.scss';

/**
 * Format a date string to a locale-friendly format.
 *
 * @param {string} dateStr ISO date string.
 * @return {string} Formatted date.
 */
function formatDate( dateStr ) {
	const date = new Date( dateStr );
	return date.toLocaleDateString( undefined, {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
	} );
}

/**
 * Dashboard widget app component.
 *
 * @return {Element} The widget UI.
 */
function DashboardWidget() {
	const config = window.wttbaConfig || {};
	const [ videos, setVideos ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ modalVideo, setModalVideo ] = useState( null );
	const [ generating, setGenerating ] = useState( null );
	const [ success, setSuccess ] = useState( null );

	useEffect( () => {
		if ( ! config.isConfigured ) {
			setLoading( false );
			return;
		}

		fetchVideos( '', 5 )
			.then( ( data ) => {
				setVideos( data.items || [] );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message || __( 'Failed to load videos.', 'wp-tube-to-blog-ai' ) );
				setLoading( false );
			} );
	}, [] );

	const handleGenerate = ( language, persona ) => {
		if ( ! modalVideo ) return;

		setGenerating( modalVideo.id );
		setModalVideo( null );
		setSuccess( null );
		setError( null );

		generatePost( modalVideo.id, language, persona )
			.then( ( result ) => {
				setGenerating( null );
				setSuccess( result );
			} )
			.catch( ( err ) => {
				setGenerating( null );
				setError( err.message || __( 'Generation failed.', 'wp-tube-to-blog-ai' ) );
			} );
	};

	if ( ! config.isConfigured ) {
		return createElement(
			'div',
			{ className: 'wttba-widget' },
			createElement(
				'p',
				null,
				__( 'Please configure your YouTube API settings.', 'wp-tube-to-blog-ai' )
			),
			createElement(
				'a',
				{ href: config.settingsUrl, className: 'button button-primary' },
				__( 'Go to Settings', 'wp-tube-to-blog-ai' )
			)
		);
	}

	if ( loading ) {
		return createElement(
			'div',
			{ className: 'wttba-widget wttba-widget--loading' },
			createElement( 'span', { className: 'spinner is-active' } ),
			__( 'Loading videos…', 'wp-tube-to-blog-ai' )
		);
	}

	return createElement(
		'div',
		{ className: 'wttba-widget' },
		error &&
			createElement(
				'div',
				{ className: 'notice notice-error inline' },
				createElement( 'p', null, error )
			),
		success &&
			createElement(
				'div',
				{ className: 'notice notice-success inline' },
				createElement(
					'p',
					null,
					__( 'Post generated successfully!', 'wp-tube-to-blog-ai' ),
					' ',
					createElement(
						'a',
						{ href: success.edit_url },
						__( 'Edit Draft', 'wp-tube-to-blog-ai' )
					)
				)
			),
		createElement(
			'ul',
			{ className: 'wttba-widget__list' },
			videos.map( ( video ) =>
				createElement(
					'li',
					{ key: video.id, className: 'wttba-widget__item' },
					createElement( 'img', {
						src: video.thumbnail,
						alt: video.title,
						className: 'wttba-widget__thumb',
					} ),
					createElement(
						'div',
						{ className: 'wttba-widget__info' },
						createElement(
							'strong',
							{ className: 'wttba-widget__title' },
							video.title
						),
						createElement(
							'span',
							{ className: 'wttba-widget__date' },
							formatDate( video.publishedAt )
						),
						createElement(
							'button',
							{
								className: 'button button-small button-primary',
								onClick: () => setModalVideo( video ),
								disabled: generating === video.id,
								type: 'button',
							},
							generating === video.id
								? __( 'Generating…', 'wp-tube-to-blog-ai' )
								: __( 'Generate Post', 'wp-tube-to-blog-ai' )
						)
					)
				)
			)
		),
		createElement(
			'p',
			{ className: 'wttba-widget__footer' },
			createElement(
				'a',
				{ href: config.adminVideosUrl },
				__( 'See More →', 'wp-tube-to-blog-ai' )
			)
		),
		createElement( LanguageModal, {
			isOpen: modalVideo !== null,
			languages: config.languages || {},
			defaultLang: config.defaultLanguage || 'en',
			defaultPersona: config.defaultPersona || '',
			onConfirm: handleGenerate,
			onCancel: () => setModalVideo( null ),
			videoTitle: modalVideo?.title || '',
		} )
	);
}

// Mount the widget.
const container = document.getElementById( 'wttba-dashboard-widget' );
if ( container ) {
	render( createElement( DashboardWidget ), container );
}
