/**
 * Draft preview modal component.
 */
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import AccessibleModal from './accessible-modal';

/**
 * Preview modal for AI-generated blog post content.
 *
 * @param {Object}   props
 * @param {boolean}  props.isOpen         Whether the modal is visible.
 * @param {string}   props.title          The generated post title.
 * @param {string}   props.content        The generated HTML content.
 * @param {boolean}  props.isRegenerating Whether a regeneration is in progress.
 * @param {boolean}  props.isSaving       Whether the draft is being saved.
 * @param {string}   props.savingMode     Which save action is running: 'draft' or 'edit'.
 * @param {Function} props.onSaveAsDraft  Callback to save the content as a draft.
 * @param {Function} props.onSaveAndEdit  Callback to save the draft and open the editor.
 * @param {Function} props.onRegenerate   Callback to regenerate the content.
 * @param {Function} props.onCancel       Callback to close the modal.
 * @return {Element|null} The modal element or null.
 */
export default function PreviewModal( {
	isOpen,
	title,
	content,
	isRegenerating,
	isSaving,
	savingMode,
	onSaveAsDraft,
	onSaveAndEdit,
	onRegenerate,
	onCancel,
} ) {
	const statusRef = useRef( null );
	const wasRegenerating = useRef( false );
	const [ statusMessage, setStatusMessage ] = useState( '' );

	useEffect( () => {
		if ( isRegenerating ) {
			wasRegenerating.current = true;
			setStatusMessage(
				__( 'Regenerating draft preview…', 'creatorstack-ai' )
			);
			statusRef.current?.focus();
		} else if ( wasRegenerating.current ) {
			wasRegenerating.current = false;
			setStatusMessage(
				__( 'Draft preview regenerated.', 'creatorstack-ai' )
			);
		}
	}, [ isRegenerating ] );

	useEffect( () => {
		if ( isSaving ) {
			setStatusMessage(
				'edit' === savingMode
					? __(
							'Saving draft and opening the editor…',
							'creatorstack-ai'
					  )
					: __( 'Saving draft…', 'creatorstack-ai' )
			);
			statusRef.current?.focus();
		}
	}, [ isSaving, savingMode ] );

	if ( ! isOpen ) {
		return null;
	}

	const isDisabled = isRegenerating || isSaving;

	return createElement(
		AccessibleModal,
		{
			isOpen,
			onRequestClose: onCancel,
			canClose: ! isDisabled,
			title: __( 'Draft Preview', 'creatorstack-ai' ),
			className: 'wttba-modal--preview',
			isBusy: isDisabled,
			size: 'large',
		},
		createElement(
			'p',
			{
				ref: statusRef,
				className: 'wttba-modal__status',
				role: 'status',
				'aria-live': 'polite',
				'aria-atomic': true,
				tabIndex: -1,
			},
			statusMessage
		),
		createElement(
			'h4',
			{ className: 'wttba-modal__preview-title' },
			title
		),
		createElement( 'div', {
			className: 'wttba-modal__preview-content',
			dangerouslySetInnerHTML: { __html: content },
		} ),
		createElement(
			'div',
			{ className: 'wttba-modal__actions' },
			createElement(
				'button',
				{
					className: 'button button-secondary',
					onClick: onCancel,
					disabled: isDisabled,
					type: 'button',
				},
				__( 'Cancel', 'creatorstack-ai' )
			),
			createElement(
				'button',
				{
					className: 'button button-secondary',
					onClick: onRegenerate,
					disabled: isDisabled,
					type: 'button',
				},
				isRegenerating
					? __( 'Regenerating…', 'creatorstack-ai' )
					: __( 'Regenerate', 'creatorstack-ai' )
			),
			createElement(
				'button',
				{
					className: 'button button-secondary',
					onClick: onSaveAsDraft,
					disabled: isDisabled,
					type: 'button',
				},
				isSaving && 'draft' === savingMode
					? __( 'Saving…', 'creatorstack-ai' )
					: __( 'Save as Draft', 'creatorstack-ai' )
			),
			createElement(
				'button',
				{
					className: 'button button-primary',
					onClick: onSaveAndEdit,
					disabled: isDisabled,
					type: 'button',
				},
				isSaving && 'edit' === savingMode
					? __( 'Opening editor…', 'creatorstack-ai' )
					: __( 'Save & Edit', 'creatorstack-ai' )
			)
		)
	);
}
