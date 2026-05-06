/**
 * Shared dismissible error notice component.
 */
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Renders a WordPress-styled dismissible error notice with contextual actions.
 *
 * @param {Object}        props
 * @param {string|null}   props.code               Error code.
 * @param {string}        props.message            The error message to display.
 * @param {string|null}   props.category           Error category.
 * @param {string|null}   props.configurationUrl   URL for the configuration action.
 * @param {string|null}   props.configurationLabel Label for the configuration action.
 * @param {Function}      props.onDismiss          Callback to clear the error.
 * @param {Function|null} props.onRetry            Callback to retry the failed action.
 * @param {string}        props.settingsUrl        URL to the plugin settings page.
 * @return {Element} The error notice element.
 */
export default function ErrorNotice( {
	code,
	message,
	category,
	configurationUrl,
	configurationLabel,
	onDismiss,
	onRetry,
	settingsUrl,
} ) {
	const actionUrl = configurationUrl || settingsUrl;
	const actionLabel =
		configurationLabel || __( 'Go to Settings', 'creatorstack-ai' );
	const transcriptErrorCodes = [
		'wttba_no_captions',
		'wttba_no_tracks',
		'wttba_no_track_url',
		'wttba_empty_transcript',
	];
	const isTranscriptError = transcriptErrorCodes.includes( code );

	return createElement(
		'div',
		{ className: 'notice notice-error is-dismissible wttba-error-notice' },
		createElement( 'p', null, message ),
		isTranscriptError &&
			createElement(
				'p',
				{ className: 'wttba-error-notice__hint' },
				__(
					'This video needs captions or subtitles before it can be converted into a post.',
					'creatorstack-ai'
				)
			),
		category === 'configuration' &&
			actionUrl &&
			createElement(
				'p',
				null,
				createElement(
					'a',
					{
						href: actionUrl,
						className: 'button button-secondary button-small',
					},
					actionLabel
				)
			),
		category === 'rate_limit' &&
			createElement(
				'p',
				{ className: 'wttba-error-notice__hint' },
				__( 'Please wait a moment and try again.', 'creatorstack-ai' )
			),
		category === 'upstream' &&
			createElement(
				'p',
				{ className: 'wttba-error-notice__hint' },
				__(
					'This may be a temporary issue with an external service.',
					'creatorstack-ai'
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
					__( 'Retry', 'creatorstack-ai' )
				)
			),
		createElement( 'button', {
			type: 'button',
			className: 'notice-dismiss',
			onClick: onDismiss,
			'aria-label': __( 'Dismiss this notice', 'creatorstack-ai' ),
		} )
	);
}
