import { __ } from '@wordpress/i18n';

/**
 * Format a date string for the current locale.
 *
 * @param {string} dateString ISO date string.
 * @return {string} Formatted date.
 */
export function formatDate( dateString ) {
	return new Date( dateString ).toLocaleDateString( undefined, {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
	} );
}

/**
 * Build the YouTube configuration notice for an app surface.
 *
 * @param {Object} config App configuration.
 * @return {{ message: string, url: string, label: string }} Notice details.
 */
export function getYoutubeConfigurationNotice( config ) {
	const youtube = config.youtube || {};
	const missingApiKey = youtube.apiKeyConfigured === false;

	return {
		message: missingApiKey
			? __(
					'Configure the YouTube connector API key.',
					'creatorstack-ai'
			  )
			: __(
					'Configure your YouTube channel settings.',
					'creatorstack-ai'
			  ),
		url: youtube.configurationUrl || config.settingsUrl,
		label:
			youtube.configurationLabel ||
			__( 'Go to Settings', 'creatorstack-ai' ),
	};
}
