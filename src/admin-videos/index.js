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
	uploadAudioAttachment,
} from '../shared/api';
import {
	createAudioFileFromBlob,
	formatBytes,
	getRecorderAnnouncement,
	getRecorderStatusText,
	isRecordingTooLarge,
	useAudioRecorder,
} from '../shared/audio-recorder';
import LanguageModal from '../shared/language-modal';
import PreviewModal from '../shared/preview-modal';
import ErrorNotice from '../shared/error-notice';
import useVideoPostGeneration from '../shared/use-video-post-generation';
import VideoGenerationFeedback from '../shared/video-generation-feedback';
import {
	formatDate,
	getYoutubeConfigurationNotice,
} from '../shared/video-post-utils';
import './style.scss';

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
			{ className: statusClass },
			createElement( 'span', {
				className: 'wttba-audio-recorder__dot',
				'aria-hidden': true,
			} ),
			createElement(
				'span',
				null,
				getRecorderStatusText(
					recorder,
					__(
						'Ready to record from your microphone.',
						'creatorstack-ai'
					)
				)
			),
			createElement(
				'span',
				{
					className: 'screen-reader-text',
					role: 'status',
					'aria-live': 'polite',
					'aria-atomic': true,
				},
				getRecorderAnnouncement( recorder )
			)
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
				'aria-label': __( 'Recorded audio preview', 'creatorstack-ai' ),
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
	const recordingTooLarge = isRecordingTooLarge(
		recorder.recordedBlob,
		maxAudioBytes
	);
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
						role: 'error' === notice.type ? 'alert' : 'status',
						'aria-atomic': true,
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
	const [ videos, setVideos ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ nextPageToken, setNextPageToken ] = useState( '' );
	const [ loadingMore, setLoadingMore ] = useState( false );
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
		[ isYoutubeToPostEnabled, setError ]
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
			{
				className: 'wttba-videos wttba-videos--loading',
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
		{ className: 'wttba-videos' },
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
			'div',
			{ className: 'wttba-videos__grid' },
			videos.map( ( video ) =>
				createElement(
					'div',
					{ key: video.id, className: 'wttba-videos__card' },
					createElement( 'img', {
						src: video.thumbnail,
						alt: '',
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
			onCancel: () => setPreview( null ),
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
