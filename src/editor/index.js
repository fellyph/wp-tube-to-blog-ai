/**
 * Block editor AI Content Suite panel.
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import {
	Button,
	ExternalLink,
	Notice,
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
import { generatePostAudio, parseError, previewAudioPost } from '../shared/api';
import './style.scss';

const GENERATED_AUDIO_CLASS = 'wttba-generated-audio';

/**
 * Format bytes for the editor UI.
 *
 * @param {number} bytes Byte count.
 * @return {string} Formatted bytes.
 */
function formatBytes( bytes ) {
	if ( ! bytes ) {
		return '';
	}

	const units = [ 'B', 'KB', 'MB', 'GB' ];
	let size = bytes;
	let unitIndex = 0;

	while ( size >= 1024 && unitIndex < units.length - 1 ) {
		size /= 1024;
		unitIndex += 1;
	}

	return `${ size.toFixed( unitIndex === 0 ? 0 : 1 ) } ${
		units[ unitIndex ]
	}`;
}

/**
 * AI Content Suite editor panel.
 *
 * @return {Element|null} Panel element.
 */
function ContentSuitePanel() {
	const config = window.wttbaEditorConfig || {};
	const ai = config.ai || {};
	const languages = config.languages || {};
	const [ selectedAudio, setSelectedAudio ] = useState( null );
	const [ language, setLanguage ] = useState(
		config.defaultLanguage || 'en'
	);
	const [ persona, setPersona ] = useState( config.defaultPersona || '' );
	const [ voice, setVoice ] = useState( '' );
	const [ audioToPostBusy, setAudioToPostBusy ] = useState( false );
	const [ postToAudioBusy, setPostToAudioBusy ] = useState( false );
	const [ panelNotice, setPanelNotice ] = useState( null );

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

	if ( 'post' !== post.type ) {
		return null;
	}

	const canGenerateFromAudio = !! ai.audioInputSupported;
	const canGeneratePostAudio = !! ai.textToSpeechSupported;
	const isBusy = audioToPostBusy || postToAudioBusy || post.isSaving;

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
		if ( ! selectedAudio?.id || ! post.id ) {
			setPanelNotice( {
				status: 'error',
				message: __(
					'Select an audio file first.',
					'wp-tube-to-blog-ai'
				),
			} );
			return;
		}

		setAudioToPostBusy( true );
		setPanelNotice( null );

		try {
			const result = await previewAudioPost(
				post.id,
				selectedAudio.id,
				language,
				persona
			);

			editPost( {
				title: result.title,
				content: result.content,
				meta: {
					_wttba_source_type: 'audio_upload',
					_wttba_source_attachment_id:
						result.source_attachment_id || selectedAudio.id,
				},
			} );

			await savePost();
			createSuccessNotice(
				__( 'Draft updated from audio.', 'wp-tube-to-blog-ai' ),
				{ type: 'snackbar' }
			);
			setPanelNotice( {
				status: 'success',
				message: __(
					'Draft updated from audio.',
					'wp-tube-to-blog-ai'
				),
			} );
		} catch ( err ) {
			showError( err );
		} finally {
			setAudioToPostBusy( false );
		}
	};

	const handlePostToAudio = async () => {
		if ( ! post.id ) {
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
				__( 'Audio generated for this post.', 'wp-tube-to-blog-ai' ),
				{ type: 'snackbar' }
			);
			setPanelNotice( {
				status: 'success',
				message: __(
					'Audio generated for this post.',
					'wp-tube-to-blog-ai'
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
			title: __( 'AI Content Suite', 'wp-tube-to-blog-ai' ),
			className: 'wttba-editor-panel',
		},
		panelNotice &&
			createElement(
				Notice,
				{
					status: panelNotice.status,
					isDismissible: true,
					onRemove: () => setPanelNotice( null ),
				},
				panelNotice.message,
				panelNotice.configurationUrl &&
					createElement(
						ExternalLink,
						{ href: panelNotice.configurationUrl },
						panelNotice.configurationLabel ||
							__( 'Configure AI Provider', 'wp-tube-to-blog-ai' )
					)
			),
		createElement(
			'div',
			{ className: 'wttba-editor-panel__section' },
			createElement(
				'h3',
				{ className: 'wttba-editor-panel__heading' },
				__( 'Audio to Post', 'wp-tube-to-blog-ai' )
			),
			! canGenerateFromAudio &&
				createElement(
					'p',
					{ className: 'wttba-editor-panel__muted' },
					ai.unavailableMessage ||
						__(
							'Configure an AI provider with audio input support.',
							'wp-tube-to-blog-ai'
						)
				),
			createElement(
				MediaUploadCheck,
				null,
				createElement( MediaUpload, {
					allowedTypes: [ 'audio' ],
					onSelect: ( media ) => setSelectedAudio( media ),
					render: ( { open } ) =>
						createElement(
							Button,
							{
								variant: 'secondary',
								onClick: open,
								disabled: isBusy || ! canGenerateFromAudio,
							},
							selectedAudio
								? __( 'Change Audio', 'wp-tube-to-blog-ai' )
								: __( 'Select Audio', 'wp-tube-to-blog-ai' )
						),
				} )
			),
			selectedAudio &&
				createElement(
					'p',
					{ className: 'wttba-editor-panel__file' },
					selectedAudio.filename || selectedAudio.title,
					selectedAudio.filesizeInBytes
						? sprintf(
								/* translators: %s: formatted file size. */
								__( '(%s)', 'wp-tube-to-blog-ai' ),
								formatBytes( selectedAudio.filesizeInBytes )
						  )
						: ''
				),
			createElement( SelectControl, {
				label: __( 'Output language', 'wp-tube-to-blog-ai' ),
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
				label: __( 'Writing persona', 'wp-tube-to-blog-ai' ),
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
						isBusy || ! canGenerateFromAudio || ! selectedAudio,
				},
				audioToPostBusy && createElement( Spinner ),
				audioToPostBusy
					? __( 'Generating…', 'wp-tube-to-blog-ai' )
					: __( 'Generate Draft', 'wp-tube-to-blog-ai' )
			)
		),
		createElement(
			'div',
			{ className: 'wttba-editor-panel__section' },
			createElement(
				'h3',
				{ className: 'wttba-editor-panel__heading' },
				__( 'Post to Audio', 'wp-tube-to-blog-ai' )
			),
			! canGeneratePostAudio &&
				createElement(
					'p',
					{ className: 'wttba-editor-panel__muted' },
					__(
						'Configure an AI provider with text-to-speech support.',
						'wp-tube-to-blog-ai'
					)
				),
			createElement( TextControl, {
				label: __( 'Voice', 'wp-tube-to-blog-ai' ),
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
					? __( 'Generating…', 'wp-tube-to-blog-ai' )
					: __( 'Generate Audio', 'wp-tube-to-blog-ai' )
			)
		)
	);
}

registerPlugin( 'wttba-ai-content-suite', {
	render: ContentSuitePanel,
	icon: 'format-audio',
} );
