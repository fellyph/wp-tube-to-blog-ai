/**
 * Language selection modal component.
 */
import { createElement, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Language selection modal.
 *
 * @param {Object}   props
 * @param {boolean}  props.isOpen        Whether the modal is visible.
 * @param {Object}   props.languages     Language map { code: label }.
 * @param {string}   props.defaultLang   Default selected language.
 * @param {Function} props.onConfirm     Callback with selected language code.
 * @param {Function} props.onCancel      Callback to close the modal.
 * @param {string}   props.videoTitle    The video title being generated.
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

	if ( ! isOpen ) {
		return null;
	}

	return createElement(
		'div',
		{ className: 'wttba-modal-overlay' },
		createElement(
			'div',
			{ className: 'wttba-modal' },
			createElement(
				'h3',
				{ className: 'wttba-modal__title' },
				__( 'Generate Blog Post', 'wp-tube-to-blog-ai' )
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
				__( 'Output Language:', 'wp-tube-to-blog-ai' )
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
					createElement(
						'option',
						{ key: code, value: code },
						label
					)
				)
			),
			createElement(
				'label',
				{
					className: 'wttba-modal__label',
					htmlFor: 'wttba-persona-textarea',
				},
				__( 'Writing Persona:', 'wp-tube-to-blog-ai' )
			),
			createElement( 'textarea', {
				id: 'wttba-persona-textarea',
				className: 'wttba-modal__textarea',
				rows: 4,
				value: persona,
				onChange: ( e ) => setPersona( e.target.value ),
				placeholder: __(
					'e.g., Conversational tone, short paragraphs, humor, actionable tips…',
					'wp-tube-to-blog-ai'
				),
			} ),
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
					__( 'Cancel', 'wp-tube-to-blog-ai' )
				),
				createElement(
					'button',
					{
						className: 'button button-primary',
						onClick: () => onConfirm( selectedLang, persona ),
						type: 'button',
					},
					__( 'Generate', 'wp-tube-to-blog-ai' )
				)
			)
		)
	);
}
