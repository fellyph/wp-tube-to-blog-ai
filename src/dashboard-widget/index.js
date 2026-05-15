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
 * Get YouTube configuration notice details.
 *
 * @param {Object} config Localized app config.
 * @return {{ message: string, url: string, label: string }} Notice details.
 */
function getYoutubeConfigurationNotice( config ) {
	const youtube = config.youtube || {};
	const missingApiKey = youtube.apiKeyConfigured === false;

	return {
		message: missingApiKey
			? __(
					'Configure the YouTube connector API key.',
					'creatorstack-ai'
			  )
			: __(
					'Configure your YouTube channel settings.',
					'creatorstack-ai'
			  ),
		url: youtube.configurationUrl || config.settingsUrl,
		label:
			youtube.configurationLabel ||
			__( 'Go to Settings', 'creatorstack-ai' ),
	};
}

/**
 * Dashboard widget app component.
 *
 * @return {Element} The widget UI.
 */
function DashboardWidget() {
	const config = window.wttbaConfig || {};
	const ai = config.ai || {};
	const isTextGenerationSupported =
		ai.textGenerationSupported !== undefined
			? ai.textGenerationSupported
			: true;
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
	const [ dismissedAiNotice, setDismissedAiNotice ] = useState( false );

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
	}, [ config.isConfigured ] );

	const handleGenerate = ( language, persona, manualTranscript = '' ) => {
		if ( ! modalVideo ) {
			return;
		}

		if ( ! isTextGenerationSupported ) {
			setError( {
				message:
					ai.unavailableMessage ||
					__(
						'Configure an AI provider before generating posts.',
						'creatorstack-ai'
					),
				category: 'configuration',
				configurationUrl: ai.configurationUrl || config.settingsUrl,
				configurationLabel: __(
					'Configure AI Provider',
					'creatorstack-ai'
				),
			} );
			setModalVideo( null );
			return;
		}

		const videoToGenerate = modalVideo;
		setGenerating( videoToGenerate.id );
		setModalVideo( null );
		setSuccess( null );
		setError( null );
		setFailedVideo( null );
		setLastGenParams( {
			videoId: videoToGenerate.id,
			language,
			persona,
			manualTranscript,
		} );

		previewPost( videoToGenerate.id, language, persona, manualTranscript )
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

		saveDraft(
			preview.video_id,
			preview.title,
			preview.content,
			preview.ai_metadata || {}
		)
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
			lastGenParams.persona,
			lastGenParams.manualTranscript || ''
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

	const getGenerateButtonLabel = ( video ) => {
		if ( ! isTextGenerationSupported ) {
			return __( 'AI unavailable', 'creatorstack-ai' );
		}

		if ( generating === video.id ) {
			return __( 'Generating…', 'creatorstack-ai' );
		}

		return __( 'Generate Post', 'creatorstack-ai' );
	};

	if ( ! config.isConfigured ) {
		const youtubeNotice = getYoutubeConfigurationNotice( config );

		return createElement(
			'div',
			{ className: 'wttba-widget' },
			createElement( 'p', null, youtubeNotice.message ),
			createElement(
				'a',
				{
					href: youtubeNotice.url,
					className: 'button button-primary',
				},
				youtubeNotice.label
			)
		);
	}

	if ( loading ) {
		return createElement(
			'div',
			{ className: 'wttba-widget wttba-widget--loading' },
			createElement( 'span', { className: 'spinner is-active' } ),
			__( 'Loading videos…', 'creatorstack-ai' )
		);
	}

	return createElement(
		'div',
		{ className: 'wttba-widget' },
		error &&
			createElement( ErrorNotice, {
				code: error.code,
				message: error.message,
				category: error.category,
				configurationUrl: error.configurationUrl,
				configurationLabel: error.configurationLabel,
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
					__( 'Post generated successfully!', 'creatorstack-ai' ),
					' ',
					createElement(
						'a',
						{ href: success.edit_url },
						__( 'Edit Draft', 'creatorstack-ai' )
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
		! isTextGenerationSupported &&
			! dismissedAiNotice &&
			createElement( ErrorNotice, {
				code: 'wttba_ai_not_supported',
				message:
					ai.unavailableMessage ||
					__(
						'Configure an AI provider before generating posts.',
						'creatorstack-ai'
					),
				category: 'configuration',
				configurationUrl: ai.configurationUrl || config.settingsUrl,
				configurationLabel: __(
					'Configure AI Provider',
					'creatorstack-ai'
				),
				onDismiss: () => setDismissedAiNotice( true ),
				settingsUrl: config.settingsUrl,
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
								className:
									'button button-small button-primary wttba-widget__generate',
								onClick: () => setModalVideo( video ),
								disabled:
									generating === video.id ||
									! isTextGenerationSupported,
								type: 'button',
							},
							getGenerateButtonLabel( video )
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
				__( 'See More →', 'creatorstack-ai' )
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
