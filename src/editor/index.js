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
	previewThumbnail,
	setGeneratedThumbnail,
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
 * Get a readable image attachment label from media responses.
 *
 * @param {Object} media Media object.
 * @return {string} Attachment label.
 */
function getImageAttachmentLabel( media ) {
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

	return __( 'Selected image', 'creatorstack-ai' );
}

/**
 * Get a preview URL from a media response.
 *
 * @param {Object} media Media object.
 * @return {string} Image URL.
 */
function getImageAttachmentUrl( media ) {
	return (
		media?.sizes?.thumbnail?.url ||
		media?.sizes?.medium?.url ||
		media?.media_details?.sizes?.thumbnail?.source_url ||
		media?.media_details?.sizes?.medium?.source_url ||
		media?.source_url ||
		media?.url ||
		''
	);
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
	const thumbnailStyles = config.thumbnailStyles || {};
	const defaultThumbnailStyle =
		Object.keys( thumbnailStyles )[ 0 ] || 'bold_youtube';
	const isAudioToPostEnabled = features.audioToPost !== false;
	const isPostToAudioEnabled = features.postToAudio === true;
	const isThumbnailGeneratorEnabled = features.thumbnailGenerator !== false;
	const [ selectedAudio, setSelectedAudio ] = useState( null );
	const [ language, setLanguage ] = useState(
		config.defaultLanguage || 'en'
	);
	const [ persona, setPersona ] = useState( config.defaultPersona || '' );
	const [ voice, setVoice ] = useState( '' );
	const [ thumbnailStyle, setThumbnailStyle ] = useState(
		defaultThumbnailStyle
	);
	const [ thumbnailSecondaryStyle, setThumbnailSecondaryStyle ] =
		useState( '' );
	const [ thumbnailAuthor, setThumbnailAuthor ] = useState( null );
	const [ thumbnailReferences, setThumbnailReferences ] = useState( [] );
	const [ thumbnailPreview, setThumbnailPreview ] = useState( null );
	const [ audioToPostBusy, setAudioToPostBusy ] = useState( false );
	const [ postToAudioBusy, setPostToAudioBusy ] = useState( false );
	const [ thumbnailBusy, setThumbnailBusy ] = useState( false );
	const [ thumbnailSaving, setThumbnailSaving ] = useState( false );
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
		( ! isAudioToPostEnabled &&
			! isPostToAudioEnabled &&
			! isThumbnailGeneratorEnabled )
	) {
		return null;
	}

	const canGenerateFromAudio =
		isAudioToPostEnabled && !! ai.audioInputSupported;
	const canGeneratePostAudio =
		isPostToAudioEnabled && !! ai.textToSpeechSupported;
	const canGenerateThumbnail =
		isThumbnailGeneratorEnabled && !! ai.imageGenerationSupported;
	const canUseThumbnailReferences =
		isThumbnailGeneratorEnabled && !! ai.imageReferenceInputSupported;
	const isBusy =
		audioToPostBusy ||
		postToAudioBusy ||
		thumbnailBusy ||
		thumbnailSaving ||
		post.isSaving;
	const maxAudioBytes = Number( config.maxAudioBytes || 0 );
	const maxThumbnailReferences = Number( config.maxThumbnailReferences || 2 );
	const recordingTooLarge =
		!! maxAudioBytes &&
		!! recorder.recordedBlob &&
		recorder.recordedBlob.size > maxAudioBytes;
	const hasAudioSource = !! selectedAudio?.id || recorder.hasRecording;
	const thumbnailStyleOptions = Object.entries( thumbnailStyles ).map(
		( [ value, style ] ) => ( {
			value,
			label: style.label || value,
		} )
	);
	const thumbnailSecondaryStyleOptions = [
		{
			value: '',
			label: __( 'No secondary style', 'creatorstack-ai' ),
		},
		...thumbnailStyleOptions.filter(
			( option ) => option.value !== thumbnailStyle
		),
	];
	const thumbnailPreviewUrl =
		thumbnailPreview?.image_data_uri || thumbnailPreview?.image_url || '';
	let thumbnailGenerateButtonLabel = __(
		'Generate Thumbnail',
		'creatorstack-ai'
	);
	if ( thumbnailPreview ) {
		thumbnailGenerateButtonLabel = __( 'Regenerate', 'creatorstack-ai' );
	}
	if ( thumbnailBusy ) {
		thumbnailGenerateButtonLabel = __( 'Generating…', 'creatorstack-ai' );
	}
	const thumbnailGenerateButtonVariant = thumbnailPreview
		? 'secondary'
		: 'primary';

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
				{
					type: 'snackbar',
				}
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

	const handleThumbnailReferencesSelect = ( media ) => {
		const selected = ( Array.isArray( media ) ? media : [ media ] )
			.filter( Boolean )
			.slice( 0, maxThumbnailReferences );

		setThumbnailReferences( selected );
		setThumbnailPreview( null );
	};

	const clearThumbnailReference = ( attachmentId ) => {
		setThumbnailReferences( ( current ) =>
			current.filter( ( item ) => item.id !== attachmentId )
		);
		setThumbnailPreview( null );
	};

	const handleThumbnailGenerate = async () => {
		if ( ! isThumbnailGeneratorEnabled || ! post.id ) {
			return;
		}

		if ( ! canGenerateThumbnail ) {
			setPanelNotice( {
				status: 'error',
				message:
					ai.unavailableMessage ||
					__(
						'Configure an AI provider with image generation support.',
						'creatorstack-ai'
					),
				configurationUrl: ai.configurationUrl || config.settingsUrl,
				configurationLabel: __(
					'Configure AI Provider',
					'creatorstack-ai'
				),
			} );
			return;
		}

		if (
			( thumbnailAuthor || thumbnailReferences.length > 0 ) &&
			! canUseThumbnailReferences
		) {
			setPanelNotice( {
				status: 'error',
				message: __(
					'The configured AI provider does not support image references.',
					'creatorstack-ai'
				),
				configurationUrl: ai.configurationUrl || config.settingsUrl,
				configurationLabel: __(
					'Configure AI Provider',
					'creatorstack-ai'
				),
			} );
			return;
		}

		setThumbnailBusy( true );
		setPanelNotice( null );

		try {
			if ( post.isDirty ) {
				await savePost();
			}

			const result = await previewThumbnail(
				post.id,
				thumbnailStyle,
				thumbnailSecondaryStyle === thumbnailStyle
					? ''
					: thumbnailSecondaryStyle,
				thumbnailAuthor?.id || 0,
				thumbnailReferences.map( ( media ) => media.id )
			);

			setThumbnailPreview( result );
			setPanelNotice( {
				status: 'success',
				message: __(
					'Thumbnail preview generated.',
					'creatorstack-ai'
				),
			} );
		} catch ( err ) {
			showError( err );
		} finally {
			setThumbnailBusy( false );
		}
	};

	const handleThumbnailSave = async () => {
		if ( ! post.id || ! thumbnailPreview?.preview_id ) {
			return;
		}

		setThumbnailSaving( true );
		setPanelNotice( null );

		try {
			const result = await setGeneratedThumbnail(
				post.id,
				thumbnailPreview.preview_id
			);

			editPost( {
				featured_media: result.attachment_id,
				meta: {
					_wttba_generated_thumbnail_attachment_id:
						result.attachment_id,
				},
			} );

			await savePost();
			createSuccessNotice(
				__( 'Featured image updated.', 'creatorstack-ai' ),
				{
					type: 'snackbar',
				}
			);
			setThumbnailPreview( null );
			setPanelNotice( {
				status: 'success',
				message: __( 'Featured image updated.', 'creatorstack-ai' ),
			} );
		} catch ( err ) {
			showError( err );
		} finally {
			setThumbnailSaving( false );
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
		isThumbnailGeneratorEnabled &&
			createElement(
				'div',
				{
					className:
						'wttba-editor-panel__section wttba-editor-panel__section--thumbnail',
				},
				createElement(
					'h3',
					{ className: 'wttba-editor-panel__heading' },
					__( 'Thumbnail', 'creatorstack-ai' )
				),
				! canGenerateThumbnail &&
					createElement(
						'p',
						{ className: 'wttba-editor-panel__muted' },
						__(
							'Configure an AI provider with image generation support.',
							'creatorstack-ai'
						)
					),
				createElement( SelectControl, {
					label: __( 'Style', 'creatorstack-ai' ),
					value: thumbnailStyle,
					options: thumbnailStyleOptions,
					onChange: ( nextStyle ) => {
						setThumbnailStyle( nextStyle );
						if ( thumbnailSecondaryStyle === nextStyle ) {
							setThumbnailSecondaryStyle( '' );
						}
						setThumbnailPreview( null );
					},
					disabled: isBusy || ! canGenerateThumbnail,
				} ),
				createElement( SelectControl, {
					label: __( 'Blend style', 'creatorstack-ai' ),
					value: thumbnailSecondaryStyle,
					options: thumbnailSecondaryStyleOptions,
					onChange: ( nextStyle ) => {
						setThumbnailSecondaryStyle( nextStyle );
						setThumbnailPreview( null );
					},
					disabled: isBusy || ! canGenerateThumbnail,
				} ),
				! canUseThumbnailReferences &&
					createElement(
						'p',
						{ className: 'wttba-editor-panel__muted' },
						__(
							'This AI provider does not support author, logo, or object reference images.',
							'creatorstack-ai'
						)
					),
				createElement(
					MediaUploadCheck,
					null,
					createElement( MediaUpload, {
						allowedTypes: [ 'image' ],
						value: thumbnailAuthor?.id || 0,
						onSelect: ( media ) => {
							setThumbnailAuthor( media );
							setThumbnailPreview( null );
						},
						render: ( { open } ) =>
							createElement(
								Button,
								{
									variant: 'secondary',
									onClick: open,
									disabled:
										isBusy ||
										! canGenerateThumbnail ||
										! canUseThumbnailReferences,
								},
								thumbnailAuthor
									? __(
											'Change Author Image',
											'creatorstack-ai'
									  )
									: __(
											'Select Author Image',
											'creatorstack-ai'
									  )
							),
					} )
				),
				thumbnailAuthor &&
					createElement(
						'div',
						{ className: 'wttba-editor-panel__image-chip' },
						getImageAttachmentUrl( thumbnailAuthor ) &&
							createElement( 'img', {
								src: getImageAttachmentUrl( thumbnailAuthor ),
								alt: '',
								className:
									'wttba-editor-panel__image-chip-thumb',
							} ),
						createElement(
							'span',
							{
								className:
									'wttba-editor-panel__image-chip-label',
							},
							getImageAttachmentLabel( thumbnailAuthor )
						),
						createElement(
							Button,
							{
								variant: 'tertiary',
								onClick: () => {
									setThumbnailAuthor( null );
									setThumbnailPreview( null );
								},
								disabled: isBusy,
							},
							__( 'Remove', 'creatorstack-ai' )
						)
					),
				createElement(
					MediaUploadCheck,
					null,
					createElement( MediaUpload, {
						allowedTypes: [ 'image' ],
						multiple: true,
						gallery: false,
						value: thumbnailReferences.map( ( media ) => media.id ),
						onSelect: handleThumbnailReferencesSelect,
						render: ( { open } ) =>
							createElement(
								Button,
								{
									variant: 'secondary',
									onClick: open,
									disabled:
										isBusy ||
										! canGenerateThumbnail ||
										! canUseThumbnailReferences,
								},
								thumbnailReferences.length
									? __(
											'Change Logos or Objects',
											'creatorstack-ai'
									  )
									: __(
											'Select Logos or Objects',
											'creatorstack-ai'
									  )
							),
					} )
				),
				thumbnailReferences.length > 0 &&
					createElement(
						'div',
						{
							className: 'wttba-editor-panel__image-chip-list',
						},
						thumbnailReferences.map( ( media ) =>
							createElement(
								'div',
								{
									key: media.id,
									className: 'wttba-editor-panel__image-chip',
								},
								getImageAttachmentUrl( media ) &&
									createElement( 'img', {
										src: getImageAttachmentUrl( media ),
										alt: '',
										className:
											'wttba-editor-panel__image-chip-thumb',
									} ),
								createElement(
									'span',
									{
										className:
											'wttba-editor-panel__image-chip-label',
									},
									getImageAttachmentLabel( media )
								),
								createElement(
									Button,
									{
										variant: 'tertiary',
										onClick: () =>
											clearThumbnailReference( media.id ),
										disabled: isBusy,
									},
									__( 'Remove', 'creatorstack-ai' )
								)
							)
						)
					),
				thumbnailPreviewUrl &&
					createElement(
						'figure',
						{
							className: 'wttba-editor-panel__thumbnail-preview',
						},
						createElement( 'img', {
							src: thumbnailPreviewUrl,
							alt: __(
								'Generated thumbnail preview',
								'creatorstack-ai'
							),
						} )
					),
				createElement(
					'div',
					{ className: 'wttba-editor-panel__thumbnail-actions' },
					createElement(
						Button,
						{
							variant: thumbnailGenerateButtonVariant,
							onClick: handleThumbnailGenerate,
							disabled: isBusy || ! canGenerateThumbnail,
						},
						thumbnailBusy && createElement( Spinner ),
						thumbnailGenerateButtonLabel
					),
					thumbnailPreview &&
						createElement(
							Button,
							{
								variant: 'primary',
								onClick: handleThumbnailSave,
								disabled:
									isBusy || thumbnailBusy || thumbnailSaving,
							},
							thumbnailSaving && createElement( Spinner ),
							thumbnailSaving
								? __( 'Setting…', 'creatorstack-ai' )
								: __( 'Set Featured Image', 'creatorstack-ai' )
						)
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
