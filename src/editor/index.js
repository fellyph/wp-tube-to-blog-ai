/**
 * Block editor CreatorStack AI panel.
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import {
	Button,
	ExternalLink,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import {
	MediaUpload,
	MediaUploadCheck,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';
import { createElement, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	generatePostAudio,
	parseError,
	previewAudioPost,
	uploadAudioAttachment,
} from '../shared/api';
import {
	createAudioFileFromBlob,
	formatBytes,
	formatDuration,
	useAudioRecorder,
} from '../shared/audio-recorder';
import './style.scss';

const GENERATED_AUDIO_CLASS = 'wttba-generated-audio';

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

	return __( 'Record audio from your microphone.', 'creatorstack-ai' );
}

/**
 * Get a readable attachment label from media responses.
 *
 * @param {Object} media Media object.
 * @return {string} Attachment label.
 */
function getAudioAttachmentLabel( media ) {
	if ( media?.filename ) {
		return media.filename;
	}

	if ( 'string' === typeof media?.title ) {
		return media.title;
	}

	if ( media?.title?.raw ) {
		return media.title.raw;
	}

	if ( media?.title?.rendered ) {
		return media.title.rendered.replace( /<[^>]+>/g, '' );
	}

	return __( 'Recorded audio', 'creatorstack-ai' );
}

/**
 * Compact notice for the narrow editor sidebar.
 *
 * @param {Object}   props           Component props.
 * @param {Object}   props.notice    Notice data.
 * @param {Function} props.onDismiss Dismiss handler.
 * @return {Element} Notice element.
 */
function CompactPanelNotice( { notice, onDismiss } ) {
	const status = notice.status || 'info';

	return createElement(
		'div',
		{
			className: `wttba-editor-panel__notice is-${ status }`,
			role: 'error' === status ? 'alert' : 'status',
			'aria-live': 'error' === status ? 'assertive' : 'polite',
		},
		createElement(
			'div',
			{ className: 'wttba-editor-panel__notice-content' },
			createElement(
				'p',
				{ className: 'wttba-editor-panel__notice-message' },
				notice.message
			),
			notice.configurationUrl &&
				createElement(
					ExternalLink,
					{
						href: notice.configurationUrl,
						className: 'wttba-editor-panel__notice-link',
					},
					notice.configurationLabel ||
						__( 'Configure AI Provider', 'creatorstack-ai' )
				)
		),
		createElement(
			'button',
			{
				type: 'button',
				className: 'wttba-editor-panel__notice-dismiss',
				onClick: onDismiss,
				'aria-label': __( 'Dismiss notice', 'creatorstack-ai' ),
			},
			createElement( 'span', { 'aria-hidden': true }, '×' )
		)
	);
}

/**
 * CreatorStack AI editor panel.
 *
 * @return {Element|null} Panel element.
 */
