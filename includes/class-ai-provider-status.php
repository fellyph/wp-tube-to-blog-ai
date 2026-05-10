<?php
/**
 * AI provider and Connectors API status helpers.
 *
 * @package CreatorStack_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes AI Client and provider configuration checks.
 */
class AI_Provider_Status {

	/**
	 * Tiny silent WAV data URI for audio-input capability checks.
	 */
	private const DUMMY_AUDIO_DATA_URI = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAIlYAAESsAAACABAAZGF0YQAAAAA=';

	/**
	 * Minimum WordPress version with the Core AI Client and Connectors APIs.
	 */
	private const MINIMUM_WORDPRESS_VERSION = '7.0';

	/**
	 * Check whether AI features are enabled for this site.
	 *
	 * @return bool
	 */
	public static function is_ai_supported_by_site(): bool {
		if ( function_exists( 'wp_supports_ai' ) ) {
			return (bool) wp_supports_ai();
		}

		return true;
	}

	/**
	 * Check whether the WordPress AI Client prompt entry point is available.
	 *
	 * @return bool
	 */
	public static function is_ai_client_available(): bool {
		return self::is_ai_supported_by_site() && function_exists( 'wp_ai_client_prompt' );
	}

	/**
	 * Get the current WordPress version string.
	 *
	 * @return string WordPress version.
	 */
	public static function get_wordpress_version(): string {
		return (string) ( function_exists( 'wp_get_wp_version' ) ? wp_get_wp_version() : get_bloginfo( 'version' ) );
	}

	/**
	 * Check whether the current WordPress version can satisfy the plugin requirement.
	 *
	 * @return bool Whether the current WordPress version is supported.
	 */
	public static function is_supported_wordpress_version(): bool {
		return version_compare( self::get_wordpress_version(), self::MINIMUM_WORDPRESS_VERSION, '>=' );
	}

	/**
	 * Check whether the WordPress 7.0 Connectors API query helpers are available.
	 *
	 * @return bool
	 */
	public static function is_connectors_api_available(): bool {
		return function_exists( 'wp_get_connectors' )
			|| function_exists( 'wp_get_connector' )
			|| function_exists( 'wp_is_connector_registered' );
	}

	/**
	 * Check whether a configured provider can handle text generation.
	 *
	 * @return bool
	 */
	public static function is_text_generation_supported(): bool {
		return self::is_supported_for( 'is_supported_for_text_generation' );
	}

	/**
	 * Check whether a configured provider can generate text from audio input.
	 *
	 * @return bool
	 */
	public static function is_audio_input_generation_supported(): bool {
		if ( ! self::is_ai_client_available() ) {
			return false;
		}

		$prompt = wp_ai_client_prompt( 'Return a one sentence summary of this audio.' );

		if ( is_wp_error( $prompt ) || ! is_object( $prompt ) || ! is_callable( array( $prompt, 'with_file' ) ) ) {
			return false;
		}

		$prompt = $prompt->with_file( self::DUMMY_AUDIO_DATA_URI, 'audio/wav' );

		if ( is_wp_error( $prompt ) || ! is_object( $prompt ) || ! is_callable( array( $prompt, 'is_supported_for_text_generation' ) ) ) {
			return false;
		}

		return true === $prompt->is_supported_for_text_generation();
	}

	/**
	 * Check whether a configured provider can convert text to speech.
	 *
	 * @return bool
	 */
	public static function is_text_to_speech_supported(): bool {
		return self::is_supported_for( 'is_supported_for_text_to_speech_conversion' );
	}

	/**
	 * Get the admin URL where site owners should configure AI provider credentials.
	 *
	 * @return string
	 */
	public static function get_configuration_url(): string {
		if ( self::should_use_connectors_screen() ) {
			return admin_url( 'options-connectors.php' );
		}

		return admin_url( 'options-general.php?page=wp-ai-client' );
	}

	/**
	 * Get registered AI provider connector names when the Connectors API exists.
	 *
	 * @return array<int, array{id: string, name: string}>
	 */
	public static function get_registered_ai_connectors(): array {
		if ( ! function_exists( 'wp_get_connectors' ) ) {
			return array();
		}

		$connectors = wp_get_connectors();

		if ( ! is_array( $connectors ) ) {
			return array();
		}

		$providers = array();

		foreach ( $connectors as $id => $connector ) {
			if ( ! is_array( $connector ) || 'ai_provider' !== ( $connector['type'] ?? '' ) ) {
				continue;
			}

			$providers[] = array(
				'id'   => sanitize_key( (string) $id ),
				'name' => sanitize_text_field( (string) ( $connector['name'] ?? $id ) ),
			);
		}

		return $providers;
	}

	/**
	 * Build localized admin configuration for JavaScript entry points.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_admin_config(): array {
		return array(
			'aiSupportedBySite'        => self::is_ai_supported_by_site(),
			'aiClientAvailable'       => self::is_ai_client_available(),
			'connectorsAvailable'     => self::is_connectors_api_available(),
			'textGenerationSupported' => self::is_text_generation_supported(),
			'audioInputSupported'      => self::is_audio_input_generation_supported(),
			'textToSpeechSupported'    => self::is_text_to_speech_supported(),
			'configurationUrl'        => self::get_configuration_url(),
			'unavailableMessage'      => self::get_unavailable_message(),
			'providers'               => self::get_registered_ai_connectors(),
		);
	}

	/**
	 * Get a human-readable message for the current AI unavailable state.
	 *
	 * @return string
	 */
	public static function get_unavailable_message(): string {
		if ( ! self::is_ai_supported_by_site() ) {
			return __(
				'AI features are disabled for this site.',
				'creatorstack-ai'
			);
		}

		if ( ! self::is_ai_client_available() ) {
			if ( ! self::is_supported_wordpress_version() ) {
				return __(
					'The WordPress AI Client is not available. Upgrade to WordPress 7.0 or newer.',
					'creatorstack-ai'
				);
			}

			return __(
				'The WordPress AI Client is not available in this WordPress build. Make sure the build includes the AI Client APIs and AI features are enabled.',
				'creatorstack-ai'
			);
		}

		if ( self::should_use_connectors_screen() ) {
			return __(
				'No AI provider connector is configured for text generation. Configure an AI provider in Settings > Connectors.',
				'creatorstack-ai'
			);
		}

		return __(
			'No AI provider is configured for text generation. Configure a provider in the WordPress AI Client settings.',
			'creatorstack-ai'
		);
	}

	/**
	 * Get shared REST error data for AI configuration failures.
	 *
	 * @return array<string, string>
	 */
	public static function get_configuration_error_data(): array {
		return array(
			'configuration_url'   => self::get_configuration_url(),
			'configuration_label' => __( 'Configure AI Provider', 'creatorstack-ai' ),
		);
	}

	/**
	 * Decide whether admin links should point to the Core Connectors screen.
	 *
	 * @return bool
	 */
	private static function should_use_connectors_screen(): bool {
		if ( self::is_connectors_api_available() ) {
			return true;
		}

		return self::is_supported_wordpress_version();
	}

	/**
	 * Run a no-cost AI Client support check.
	 *
	 * @param string $method Support check method.
	 * @return bool Whether the configured provider supports the requested feature.
	 */
	private static function is_supported_for( string $method ): bool {
		if ( ! self::is_ai_client_available() ) {
			return false;
		}

		$prompt = wp_ai_client_prompt( 'test' );

		if ( is_wp_error( $prompt ) || ! is_object( $prompt ) || ! is_callable( array( $prompt, $method ) ) ) {
			return false;
		}

		return true === $prompt->{$method}();
	}
}
