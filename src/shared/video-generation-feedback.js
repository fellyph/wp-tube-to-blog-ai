/**
 * Shared notices for the YouTube-to-post generation workflow.
 */
import { createElement, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ErrorNotice from './error-notice';
import WarningNotice from './warning-notice';

/**
 * Render generation errors, success, warnings, and AI configuration feedback.
 *
 * @param {Object}        props
 * @param {Object}        props.ai                        AI configuration.
 * @param {Object|null}   props.error                     Current error.
 * @param {Object|null}   props.success                   Current success result.
 * @param {boolean}       props.isTextGenerationSupported Whether text generation is available.
 * @param {boolean}       props.dismissedAiNotice         Whether the AI notice was dismissed.
 * @param {string}        props.settingsUrl               Settings URL.
 * @param {Function}      props.onDismissError            Error dismissal callback.
 * @param {Function|null} props.onRetry                   Retry callback.
 * @param {Function}      props.onDismissWarnings         Warning dismissal callback.
 * @param {Function}      props.onDismissAiNotice         AI notice dismissal callback.
 * @return {Element} Notice elements.
 */
export default function VideoGenerationFeedback( {
	ai,
	error,
	success,
	isTextGenerationSupported,
	dismissedAiNotice,
	settingsUrl,
	onDismissError,
	onRetry,
	onDismissWarnings,
	onDismissAiNotice,
} ) {
	return createElement(
		Fragment,
		null,
		error &&
			createElement( ErrorNotice, {
				code: error.code,
				message: error.message,
				category: error.category,
				configurationUrl: error.configurationUrl,
				configurationLabel: error.configurationLabel,
				onDismiss: onDismissError,
				onRetry,
				settingsUrl,
			} ),
		success &&
			createElement(
				'div',
				{
					className: 'notice notice-success inline',
					role: 'status',
					'aria-live': 'polite',
					'aria-atomic': true,
				},
				createElement(
					'p',
					null,
					__( 'Post generated successfully!', 'creatorstack-ai' ),
					' ',
					createElement(
						'a',
						{ href: success.edit_url },
						__( 'Edit Draft', 'creatorstack-ai' )
					)
				)
			),
		success?.warnings?.length > 0 &&
			createElement( WarningNotice, {
				messages: success.warnings,
				onDismiss: onDismissWarnings,
			} ),
		! isTextGenerationSupported &&
			! dismissedAiNotice &&
			createElement( ErrorNotice, {
				code: 'wttba_ai_not_supported',
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
				onDismiss: onDismissAiNotice,
				settingsUrl,
			} )
	);
}
