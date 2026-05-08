<?php
/**
 * AI generation metadata and usage logging.
 *
 * @package CreatorStack_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records lightweight AI generation usage metadata.
 */
class Generation_Logger {

	/**
	 * Post meta key for the latest AI generation metadata.
	 */
	public const META_KEY = '_wttba_ai_generation_meta';

	/**
	 * Option key for the recent usage log.
	 */
	private const OPTION_KEY = 'wttba_generation_log';

	/**
	 * Maximum number of recent generation entries to retain.
	 */
	private const MAX_LOG_ENTRIES = 25;

	/**
	 * Build metadata from a GenerativeAiResult object.
	 *
	 * @param mixed  $result      AI Client result object.
	 * @param string $source_type Source type identifier.
	 * @param string $status      Generation status.
	 * @return array<string, mixed>
	 */
	public static function metadata_from_result( mixed $result, string $source_type, string $status = 'success' ): array {
		$metadata = self::base_metadata( $source_type, $status );

		if ( is_object( $result ) && method_exists( $result, 'getId' ) ) {
			$metadata['result_id'] = sanitize_text_field( (string) $result->getId() );
		}

		if ( is_object( $result ) && method_exists( $result, 'getProviderMetadata' ) ) {
			$provider = $result->getProviderMetadata();

			if ( is_object( $provider ) ) {
				$metadata['provider'] = array_filter(
					array(
						'id'   => method_exists( $provider, 'getId' ) ? sanitize_key( (string) $provider->getId() ) : '',
						'name' => method_exists( $provider, 'getName' ) ? sanitize_text_field( (string) $provider->getName() ) : '',
					)
				);
			}
		}

		if ( is_object( $result ) && method_exists( $result, 'getModelMetadata' ) ) {
			$model = $result->getModelMetadata();

			if ( is_object( $model ) ) {
				$metadata['model'] = array_filter(
					array(
						'id'   => method_exists( $model, 'getId' ) ? sanitize_text_field( (string) $model->getId() ) : '',
						'name' => method_exists( $model, 'getName' ) ? sanitize_text_field( (string) $model->getName() ) : '',
					)
				);
			}
		}

		if ( is_object( $result ) && method_exists( $result, 'getTokenUsage' ) ) {
			$token_usage = $result->getTokenUsage();

			if ( is_object( $token_usage ) && method_exists( $token_usage, 'toArray' ) ) {
				$metadata['token_usage'] = self::sanitize_metadata( $token_usage->toArray() );
			}
		}

		return self::sanitize_metadata( $metadata );
	}

	/**
	 * Build metadata for a failed generation attempt.
	 *
	 * @param string    $source_type Source type identifier.
	 * @param \WP_Error $error       Error object.
	 * @return array<string, mixed>
	 */
	public static function metadata_from_error( string $source_type, \WP_Error $error ): array {
		$metadata = self::base_metadata( $source_type, 'error' );

		$metadata['error'] = array(
			'code'    => sanitize_key( $error->get_error_code() ),
			'message' => sanitize_text_field( $error->get_error_message() ),
		);

		return $metadata;
	}

	/**
	 * Record generation metadata on a post and in the recent usage log.
	 *
	 * @param int|null             $post_id  Optional post ID.
	 * @param array<string, mixed> $metadata Generation metadata.
	 */
	public static function record( ?int $post_id, array $metadata ): void {
		$metadata = self::sanitize_metadata( $metadata );
		$post_id  = absint( $post_id );

		if ( $post_id > 0 ) {
			update_post_meta( $post_id, self::META_KEY, $metadata );
		}

		$entry = $metadata;
		if ( $post_id > 0 ) {
			$entry['post_id'] = $post_id;
		}

		$log = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, self::MAX_LOG_ENTRIES );

		update_option( self::OPTION_KEY, $log, false );
	}

	/**
	 * Get recent generation usage entries.
	 *
	 * @param int $limit Number of entries to return.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_recent_entries( int $limit = 10 ): array {
		$log = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $log ) ) {
			return array();
		}

		return array_slice( $log, 0, max( 1, $limit ) );
	}

	/**
	 * Sanitize nested metadata values.
	 *
	 * @param mixed $value Metadata value.
	 * @return mixed Sanitized metadata value.
	 */
	public static function sanitize_metadata( mixed $value, mixed ...$unused ): mixed {
		if ( is_array( $value ) ) {
			$sanitized = array();

			foreach ( $value as $key => $item ) {
				$sanitized_key               = is_string( $key ) ? sanitize_key( $key ) : absint( $key );
				$sanitized[ $sanitized_key ] = self::sanitize_metadata( $item );
			}

			return $sanitized;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Build the common metadata shape.
	 *
	 * @param string $source_type Source type identifier.
	 * @param string $status      Generation status.
	 * @return array<string, mixed>
	 */
	private static function base_metadata( string $source_type, string $status ): array {
		return array(
			'source_type'  => sanitize_key( $source_type ),
			'user_id'      => get_current_user_id(),
			'generated_at' => current_time( 'mysql' ),
			'status'       => sanitize_key( $status ),
		);
	}
}
