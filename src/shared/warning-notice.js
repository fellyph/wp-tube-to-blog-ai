/**
 * Shared dismissible warning notice component.
 */
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Renders a WordPress-styled dismissible warning notice for non-blocking warnings.
 *
 * @param {Object}   props
 * @param {string[]} props.messages  Array of warning messages.
 * @param {Function} props.onDismiss Callback to clear the warnings.
 * @return {Element|null} The warning notice element or null if no messages.
 */
export default function WarningNotice( { messages, onDismiss } ) {
	if ( ! messages || messages.length === 0 ) {
		return null;
	}

	return createElement(
		'div',
		{
			className:
				'notice notice-warning is-dismissible wttba-warning-notice',
		},
		messages.map( ( msg, index ) =>
			createElement( 'p', { key: index }, msg )
		),
		createElement( 'button', {
			type: 'button',
			className: 'notice-dismiss',
			onClick: onDismiss,
			'aria-label': __( 'Dismiss this notice', 'wp-tube-to-blog-ai' ),
		} )
	);
}
