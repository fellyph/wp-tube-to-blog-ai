/**
 * Shared state and actions for the YouTube-to-post generation flow.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { parseError, previewPost, saveDraft } from './api';

/**
 * Manage generation, preview, regeneration, and draft saving.
 *
 * @param {Object} config
 * @param {Object} config.ai          AI capability configuration.
 * @param {string} config.settingsUrl Plugin settings URL.
 * @return {Object} Generation state and actions.
 */
export default function useVideoPostGeneration( { ai, settingsUrl } ) {
	const isTextGenerationSupported =
		ai.textGenerationSupported !== undefined
			? ai.textGenerationSupported
			: true;
	const [ error, setError ] = useState( null );
	const [ modalVideo, setModalVideo ] = useState( null );
	const [ generating, setGenerating ] = useState( null );
	const [ success, setSuccess ] = useState( null );
	const [ failedVideo, setFailedVideo ] = useState( null );
	const [ preview, setPreview ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ regenerating, setRegenerating ] = useState( false );
	const [ lastGeneration, setLastGeneration ] = useState( null );
	const [ dismissedAiNotice, setDismissedAiNotice ] = useState( false );

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
				configurationUrl: ai.configurationUrl || settingsUrl,
				configurationLabel: __(
					'Configure AI Provider',
					'creatorstack-ai'
				),
			} );
			setModalVideo( null );
			return;
		}

		const video = modalVideo;
		setGenerating( video.id );
		setModalVideo( null );
		setSuccess( null );
		setError( null );
		setFailedVideo( null );
		setLastGeneration( {
			videoId: video.id,
			language,
			persona,
			manualTranscript,
		} );

		previewPost( video.id, language, persona, manualTranscript )
			.then( ( result ) => {
				setGenerating( null );
				setPreview( result );
			} )
			.catch( ( requestError ) => {
				setGenerating( null );
				setFailedVideo( video );
				setError( parseError( requestError ) );
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
			.catch( ( requestError ) => {
				setSaving( false );
				setPreview( null );
				setError( parseError( requestError ) );
			} );
	};

	const handleRegenerate = () => {
		if ( ! lastGeneration ) {
			return;
		}

		setRegenerating( true );
		previewPost(
			lastGeneration.videoId,
			lastGeneration.language,
			lastGeneration.persona,
			lastGeneration.manualTranscript || ''
		)
			.then( ( result ) => {
				setRegenerating( false );
				setPreview( result );
			} )
			.catch( ( requestError ) => {
				setRegenerating( false );
				setPreview( null );
				setError( parseError( requestError ) );
			} );
	};

	const retryFailedVideo = failedVideo
		? () => {
				setError( null );
				setModalVideo( failedVideo );
				setFailedVideo( null );
		  }
		: null;

	const getGenerateButtonLabel = ( video ) => {
		if ( ! isTextGenerationSupported ) {
			return __( 'AI unavailable', 'creatorstack-ai' );
		}

		return generating === video.id
			? __( 'Generating…', 'creatorstack-ai' )
			: __( 'Generate Post', 'creatorstack-ai' );
	};

	return {
		dismissedAiNotice,
		error,
		generating,
		isTextGenerationSupported,
		modalVideo,
		preview,
		regenerating,
		retryFailedVideo,
		saving,
		success,
		getGenerateButtonLabel,
		handleGenerate,
		handleRegenerate,
		handleSaveDraft,
		setDismissedAiNotice,
		setError,
		setModalVideo,
		setPreview,
		setSuccess,
	};
}
