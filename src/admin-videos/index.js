/**
 * Admin videos page entry point.
 */
import {
	createElement,
	render,
	useState,
	useEffect,
	useCallback,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { fetchVideos, previewPost, saveDraft, parseError } from '../shared/api';
import LanguageModal from '../shared/language-modal';
import PreviewModal from '../shared/preview-modal';
import ErrorNotice from '../shared/error-notice';
import WarningNotice from '../shared/warning-notice';
import './style.scss';

/**
 * Format a date string.
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
 * Admin videos page app component.
 *
 * @return {Element} The videos page UI.
 */
function AdminVideos() {
	const config = window.wttbaConfig || {};
	const ai = config.ai || {};
	const isTextGenerationSupported =
		ai.textGenerationSupported !== undefined
			? ai.textGenerationSupported
			: true;
	const [ videos, setVideos ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ nextPageToken, setNextPageToken ] = useState( '' );
	const [ loadingMore, setLoadingMore ] = useState( false );
	const [ modalVideo, setModalVideo ] = useState( null );
	const [ generating, setGenerating ] = useState( null );
	const [ success, setSuccess ] = useState( null );
	const [ failedVideo, setFailedVideo ] = useState( null );
	const [ preview, setPreview ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ regenerating, setRegenerating ] = useState( false );
	const [ lastGenParams, setLastGenParams ] = useState( null );
	const [ dismissedAiNotice, setDismissedAiNotice ] = useState( false );

	const loadVideos = useCallback( ( pageToken = '' ) => {
		const isLoadMore = pageToken !== '';
		if ( isLoadMore ) {
			setLoadingMore( true );
		} else {
			setLoading( true );
		}

		fetchVideos( pageToken, 12 )
			.then( ( data ) => {
				if ( isLoadMore ) {
					setVideos( ( prev ) => [
						...prev,
						...( data.items || [] ),
					] );
				} else {
					setVideos( data.items || [] );
				}
				setNextPageToken( data.nextPageToken || '' );
				setLoading( false );
				setLoadingMore( false );
			} )
			.catch( ( err ) => {
				setError( parseError( err ) );
				setLoading( false );
				setLoadingMore( false );
			} );
	}, [] );

	useEffect( () => {
		if ( config.isConfigured ) {
			loadVideos();
		} else {
			setLoading( false );
		}
	}, [ config.isConfigured, loadVideos ] );

	const handleGenerate = ( language, persona ) => {
		if ( ! modalVideo ) {
			return;
		}

		if ( ! isTextGenerationSupported ) {
			setError( {
				message:
					ai.unavailableMessage ||
					__(
						'Configure an AI provider before generating posts.',
						'wp-tube-to-blog-ai'
					),
				category: 'configuration',
				configurationUrl: ai.configurationUrl || config.settingsUrl,
				configurationLabel: __(
					'Configure AI Provider',
					'wp-tube-to-blog-ai'
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

	const getGenerateButtonLabel = ( video ) => {
		if ( ! isTextGenerationSupported ) {
			return __( 'AI unavailable', 'wp-tube-to-blog-ai' );
		}

		if ( generating === video.id ) {
			return __( 'Generating…', 'wp-tube-to-blog-ai' );
		}

		return __( 'Generate Post', 'wp-tube-to-blog-ai' );
	};

	if ( ! config.isConfigured ) {
		return createElement(
			'div',
			{ className: 'wttba-videos' },
			createElement(
				'div',
				{ className: 'notice notice-warning inline' },
				createElement(
					'p',
					null,
					__(
						'Please configure your YouTube API settings.',
						'wp-tube-to-blog-ai'
					),
					' ',
					createElement(
						'a',
						{ href: config.settingsUrl },
						__( 'Go to Settings', 'wp-tube-to-blog-ai' )
					)
				)
			)
		);
	}

	if ( loading ) {
		return createElement(
			'div',
			{ className: 'wttba-videos wttba-videos--loading' },
			createElement( 'span', { className: 'spinner is-active' } ),
			__( 'Loading videos…', 'wp-tube-to-blog-ai' )
		);
	}

	return createElement(
		'div',
		{ className: 'wttba-videos' },
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
		! isTextGenerationSupported &&
			! dismissedAiNotice &&
			createElement( ErrorNotice, {
				code: 'wttba_ai_not_supported',
				message:
					ai.unavailableMessage ||
					__(
						'Configure an AI provider before generating posts.',
						'wp-tube-to-blog-ai'
					),
				category: 'configuration',
				configurationUrl: ai.configurationUrl || config.settingsUrl,
				configurationLabel: __(
					'Configure AI Provider',
					'wp-tube-to-blog-ai'
				),
				onDismiss: () => setDismissedAiNotice( true ),
				settingsUrl: config.settingsUrl,
			} ),
		createElement(
			'div',
			{ className: 'wttba-videos__grid' },
			videos.map( ( video ) =>
				createElement(
					'div',
					{ key: video.id, className: 'wttba-videos__card' },
					createElement( 'img', {
						src: video.thumbnail,
						alt: video.title,
						className: 'wttba-videos__thumb',
					} ),
					createElement(
						'div',
						{ className: 'wttba-videos__card-body' },
						createElement(
							'h3',
							{ className: 'wttba-videos__card-title' },
							video.title
						),
						createElement(
							'span',
							{ className: 'wttba-videos__card-date' },
							formatDate( video.publishedAt )
						),
						createElement(
							'button',
							{
								className: 'button button-primary',
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
		nextPageToken &&
			createElement(
				'div',
				{ className: 'wttba-videos__load-more' },
				createElement(
					'button',
					{
						className: 'button button-secondary',
						onClick: () => loadVideos( nextPageToken ),
						disabled: loadingMore,
						type: 'button',
					},
					loadingMore
						? __( 'Loading…', 'wp-tube-to-blog-ai' )
						: __( 'Load More Videos', 'wp-tube-to-blog-ai' )
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

// Mount the app.
const container = document.getElementById( 'wttba-admin-videos' );
if ( container ) {
	render( createElement( AdminVideos ), container );
}
