/**
 * Dashboard widget entry point.
 */
import { createElement, render, useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { fetchVideos, parseError } from '../shared/api';
import LanguageModal from '../shared/language-modal';
import PreviewModal from '../shared/preview-modal';
import useVideoPostGeneration from '../shared/use-video-post-generation';
import VideoGenerationFeedback from '../shared/video-generation-feedback';
import {
	formatDate,
	getYoutubeConfigurationNotice,
} from '../shared/video-post-utils';
import './style.scss';

/**
 * Dashboard widget app component.
 *
 * @return {Element} The widget UI.
 */
function DashboardWidget() {
	const config = window.wttbaConfig || {};
	const ai = config.ai || {};
	const [ videos, setVideos ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const generation = useVideoPostGeneration( {
		ai,
		settingsUrl: config.settingsUrl,
	} );
	const {
		dismissedAiNotice,
		error,
		generating,
		getGenerateButtonLabel,
		handleGenerate,
		handleRegenerate,
		handleSaveDraft,
		isTextGenerationSupported,
		modalVideo,
		preview,
		regenerating,
		retryFailedVideo,
		saving,
		setDismissedAiNotice,
		setError,
		setModalVideo,
		setPreview,
		setSuccess,
		success,
	} = generation;

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
	}, [ config.isConfigured, setError ] );

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
			{
				className: 'wttba-widget wttba-widget--loading',
				role: 'status',
				'aria-live': 'polite',
			},
			createElement( 'span', {
				className: 'spinner is-active',
				'aria-hidden': true,
			} ),
			__( 'Loading videos…', 'creatorstack-ai' )
		);
	}

	return createElement(
		'div',
		{ className: 'wttba-widget' },
		createElement( VideoGenerationFeedback, {
			ai,
			error,
			success,
			isTextGenerationSupported,
			dismissedAiNotice,
			settingsUrl: config.settingsUrl,
			onDismissError: () => setError( null ),
			onRetry: retryFailedVideo,
			onDismissWarnings: () => setSuccess( { ...success, warnings: [] } ),
			onDismissAiNotice: () => setDismissedAiNotice( true ),
		} ),
		createElement(
			'ul',
			{ className: 'wttba-widget__list', role: 'list' },
			videos.map( ( video ) =>
				createElement(
					'li',
					{ key: video.id, className: 'wttba-widget__item' },
					createElement( 'img', {
						src: video.thumbnail,
						alt: '',
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
								onClick: () => {
									if ( generating !== video.id ) {
										setModalVideo( video );
									}
								},
								disabled: ! isTextGenerationSupported,
								'aria-disabled':
									generating === video.id || undefined,
								'aria-label': sprintf(
									/* translators: 1: action label, 2: video title. */
									__( '%1$s: %2$s', 'creatorstack-ai' ),
									getGenerateButtonLabel( video ),
									video.title
								),
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
			onCancel: () => setPreview( null ),
		} )
	);
}

// Mount the widget.
const container = document.getElementById( 'wttba-dashboard-widget' );
if ( container ) {
	render( createElement( DashboardWidget ), container );
}
