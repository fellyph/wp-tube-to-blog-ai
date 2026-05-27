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
import { __, sprintf } from '@wordpress/i18n';
import {
	createAudioDraft,
	fetchVideos,
	parseError,
	previewPost,
	saveDraft,
	uploadAudioAttachment,
} from '../shared/api';
import {
	createAudioFileFromBlob,
	formatBytes,
	formatDuration,
	useAudioRecorder,
} from '../shared/audio-recorder';
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
 * Render a recording status message.
 *
 * @param {Object} recorder Audio recorder state.
 * @return {string} Status label.
 */
function getRecorderStatusText( recorder ) {
	if ( 'requesting' === recorder.status ) {
		return __( 'Requesting microphone access…', 'creatorstack-ai' );
	}

	if ( 'recording' === recorder.status ) {
		return sprintf(
			/* translators: %s: recording duration. */
			__( 'Recording %s', 'creatorstack-ai' ),
			formatDuration( recorder.duration )
		);
	}

	if ( recorder.hasRecording ) {
		return sprintf(
			/* translators: 1: recording duration, 2: recording file size. */
			__( 'Recording ready: %1$s, %2$s', 'creatorstack-ai' ),
			formatDuration( recorder.duration ),
			formatBytes( recorder.recordedBlob.size )
		);
	}

	if ( recorder.error ) {
		return recorder.error;
	}

	return __( 'Ready to record from your microphone.', 'creatorstack-ai' );
}

/**
 * Recorder controls used on the standalone audio page.
 *
 * @param {Object}  props          Component props.
 * @param {Object}  props.recorder Recorder state/actions.
 * @param {boolean} props.disabled Whether controls are disabled.
 * @return {Element} Recorder UI.
 */
function AudioRecorderCard( { recorder, disabled } ) {
	const statusClass = recorder.isRecording
		? 'wttba-audio-recorder__status wttba-audio-recorder__status--recording'
		: 'wttba-audio-recorder__status';

	return createElement(
		'div',
		{
			className: 'wttba-audio-recorder',
			'data-state': recorder.status,
		},
		createElement(
			'div',
			{ className: statusClass, 'aria-live': 'polite' },
			createElement( 'span', {
				className: 'wttba-audio-recorder__dot',
				'aria-hidden': true,
			} ),
			createElement( 'span', null, getRecorderStatusText( recorder ) )
		),
		createElement(
			'div',
			{ className: 'wttba-audio-recorder__actions' },
			! recorder.isRecording &&
				createElement(
					'button',
					{
						type: 'button',
						className: 'button button-primary',
						onClick: recorder.start,
						disabled: disabled || ! recorder.isSupported,
					},
					recorder.hasRecording
						? __( 'Record Again', 'creatorstack-ai' )
						: __( 'Start Recording', 'creatorstack-ai' )
				),
			recorder.isRecording &&
				createElement(
					'button',
					{
						type: 'button',
						className: 'button button-primary',
						onClick: recorder.stop,
						disabled,
					},
					__( 'Stop Recording', 'creatorstack-ai' )
				),
			recorder.hasRecording &&
				! recorder.isRecording &&
				createElement(
					'button',
					{
						type: 'button',
						className: 'button button-secondary',
						onClick: recorder.reset,
						disabled,
					},
					__( 'Discard Recording', 'creatorstack-ai' )
				)
		),
		recorder.recordedUrl &&
			createElement( 'audio', {
				className: 'wttba-audio-recorder__preview',
				controls: true,
				src: recorder.recordedUrl,
			} )
	);
}

/**
 * Standalone Audio to Post app.
 *
 * @return {Element} Audio recording and draft generation UI.
 */
