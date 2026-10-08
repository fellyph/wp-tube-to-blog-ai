/**
 * Draft preview modal component.
 */
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import AccessibleModal from './accessible-modal';
import ErrorNotice from './error-notice';

/**
 * Preview modal for AI-generated blog post content.
 *
 * @param {Object}        props
 * @param {boolean}       props.isOpen         Whether the modal is visible.
 * @param {string}        props.title          The generated post title.
 * @param {string}        props.content        The generated HTML content.
 * @param {boolean}       props.isRegenerating Whether a regeneration is in progress.
 * @param {boolean}       props.isSaving       Whether the draft is being saved.
 * @param {string}        props.savingMode     Which save action is running: 'draft' or 'edit'.
 * @param {Object|null}   props.error          Optional error object to display in the modal.
 * @param {Function|null} props.onDismissError Optional callback to dismiss the error notice.
 * @param {string}        props.settingsUrl    Optional settings URL for error actions.
 * @param {Function}      props.onSaveAsDraft  Callback to save the content as a draft.
 * @param {Function}      props.onSaveAndEdit  Callback to save the draft and open the editor.
 * @param {Function}      props.onRegenerate   Callback to regenerate the content.
 * @param {Function}      props.onCancel       Callback to close the modal.
 * @return {Element|null} The modal element or null.
 */
export default function PreviewModal( {
	isOpen,
	title,
	content,
	isRegenerating,
	isSaving,
	savingMode,
	error = null,
	onDismissError,
	settingsUrl,
	onSaveAsDraft,
	onSaveAndEdit,
	onRegenerate,
	onCancel,
} ) {
	const primaryButtonRef = useRef( null );
	const wasRegeneratingRef = useRef( false );
	const [ statusMessage, setStatusMessage ] = useState( '' );

	useEffect( () => {
		if ( isRegenerating ) {
			wasRegeneratingRef.current = true;
			setStatusMessage(
				__( 'Regenerating draft preview…', 'creatorstack-ai' )
			);
		} else if ( wasRegeneratingRef.current ) {
			wasRegeneratingRef.current = false;
			setStatusMessage(
				__( 'Draft preview regenerated.', 'creatorstack-ai' )
			);
			primaryButtonRef.current?.focus();
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
				className: 'wttba-modal__status',
				role: 'status',
				'aria-live': 'polite',
				'aria-atomic': true,
			},
			statusMessage
		),
		error &&
			createElement( ErrorNotice, {
				code: error.code,
				message: error.message,
				category: error.category,
				configurationUrl: error.configurationUrl,
				configurationLabel: error.configurationLabel,
				onDismiss: onDismissError,
				settingsUrl,
			} ),
		createElement(
			'h2',
			{ className: 'wttba-modal__preview-title' },
			title
		),
		createElement( 'div', {
			className: 'wttba-modal__preview-content',
			role: 'region',
			'aria-label': __(
				'Generated post content preview',
				'creatorstack-ai'
			),
			tabIndex: 0,
			dangerouslySetInnerHTML: { __html: content },
		} ),
		createElement(
			'div',
			{ className: 'wttba-modal__actions' },
			createElement(
				'button',
				{
					className: 'button button-secondary',
					onClick: () => {
						if ( isDisabled ) {
							return;
						}
						onCancel();
					},
					'aria-disabled': isDisabled,
					type: 'button',
				},
				__( 'Cancel', 'creatorstack-ai' )
			),
			createElement(
				'button',
				{
					className: 'button button-secondary',
					onClick: () => {
						if ( isDisabled ) {
							return;
						}
						onRegenerate();
					},
					'aria-disabled': isDisabled,
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
					onClick: () => {
						if ( isDisabled ) {
							return;
						}
						onSaveAsDraft();
					},
					'aria-disabled': isDisabled,
					type: 'button',
				},
				isSaving && 'draft' === savingMode
					? __( 'Saving…', 'creatorstack-ai' )
					: __( 'Save as Draft', 'creatorstack-ai' )
			),
			createElement(
				'button',
				{
					ref: primaryButtonRef,
					className: 'button button-primary',
					onClick: () => {
						if ( isDisabled ) {
							return;
						}
						onSaveAndEdit();
					},
					'aria-disabled': isDisabled,
					type: 'button',
				},
				isSaving && 'edit' === savingMode
					? __( 'Opening editor…', 'creatorstack-ai' )
					: __( 'Save & Edit', 'creatorstack-ai' )
			)
		)
	);
}
