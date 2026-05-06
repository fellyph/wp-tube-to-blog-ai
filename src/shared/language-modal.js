/**
 * Language selection modal component.
 */
import { createElement, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Language selection modal.
 *
 * @param {Object}   props
 * @param {boolean}  props.isOpen         Whether the modal is visible.
 * @param {Object}   props.languages      Language map { code: label }.
 * @param {string}   props.defaultLang    Default selected language.
 * @param {string}   props.defaultPersona Default writing persona.
 * @param {Function} props.onConfirm      Callback with selected language, persona, and manual transcript.
 * @param {Function} props.onCancel       Callback to close the modal.
 * @param {string}   props.videoTitle     The video title being generated.
 * @return {Element|null} The modal element or null.
 */
export default function LanguageModal( {
	isOpen,
	languages,
	defaultLang,
	defaultPersona,
	onConfirm,
	onCancel,
	videoTitle,
} ) {
	const [ selectedLang, setSelectedLang ] = useState( defaultLang );
	const [ persona, setPersona ] = useState( defaultPersona || '' );
	const [ useManualTranscript, setUseManualTranscript ] = useState( false );
	const [ manualTranscript, setManualTranscript ] = useState( '' );

	useEffect( () => {
		if ( isOpen ) {
			setSelectedLang( defaultLang );
			setPersona( defaultPersona || '' );
			setUseManualTranscript( false );
			setManualTranscript( '' );
		}
	}, [ isOpen, defaultLang, defaultPersona ] );

	if ( ! isOpen ) {
		return null;
	}

	const isGenerateDisabled =
		useManualTranscript && manualTranscript.trim().length < 50;

	return createElement(
		'div',
		{ className: 'wttba-modal-overlay' },
		createElement(
			'div',
			{ className: 'wttba-modal' },
			createElement(
				'h3',
				{ className: 'wttba-modal__title' },
				__( 'Generate Blog Post', 'creatorstack-ai' )
			),
			videoTitle &&
				createElement(
					'p',
					{ className: 'wttba-modal__video-title' },
					videoTitle
				),
			createElement(
				'label',
				{
					className: 'wttba-modal__label',
					htmlFor: 'wttba-language-select',
				},
				__( 'Output Language:', 'creatorstack-ai' )
			),
			createElement(
				'select',
				{
					id: 'wttba-language-select',
					className: 'wttba-modal__select',
					value: selectedLang,
					onChange: ( e ) => setSelectedLang( e.target.value ),
				},
				Object.entries( languages ).map( ( [ code, label ] ) =>
					createElement( 'option', { key: code, value: code }, label )
				)
			),
			createElement(
				'label',
				{
					className: 'wttba-modal__label',
					htmlFor: 'wttba-persona-textarea',
				},
				__( 'Writing Persona:', 'creatorstack-ai' )
			),
			createElement( 'textarea', {
				id: 'wttba-persona-textarea',
				className: 'wttba-modal__textarea',
				rows: 4,
				value: persona,
				onChange: ( e ) => setPersona( e.target.value ),
				placeholder: __(
					'e.g., Conversational tone, short paragraphs, humor, actionable tips…',
					'creatorstack-ai'
				),
			} ),
			createElement(
				'label',
				{ className: 'wttba-modal__checkbox-label' },
				createElement( 'input', {
					type: 'checkbox',
					checked: useManualTranscript,
					onChange: ( e ) =>
						setUseManualTranscript( e.target.checked ),
				} ),
				__(
					'Use a custom transcript instead of fetching captions',
					'creatorstack-ai'
				)
			),
			useManualTranscript &&
				createElement(
					'div',
					{ className: 'wttba-modal__manual-transcript' },
					createElement(
						'label',
						{
							className: 'wttba-modal__label',
							htmlFor: 'wttba-manual-transcript-textarea',
						},
						__( 'Manual Transcript:', 'creatorstack-ai' )
					),
					createElement( 'textarea', {
						id: 'wttba-manual-transcript-textarea',
						className: 'wttba-modal__textarea',
						rows: 8,
						value: manualTranscript,
						onChange: ( e ) =>
							setManualTranscript( e.target.value ),
						placeholder: __(
							'Paste the transcript text for this video.',
							'creatorstack-ai'
						),
					} ),
					createElement(
						'p',
						{ className: 'wttba-modal__description' },
						__(
							'Paste at least 50 characters. When provided, this transcript is used as the source material and YouTube captions are not fetched.',
							'creatorstack-ai'
						)
					)
				),
			createElement(
				'div',
				{ className: 'wttba-modal__actions' },
				createElement(
					'button',
					{
						className: 'button button-secondary',
						onClick: onCancel,
						type: 'button',
					},
					__( 'Cancel', 'creatorstack-ai' )
				),
				createElement(
					'button',
					{
						className: 'button button-primary',
						onClick: () =>
							onConfirm(
								selectedLang,
								persona,
								useManualTranscript
									? manualTranscript.trim()
									: ''
							),
						disabled: isGenerateDisabled,
						type: 'button',
					},
					__( 'Generate', 'creatorstack-ai' )
				)
			)
		)
	);
}
