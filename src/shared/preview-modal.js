/**
 * Draft preview modal component.
 */
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Preview modal for AI-generated blog post content.
 *
 * @param {Object}   props
 * @param {boolean}  props.isOpen         Whether the modal is visible.
 * @param {string}   props.title          The generated post title.
 * @param {string}   props.content        The generated HTML content.
 * @param {boolean}  props.isRegenerating Whether a regeneration is in progress.
 * @param {boolean}  props.isSaving       Whether the draft is being saved.
 * @param {Function} props.onSaveAsDraft  Callback to save the content as a draft.
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
	onSaveAsDraft,
	onRegenerate,
	onCancel,
} ) {
	if ( ! isOpen ) {
		return null;
	}

	const isDisabled = isRegenerating || isSaving;

	return createElement(
		'div',
		{ className: 'wttba-modal-overlay' },
		createElement(
			'div',
			{ className: 'wttba-modal wttba-modal--preview' },
			createElement(
				'h3',
				{ className: 'wttba-modal__title' },
				__( 'Draft Preview', 'wp-tube-to-blog-ai' )
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
					__( 'Cancel', 'wp-tube-to-blog-ai' )
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
						? __( 'Regenerating…', 'wp-tube-to-blog-ai' )
						: __( 'Regenerate', 'wp-tube-to-blog-ai' )
				),
				createElement(
					'button',
					{
						className: 'button button-primary',
						onClick: onSaveAsDraft,
						disabled: isDisabled,
						type: 'button',
					},
					isSaving
						? __( 'Saving…', 'wp-tube-to-blog-ai' )
						: __( 'Save as Draft', 'wp-tube-to-blog-ai' )
				)
			)
		)
	);
}