function AudioToPost() {
	const config = window.wttbaConfig || {};
	const ai = config.ai || {};
	const features = config.features || {};
	const languages = config.languages || {};
	const isAudioToPostEnabled = features.audioToPost !== false;
	const canGenerateFromAudio =
		isAudioToPostEnabled && !! ai.audioInputSupported;
	const [ language, setLanguage ] = useState(
		config.defaultLanguage || 'en'
	);
	const [ persona, setPersona ] = useState( config.defaultPersona || '' );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ success, setSuccess ] = useState( null );
	const [ dismissedAiNotice, setDismissedAiNotice ] = useState( false );
	const recorder = useAudioRecorder( {
		onRecorded: () => {
			setNotice( null );
			setSuccess( null );
		},
	} );
	const maxAudioBytes = Number( config.maxAudioBytes || 0 );
	const recordingTooLarge =
		!! maxAudioBytes &&
		!! recorder.recordedBlob &&
		recorder.recordedBlob.size > maxAudioBytes;
	const isDisabled = busy || recorder.isRecording || ! canGenerateFromAudio;

	const handleCreateDraft = async () => {
		if ( ! canGenerateFromAudio ) {
			setNotice( {
				type: 'error',
				message:
					ai.unavailableMessage ||
					__(
						'Configure an AI provider with audio input support before generating a draft.',
						'creatorstack-ai'
					),
				configurationUrl: ai.configurationUrl || config.settingsUrl,
			} );
			return;
		}

		if ( ! recorder.recordedBlob ) {
			setNotice( {
				type: 'error',
				message: __(
					'Record audio before creating a draft.',
					'creatorstack-ai'
				),
			} );
			return;
		}

		if ( recordingTooLarge ) {
			setNotice( {
				type: 'error',
				message: sprintf(
					/* translators: %s: maximum upload size. */
					__(
						'The recording is too large. The maximum size is %s.',
						'creatorstack-ai'
					),
					formatBytes( maxAudioBytes )
				),
			} );
			return;
		}

		setBusy( true );
		setNotice( null );
		setSuccess( null );

		try {
			const audioFile = createAudioFileFromBlob(
				recorder.recordedBlob,
				'wttba-audio-to-post'
			);
			const attachment = await uploadAudioAttachment(
				audioFile,
				__( 'Audio to Post recording', 'creatorstack-ai' )
			);
			const draft = await createAudioDraft(
				attachment.id,
				language,
				persona
			);

			setSuccess( draft );
			setNotice( {
				type: 'success',
				message: __(
					'Draft created from your recording.',
					'creatorstack-ai'
				),
			} );
		} catch ( err ) {
			const parsed = parseError( err );
			setNotice( {
				type: 'error',
				message: parsed.message,
				configurationUrl: parsed.configurationUrl,
				configurationLabel: parsed.configurationLabel,
			} );
		} finally {
			setBusy( false );
		}
	};

	if ( ! isAudioToPostEnabled ) {
		return createElement(
			'div',
			{ className: 'wttba-audio-to-post' },
			createElement(
				'div',
				{ className: 'notice notice-warning inline' },
				createElement(
					'p',
					null,
					__(
						'Audio to Post is disabled in CreatorStack AI settings.',
						'creatorstack-ai'
					),
					' ',
					createElement(
						'a',
						{ href: config.settingsUrl },
						__( 'Update settings', 'creatorstack-ai' )
					)
				)
			)
		);
	}

	return createElement(
		'div',
		{ className: 'wttba-audio-to-post' },
		createElement(
			'section',
			{ className: 'wttba-audio-to-post__panel' },
			createElement(
				'div',
				{ className: 'wttba-audio-to-post__intro' },
				createElement(
					'h2',
					null,
					__( 'Record audio and create a draft', 'creatorstack-ai' )
				),
				createElement(
					'p',
					null,
					__(
						'Capture a voice note, interview, or spoken outline. The recording is saved to the Media Library and transformed into a draft post.',
						'creatorstack-ai'
					)
				)
			),
			notice &&
				createElement(
					'div',
					{
						className: `notice notice-${ notice.type } inline`,
					},
					createElement(
						'p',
						null,
						notice.message,
						notice.configurationUrl && ' ',
						notice.configurationUrl &&
							createElement(
								'a',
								{
									href: notice.configurationUrl,
									className:
										'button button-secondary button-small wttba-audio-to-post__notice-action',
								},
								notice.configurationLabel ||
									__(
										'Configure AI Provider',
										'creatorstack-ai'
									)
							)
					)
				),
			! canGenerateFromAudio &&
				! dismissedAiNotice &&
				createElement( ErrorNotice, {
					code: 'wttba_audio_input_not_supported',
					message:
						ai.unavailableMessage ||
						__(
							'Configure an AI provider with audio input support before generating drafts from recordings.',
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
			createElement( AudioRecorderCard, {
				recorder,
				disabled: busy || ! canGenerateFromAudio,
			} ),
			recordingTooLarge &&
				createElement(
					'p',
					{ className: 'description wttba-audio-to-post__limit' },
					sprintf(
						/* translators: %s: maximum upload size. */
						__(
							'This recording is larger than the %s upload limit.',
							'creatorstack-ai'
						),
						formatBytes( maxAudioBytes )
					)
				),
			createElement(
				'div',
				{ className: 'wttba-audio-to-post__settings' },
				createElement(
					'label',
					{ htmlFor: 'wttba-audio-to-post-language' },
					__( 'Output language', 'creatorstack-ai' )
				),
				createElement(
					'select',
					{
						id: 'wttba-audio-to-post-language',
						value: language,
						onChange: ( event ) =>
							setLanguage( event.target.value ),
						disabled: isDisabled,
					},
					Object.entries( languages ).map( ( [ value, label ] ) =>
						createElement( 'option', { key: value, value }, label )
					)
				),
				createElement(
					'label',
					{ htmlFor: 'wttba-audio-to-post-persona' },
					__( 'Writing persona', 'creatorstack-ai' )
				),
				createElement( 'textarea', {
					id: 'wttba-audio-to-post-persona',
					value: persona,
					onChange: ( event ) => setPersona( event.target.value ),
					rows: 5,
					disabled: isDisabled,
				} )
			),
			createElement(
				'div',
				{ className: 'wttba-audio-to-post__actions' },
				createElement(
					'button',
					{
						type: 'button',
						className: 'button button-primary',
						onClick: handleCreateDraft,
						disabled:
							isDisabled ||
							! recorder.hasRecording ||
							recordingTooLarge,
					},
					busy
						? __( 'Creating draft…', 'creatorstack-ai' )
						: __( 'Create Draft From Recording', 'creatorstack-ai' )
				),
				createElement(
					'a',
					{
						className: 'button button-secondary',
						href: config.mediaLibraryUrl,
					},
					__( 'Media Library', 'creatorstack-ai' )
				),
				createElement(
					'a',
					{
						className: 'button button-secondary',
						href: config.newPostUrl,
					},
					__( 'Open Blank Draft', 'creatorstack-ai' )
				)
			),
			success &&
				createElement(
					'p',
					{ className: 'wttba-audio-to-post__success' },
					createElement(
						'a',
						{ href: success.edit_url },
						__( 'Edit generated draft', 'creatorstack-ai' )
					)
				)
		)
	);
}

/**
 * Admin videos page app component.
 *
 * @return {Element} The videos page UI.
 */
function AdminVideos() {
	const config = window.wttbaConfig || {};
	const ai = config.ai || {};
	const features = config.features || {};
	const isYoutubeToPostEnabled = features.youtubeToPost !== false;
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

	const loadVideos = useCallback(
		( pageToken = '' ) => {
			if ( ! isYoutubeToPostEnabled ) {
				return;
			}

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
		},
		[ isYoutubeToPostEnabled ]
	);

	useEffect( () => {
		if ( ! isYoutubeToPostEnabled ) {
			setLoading( false );
		} else if ( config.isConfigured ) {
			loadVideos();
		} else {
			setLoading( false );
		}
	}, [ config.isConfigured, isYoutubeToPostEnabled, loadVideos ] );

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

	if ( ! isYoutubeToPostEnabled ) {
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
						'YouTube to Post is disabled in CreatorStack AI settings.',
						'creatorstack-ai'
					),
					' ',
					createElement(
						'a',
						{ href: config.settingsUrl },
						__( 'Update settings', 'creatorstack-ai' )
					)
				)
			)
		);
	}

	if ( ! config.isConfigured ) {
		const youtubeNotice = getYoutubeConfigurationNotice( config );

		return createElement(
			'div',
			{ className: 'wttba-videos' },
			createElement(
				'div',
				{ className: 'notice notice-warning inline' },
				createElement(
					'p',
					null,
					youtubeNotice.message,
					' ',
					createElement(
						'a',
						{ href: youtubeNotice.url },
						youtubeNotice.label
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
			__( 'Loading videos…', 'creatorstack-ai' )
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
						? __( 'Loading…', 'creatorstack-ai' )
						: __( 'Load More Videos', 'creatorstack-ai' )
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

const audioContainer = document.getElementById( 'wttba-audio-to-post' );
if ( audioContainer ) {
	render( createElement( AudioToPost ), audioContainer );
}
