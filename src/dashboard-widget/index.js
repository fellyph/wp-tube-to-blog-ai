/**
 * Dashboard widget entry point.
 */
import { createElement, render, useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { fetchVideos, previewPost, saveDraft, parseError } from '../shared/api';
import LanguageModal from '../shared/language-modal';
import PreviewModal from '../shared/preview-modal';
import ErrorNotice from '../shared/error-notice';
import WarningNotice from '../shared/warning-notice';
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
	const [ failedVideo, setFailedVideo ] = useState( null );
	const [ preview, setPreview ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ regenerating, setRegenerating ] = useState( false );
	const [ lastGenParams, setLastGenParams ] = useState( null );

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
				setError( parseError( err ) );
				setLoading( false );
			} );
	}, [] );

	const handleGenerate = ( language, persona ) => {
		if ( ! modalVideo ) {
			return;
		}

		const videoToGenerate = modalVideo;
		setGenerating( videoToGenerate.id );
		setModalVideo( null );
		setSuccess( null );
		setError( null );
		setFailedVideo( null );
		setLastGenParams( { videoId: videoToGenerate.id, language, persona } );

		previewPost( videoToGenerate.id, language, persona )
			.then( ( result ) => {
				setGenerating( null );
				setPreview( result );
			} )
			.catch( ( err ) => {
				setGenerating( null );
				setFailedVideo( videoToGenerate );
				setError( parseError( err ) );
			} );
	};

	const handleSaveDraft = () => {
		if ( ! preview ) {
			return;
		}

		setSaving( true );

		saveDraft( preview.video_id, preview.title, preview.content )
			.then( ( result ) => {
				setSaving( false );
				setPreview( null );
				setSuccess( result );
			} )
			.catch( ( err ) => {
				setSaving( false );
				setPreview( null );
				setError( parseError( err ) );
			} );
	};

	const handleRegenerate = () => {
		if ( ! lastGenParams ) {
			return;
		}

		setRegenerating( true );

		previewPost(
			lastGenParams.videoId,
			lastGenParams.language,
			lastGenParams.persona
		)
			.then( ( result ) => {
				setRegenerating( false );
				setPreview( result );
			} )
			.catch( ( err ) => {
				setRegenerating( false );
				setPreview( null );
				setError( parseError( err ) );
			} );
	};

	const handleCancelPreview = () => {
		setPreview( null );
	};

	if ( ! config.isConfigured ) {
		return createElement(
			'div',
			{ className: 'wttba-widget' },
			createElement(
				'p',
				null,
				__(
					'Please configure your YouTube API settings.',
					'wp-tube-to-blog-ai'
				)
			),
			createElement(
				'a',
				{
					href: config.settingsUrl,
					className: 'button button-primary',
				},
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
			createElement( ErrorNotice, {
				message: error.message,
				category: error.category,
				onDismiss: () => setError( null ),
				onRetry: failedVideo
					? () => {
							setError( null );
							setModalVideo( failedVideo );
							setFailedVideo( null );
					  }
					: null,
				settingsUrl: config.settingsUrl,
			} ),
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
		success &&
			success.warnings &&
			success.warnings.length > 0 &&
			createElement( WarningNotice, {
				messages: success.warnings,
				onDismiss: () => setSuccess( { ...success, warnings: [] } ),
			} ),
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
		} ),
		createElement( PreviewModal, {
			isOpen: preview !== null,
			title: preview?.title || '',
			content: preview?.content || '',
			isRegenerating: regenerating,
			isSaving: saving,
			onSaveAsDraft: handleSaveDraft,
			onRegenerate: handleRegenerate,
			onCancel: handleCancelPreview,
		} )
	);
}

// Mount the widget.
const container = document.getElementById( 'wttba-dashboard-widget' );
if ( container ) {
	render( createElement( DashboardWidget ), container );
}
