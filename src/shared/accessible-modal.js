/**
 * WordPress-native modal frame shared by the admin video surfaces.
 */
import { Modal } from '@wordpress/components';
import { createElement } from '@wordpress/element';

/**
 * Render an accessible modal using WordPress's focus and portal handling.
 *
 * @param {Object}    props
 * @param {boolean}   props.isOpen         Whether the dialog is open.
 * @param {Function}  props.onRequestClose Close callback.
 * @param {boolean}   props.canClose       Whether dismiss actions are enabled.
 * @param {string}    props.title          Accessible dialog title.
 * @param {string}    props.className      Additional dialog class.
 * @param {boolean}   props.isBusy         Whether the dialog is processing.
 * @param {string}    props.size           WordPress modal size.
 * @param {Element[]} props.children       Dialog content.
 * @return {Element|null} Modal element or null.
 */
export default function AccessibleModal( {
	isOpen,
	onRequestClose,
	canClose = true,
	title,
	className = '',
	isBusy = false,
	size = 'medium',
	children,
} ) {
	if ( ! isOpen ) {
		return null;
	}

	return createElement(
		Modal,
		{
			title,
			onRequestClose,
			className: `wttba-modal ${ className }`.trim(),
			overlayClassName: 'wttba-modal-overlay',
			focusOnMount: 'firstContentElement',
			isDismissible: canClose,
			shouldCloseOnClickOutside: canClose,
			shouldCloseOnEsc: canClose,
			size,
			'aria-busy': isBusy || undefined,
		},
		children
	);
}
