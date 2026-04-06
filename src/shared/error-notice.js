/**
 * Shared dismissible error notice component.
 */
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Renders a WordPress-styled dismissible error notice with contextual actions.
 *
 * @param {Object}        props
 * @param {string}        props.message     The error message to display.
 * @param {string|null}   props.category    Error category: 'configuration', 'rate_limit', 'not_found', 'upstream', 'internal', or null.
 * @param {Function}      props.onDismiss   Callback to clear the error.
 * @param {Function|null} props.onRetry     Callback to retry the failed action.
 * @param {string}        props.settingsUrl URL to the plugin settings page.
 * @return {Element} The error notice element.
 */
export default function ErrorNotice( {
	message,
	category,
	onDismiss,
	onRetry,
	settingsUrl,
} ) {
	return createElement(
		'div',
		{ className: 'notice notice-error is-dismissible wttba-error-notice' },
		createElement( 'p', null, message ),
		category === 'configuration' &&
			settingsUrl &&
			createElement(
				'p',
				null,
				createElement(
					'a',
					{
						href: settingsUrl,
						className: 'button button-secondary button-small',
					},
					__( 'Go to Settings', 'wp-tube-to-blog-ai' )
				)
			),
		category === 'rate_limit' &&
			createElement(
				'p',
				{ className: 'wttba-error-notice__hint' },
				__(
					'Please wait a moment and try again.',
					'wp-tube-to-blog-ai'
				)
			),
		category === 'upstream' &&
			createElement(
				'p',
				{ className: 'wttba-error-notice__hint' },
				__(
					'This may be a temporary issue with an external service.',
					'wp-tube-to-blog-ai'
				)
			),
		onRetry &&
			category !== 'configuration' &&
			category !== 'rate_limit' &&
			createElement(
				'p',
				{ className: 'wttba-error-notice__actions' },
				createElement(
					'button',
					{
						type: 'button',
						className: 'button button-secondary button-small',
						onClick: onRetry,
					},
					__( 'Retry', 'wp-tube-to-blog-ai' )
				)
			),
		createElement( 'button', {
			type: 'button',
			className: 'notice-dismiss',
			onClick: onDismiss,
			'aria-label': __( 'Dismiss this notice', 'wp-tube-to-blog-ai' ),
		} )
	);
}