function ContentSuitePanel() {
	const config = window.wttbaEditorConfig || {};
	const ai = config.ai || {};
	const features = config.features || {};
	const languages = config.languages || {};
	const isAudioToPostEnabled = features.audioToPost !== false;
	const isPostToAudioEnabled = features.postToAudio === true;
	const [ selectedAudio, setSelectedAudio ] = useState( null );
	const [ language, setLanguage ] = useState(
		config.defaultLanguage || 'en'
	);
	const [ persona, setPersona ] = useState( config.defaultPersona || '' );
	const [ voice, setVoice ] = useState( '' );
	const [ audioToPostBusy, setAudioToPostBusy ] = useState( false );
	const [ postToAudioBusy, setPostToAudioBusy ] = useState( false );
	const [ panelNotice, setPanelNotice ] = useState( null );
	const recorder = useAudioRecorder( {
		onRecorded: () => {
			setSelectedAudio( null );
			setPanelNotice( null );
		},
	} );

	const { editPost, savePost } = useDispatch( 'core/editor' );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( 'core/notices' );
	const { insertBlocks, removeBlocks } = useDispatch( blockEditorStore );

	const post = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			id: editor.getCurrentPostId(),
			type: editor.getCurrentPostType(),
			isSaving: editor.isSavingPost(),
			isDirty: editor.isEditedPostDirty(),
		};
	}, [] );

	const blocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks(),
		[]
	);

	if (
		'post' !== post.type ||
		( ! isAudioToPostEnabled && ! isPostToAudioEnabled )
	) {
		return null;
	}

	const canGenerateFromAudio =
		isAudioToPostEnabled && !! ai.audioInputSupported;
	const canGeneratePostAudio =
		isPostToAudioEnabled && !! ai.textToSpeechSupported;
	const isBusy = audioToPostBusy || postToAudioBusy || post.isSaving;
	const maxAudioBytes = Number( config.maxAudioBytes || 0 );
	const recordingTooLarge =
		!! maxAudioBytes &&
		!! recorder.recordedBlob &&
		recorder.recordedBlob.size > maxAudioBytes;
	const hasAudioSource = !! selectedAudio?.id || recorder.hasRecording;

	const showError = ( err ) => {
		const parsed = parseError( err );
		setPanelNotice( {
			status: 'error',
			message: parsed.message,
			configurationUrl: parsed.configurationUrl,
			configurationLabel: parsed.configurationLabel,
		} );
		createErrorNotice( parsed.message, { type: 'snackbar' } );
	};

	const handleAudioToPost = async () => {
		if ( ! isAudioToPostEnabled ) {
			return;
		}

		if ( ! hasAudioSource || ! post.id ) {
			setPanelNotice( {
				status: 'error',
				message: __(
					'Select or record audio first.',
					'creatorstack-ai'
				),
			} );
			return;
		}

		if ( recordingTooLarge ) {
			setPanelNotice( {
				status: 'error',
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

		setAudioToPostBusy( true );
		setPanelNotice( null );

		try {
			let audioAttachment = selectedAudio;

			if ( ! audioAttachment?.id && recorder.recordedBlob ) {
				const audioFile = createAudioFileFromBlob(
					recorder.recordedBlob,
					'wttba-editor-recording'
				);
				audioAttachment = await uploadAudioAttachment(
					audioFile,
					__( 'Editor audio recording', 'creatorstack-ai' )
				);
				setSelectedAudio( audioAttachment );
			}

			const result = await previewAudioPost(
				post.id,
				audioAttachment.id,
				language,
				persona
			);

			editPost( {
				title: result.title,
				content: result.content,
				meta: {
					_wttba_source_type: 'audio_upload',
					_wttba_source_attachment_id:
						result.source_attachment_id || audioAttachment.id,
				},
			} );

			await savePost();
			createSuccessNotice(
				__( 'Draft updated from audio.', 'creatorstack-ai' ),
				{ type: 'snackbar' }
			);
			setPanelNotice( {
				status: 'success',
				message: __( 'Draft updated from audio.', 'creatorstack-ai' ),
			} );
		} catch ( err ) {
			showError( err );
		} finally {
			setAudioToPostBusy( false );
		}
	};

	const handlePostToAudio = async () => {
		if ( ! isPostToAudioEnabled || ! post.id ) {
			return;
		}

		setPostToAudioBusy( true );
		setPanelNotice( null );

		try {
			if ( post.isDirty ) {
				await savePost();
			}

			const result = await generatePostAudio( post.id, voice, true );
			const existingAudioBlocks = blocks.filter( ( block ) =>
				( block.attributes?.className || '' )
					.split( ' ' )
					.includes( GENERATED_AUDIO_CLASS )
			);

			if ( existingAudioBlocks.length ) {
				removeBlocks(
					existingAudioBlocks.map( ( block ) => block.clientId )
				);
			}

			insertBlocks(
				createBlock( 'core/audio', {
					id: result.attachment_id,
					src: result.audio_url,
					className: GENERATED_AUDIO_CLASS,
				} ),
				0
			);

			editPost( {
				meta: {
					_wttba_generated_audio_attachment_id: result.attachment_id,
				},
			} );

			await savePost();
			createSuccessNotice(
				__( 'Audio generated for this post.', 'creatorstack-ai' ),
				{ type: 'snackbar' }
			);
			setPanelNotice( {
				status: 'success',
				message: __(
					'Audio generated for this post.',
					'creatorstack-ai'
				),
			} );
		} catch ( err ) {
			showError( err );
		} finally {
			setPostToAudioBusy( false );
		}
	};

	return createElement(
		PluginDocumentSettingPanel,
		{
			name: 'wttba-ai-content-suite',
			title: __( 'CreatorStack AI', 'creatorstack-ai' ),
			className: 'wttba-editor-panel',
		},
		panelNotice &&
			createElement( CompactPanelNotice, {
				notice: panelNotice,
				onDismiss: () => setPanelNotice( null ),
			} ),
		isAudioToPostEnabled &&
			createElement(
				'div',
				{ className: 'wttba-editor-panel__section' },
				createElement(
					'h3',
					{ className: 'wttba-editor-panel__heading' },
					__( 'Audio to Post', 'creatorstack-ai' )
				),
				! canGenerateFromAudio &&
					createElement(
						'p',
						{ className: 'wttba-editor-panel__muted' },
						ai.unavailableMessage ||
							__(
								'Configure an AI provider with audio input support.',
								'creatorstack-ai'
							)
					),
				createElement(
					MediaUploadCheck,
					null,
					createElement( MediaUpload, {
						allowedTypes: [ 'audio' ],
						onSelect: ( media ) => {
							recorder.reset();
							setSelectedAudio( media );
						},
						render: ( { open } ) =>
							createElement(
								Button,
								{
									variant: 'secondary',
									onClick: open,
									disabled: isBusy || ! canGenerateFromAudio,
								},
								selectedAudio
									? __( 'Change Audio', 'creatorstack-ai' )
									: __( 'Select Audio', 'creatorstack-ai' )
							),
					} )
				),
				createElement(
					'div',
					{
						className: recorder.isRecording
							? 'wttba-editor-panel__recorder is-recording'
							: 'wttba-editor-panel__recorder',
						'data-state': recorder.status,
					},
					createElement(
						'p',
						{
							className: 'wttba-editor-panel__recorder-status',
							'aria-live': 'polite',
						},
						createElement( 'span', {
							className: 'wttba-editor-panel__recorder-dot',
							'aria-hidden': true,
						} ),
						createElement(
							'span',
							null,
							getRecorderStatusText( recorder )
						)
					),
					createElement(
						'div',
						{ className: 'wttba-editor-panel__recorder-actions' },
						! recorder.isRecording &&
							createElement(
								Button,
								{
									variant: 'secondary',
									onClick: recorder.start,
									disabled:
										isBusy ||
										! canGenerateFromAudio ||
										! recorder.isSupported,
								},
								recorder.hasRecording
									? __( 'Record Again', 'creatorstack-ai' )
									: __( 'Record Audio', 'creatorstack-ai' )
							),
						recorder.isRecording &&
							createElement(
								Button,
								{
									variant: 'primary',
									onClick: recorder.stop,
									disabled: isBusy,
								},
								__( 'Stop Recording', 'creatorstack-ai' )
							),
						recorder.hasRecording &&
							! recorder.isRecording &&
							createElement(
								Button,
								{
									variant: 'tertiary',
									onClick: recorder.reset,
									disabled: isBusy,
								},
								__( 'Discard', 'creatorstack-ai' )
							)
					),
					recorder.recordedUrl &&
						createElement( 'audio', {
							className: 'wttba-editor-panel__recorder-preview',
							controls: true,
							src: recorder.recordedUrl,
						} )
				),
				selectedAudio &&
					createElement(
						'p',
						{ className: 'wttba-editor-panel__file' },
						getAudioAttachmentLabel( selectedAudio ),
						selectedAudio.filesizeInBytes
							? sprintf(
									/* translators: %s: formatted file size. */
									__( '(%s)', 'creatorstack-ai' ),
									formatBytes( selectedAudio.filesizeInBytes )
							  )
							: ''
					),
				recordingTooLarge &&
					createElement(
						'p',
						{ className: 'wttba-editor-panel__muted is-error' },
						sprintf(
							/* translators: %s: maximum upload size. */
							__(
								'This recording is larger than the %s upload limit.',
								'creatorstack-ai'
							),
							formatBytes( maxAudioBytes )
						)
					),
				createElement( SelectControl, {
					label: __( 'Output language', 'creatorstack-ai' ),
					value: language,
					options: Object.entries( languages ).map(
						( [ value, label ] ) => ( {
							value,
							label,
						} )
					),
					onChange: setLanguage,
					disabled: isBusy || ! canGenerateFromAudio,
				} ),
				createElement( TextareaControl, {
					label: __( 'Writing persona', 'creatorstack-ai' ),
					value: persona,
					onChange: setPersona,
					rows: 4,
					disabled: isBusy || ! canGenerateFromAudio,
				} ),
				createElement(
					Button,
					{
						variant: 'primary',
						onClick: handleAudioToPost,
						disabled:
							isBusy ||
							! canGenerateFromAudio ||
							! hasAudioSource ||
							recordingTooLarge,
					},
					audioToPostBusy && createElement( Spinner ),
					audioToPostBusy
						? __( 'Generating…', 'creatorstack-ai' )
						: __( 'Generate Draft', 'creatorstack-ai' )
				)
			),
		isPostToAudioEnabled &&
			createElement(
				'div',
				{ className: 'wttba-editor-panel__section' },
				createElement(
					'h3',
					{ className: 'wttba-editor-panel__heading' },
					__( 'Post to Audio', 'creatorstack-ai' )
				),
				! canGeneratePostAudio &&
					createElement(
						'p',
						{ className: 'wttba-editor-panel__muted' },
						__(
							'Configure an AI provider with text-to-speech support.',
							'creatorstack-ai'
						)
					),
				createElement( TextControl, {
					label: __( 'Voice', 'creatorstack-ai' ),
					value: voice,
					onChange: setVoice,
					disabled: isBusy || ! canGeneratePostAudio,
				} ),
				createElement(
					Button,
					{
						variant: 'secondary',
						onClick: handlePostToAudio,
						disabled: isBusy || ! canGeneratePostAudio,
					},
					postToAudioBusy && createElement( Spinner ),
					postToAudioBusy
						? __( 'Generating…', 'creatorstack-ai' )
						: __( 'Generate Audio', 'creatorstack-ai' )
				)
			)
	);
}

registerPlugin( 'wttba-ai-content-suite', {
	render: ContentSuitePanel,
	icon: 'format-audio',
} );
