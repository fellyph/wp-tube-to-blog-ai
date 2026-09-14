<?php
/**
 * YouTube Data API v3 wrapper.
 *
 * @package CreatorStack_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps the YouTube Data API v3 for fetching channel videos.
 */
class YouTube_API {

	/**
	 * YouTube Data API v3 base URL.
	 */
	private const API_BASE = 'https://www.googleapis.com/youtube/v3';

	/**
	 * Transient cache duration in seconds (15 minutes).
	 */
	private const CACHE_TTL = 900;

	/**
	 * Get the configured API key.
	 *
	 * @return string
	 */
	private function get_api_key(): string {
		return YouTube_Connector::get_api_key();
	}

	/**
	 * Get the configured Channel ID.
	 *
	 * @return string
	 */
	private function get_channel_id(): string {
		return YouTube_Connector::get_channel_id();
	}

	/**
	 * Check if the API is configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return YouTube_Connector::is_configured();
	}

	/**
	 * Build a standardized configuration error.
	 *
	 * @return \WP_Error
	 */
	private function get_configuration_error(): \WP_Error {
		return new \WP_Error(
			'wttba_not_configured',
			__( 'A valid YouTube Data API key and Channel ID are required before CreatorStack AI can load YouTube videos.', 'creatorstack-ai' ),
			YouTube_Connector::get_configuration_error_data()
		);
	}

	/**
	 * Fetch videos from the connected YouTube channel.
	 *
	 * @param string $page_token Optional pagination token.
	 * @param int    $max_results Number of results per page (max 50).
	 * @return array{items: array, nextPageToken?: string, totalResults: int}|\WP_Error
	 */
	public function get_videos( ?string $page_token = '', ?int $max_results = 5 ): array|\WP_Error {
		$page_token  = (string) $page_token;
		$max_results = $max_results ? absint( $max_results ) : 5;

		if ( ! $this->is_configured() ) {
			return $this->get_configuration_error();
		}

		$cache_key = 'wttba_videos_' . md5( $this->get_channel_id() . $page_token . $max_results );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$args = array(
			'part'       => 'snippet',
			'channelId'  => $this->get_channel_id(),
			'maxResults' => min( $max_results, 50 ),
			'order'      => 'date',
			'type'       => 'video',
			'key'        => $this->get_api_key(),
		);

		if ( '' !== $page_token ) {
			$args['pageToken'] = $page_token;
		}

		$url      = add_query_arg( $args, self::API_BASE . '/search' );
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			return $this->get_api_error( (int) $code, $body );
		}

		$result = array(
			'items'        => $this->format_video_items( $body['items'] ?? array() ),
			'totalResults' => $body['pageInfo']['totalResults'] ?? 0,
		);

		if ( ! empty( $body['nextPageToken'] ) ) {
			$result['nextPageToken'] = $body['nextPageToken'];
		}

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Fetch a single video's details.
	 *
	 * @param string $video_id The YouTube video ID.
	 * @return array{id: string, title: string, description: string, thumbnail: string, publishedAt: string}|\WP_Error
	 */
	public function get_video( string $video_id ): array|\WP_Error {
		if ( ! $this->is_configured() ) {
			return $this->get_configuration_error();
		}

		$cache_key = 'wttba_video_' . md5( $this->get_channel_id() . $video_id );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$url = add_query_arg(
			array(
				'part' => 'snippet',
				'id'   => $video_id,
				'key'  => $this->get_api_key(),
			),
			self::API_BASE . '/videos'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			return $this->get_api_error( (int) $code, $body );
		}

		if ( empty( $body['items'] ) ) {
			return new \WP_Error(
				'wttba_video_not_found',
				__( 'The video could not be found. Please check the video ID and ensure it is publicly accessible.', 'creatorstack-ai' )
			);
		}

		$item   = $body['items'][0];
		$result = array(
			'id'          => $video_id,
			'title'       => $item['snippet']['title'] ?? '',
			'description' => $item['snippet']['description'] ?? '',
			'thumbnail'   => $item['snippet']['thumbnails']['high']['url'] ?? $item['snippet']['thumbnails']['default']['url'] ?? '',
			'publishedAt' => $item['snippet']['publishedAt'] ?? '',
		);

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Extract the machine-readable reason from a Google API error payload.
	 *
	 * Modern responses expose it in error.details[].reason (google.rpc.ErrorInfo);
	 * legacy responses only expose error.errors[].reason.
	 *
	 * @param array $body Decoded response body.
	 * @return string Reason identifier, or an empty string when absent.
	 */
	private function get_error_reason( array $body ): string {
		foreach ( (array) ( $body['error']['details'] ?? array() ) as $detail ) {
			if ( ! empty( $detail['reason'] ) ) {
				return (string) $detail['reason'];
			}
		}

		foreach ( (array) ( $body['error']['errors'] ?? array() ) as $error ) {
			if ( ! empty( $error['reason'] ) ) {
				return (string) $error['reason'];
			}
		}

		return '';
	}

	/**
	 * Map known Google API error reasons to actionable guidance.
	 *
	 * The `transient` flag tells the UI whether retrying can succeed. Configuration
	 * failures never resolve on their own, so they must not be reported as a
	 * temporary problem with an external service.
	 *
	 * @return array<string, array{message: string, url: string, transient: bool}>
	 */
	private function get_error_hints(): array {
		$credentials_url = 'https://console.cloud.google.com/apis/credentials';

		return array(
			'API_KEY_SERVICE_BLOCKED'         => array(
				'message'   => __( 'Your Google API key is blocked for the YouTube Data API v3. In Google Cloud Console, open APIs & Services > Credentials, edit the key, and under "API restrictions" allow "YouTube Data API v3". Keys created in Google AI Studio are usually restricted to the Generative Language API only. Changes take a minute or two to apply.', 'creatorstack-ai' ),
				'url'       => $credentials_url,
				'transient' => false,
			),
			'SERVICE_DISABLED'                => array(
				'message'   => __( 'The YouTube Data API v3 is not enabled for this Google Cloud project. Enable it, then wait a few minutes before retrying.', 'creatorstack-ai' ),
				'url'       => 'https://console.cloud.google.com/apis/library/youtube.googleapis.com',
				'transient' => false,
			),
			'accessNotConfigured'             => array(
				'message'   => __( 'The YouTube Data API v3 is not enabled for this Google Cloud project. Enable it, then wait a few minutes before retrying.', 'creatorstack-ai' ),
				'url'       => 'https://console.cloud.google.com/apis/library/youtube.googleapis.com',
				'transient' => false,
			),
			'API_KEY_HTTP_REFERRER_BLOCKED'   => array(
				'message'   => __( 'Your Google API key is restricted to HTTP referrers, which cannot work here: WordPress calls the YouTube API from the server and sends no referrer. Edit the key and set "Application restrictions" to None or to your server IP address.', 'creatorstack-ai' ),
				'url'       => $credentials_url,
				'transient' => false,
			),
			'API_KEY_IP_ADDRESS_BLOCKED'      => array(
				'message'   => __( 'Your Google API key rejects requests from this server IP address. Edit the key and add the IP to "Application restrictions", or set the restriction to None.', 'creatorstack-ai' ),
				'url'       => $credentials_url,
				'transient' => false,
			),
			'API_KEY_INVALID'                 => array(
				'message'   => __( 'Google rejected this API key. Confirm you copied the whole key, that it was not deleted or regenerated, and that it belongs to the project where the YouTube Data API v3 is enabled.', 'creatorstack-ai' ),
				'url'       => $credentials_url,
				'transient' => false,
			),
			'keyInvalid'                      => array(
				'message'   => __( 'Google rejected this API key. Confirm you copied the whole key, that it was not deleted or regenerated, and that it belongs to the project where the YouTube Data API v3 is enabled.', 'creatorstack-ai' ),
				'url'       => $credentials_url,
				'transient' => false,
			),
			'quotaExceeded'                   => array(
				'message'   => __( 'This project used up its daily YouTube Data API quota. The quota resets at midnight Pacific Time, or you can request an increase in Google Cloud Console.', 'creatorstack-ai' ),
				'url'       => 'https://console.cloud.google.com/apis/api/youtube.googleapis.com/quotas',
				'transient' => false,
			),
			'dailyLimitExceeded'              => array(
				'message'   => __( 'This project used up its daily YouTube Data API quota. The quota resets at midnight Pacific Time, or you can request an increase in Google Cloud Console.', 'creatorstack-ai' ),
				'url'       => 'https://console.cloud.google.com/apis/api/youtube.googleapis.com/quotas',
				'transient' => false,
			),
			'rateLimitExceeded'               => array(
				'message'   => __( 'Too many requests were sent to the YouTube Data API in a short window. Wait a moment and try again.', 'creatorstack-ai' ),
				'url'       => '',
				'transient' => true,
			),
			'userRateLimitExceeded'           => array(
				'message'   => __( 'Too many requests were sent to the YouTube Data API in a short window. Wait a moment and try again.', 'creatorstack-ai' ),
				'url'       => '',
				'transient' => true,
			),
			'channelNotFound'                 => array(
				'message'   => __( 'YouTube could not find the configured channel. Check the Channel ID in the plugin settings — it should start with "UC" and is not the same as your @handle.', 'creatorstack-ai' ),
				'url'       => '',
				'transient' => false,
			),
		);
	}

	/**
	 * Convert a non-200 YouTube Data API response into an actionable WP_Error.
	 *
	 * @param int   $code HTTP status code.
	 * @param mixed $body Decoded response body.
	 * @return \WP_Error
	 */
	private function get_api_error( int $code, $body ): \WP_Error {
		$body           = is_array( $body ) ? $body : array();
		$reason         = $this->get_error_reason( $body );
		$remote_message = (string) ( $body['error']['message'] ?? '' );
		$hints          = $this->get_error_hints();

		if ( isset( $hints[ $reason ] ) ) {
			$hint    = $hints[ $reason ];
			$message = $hint['message'];
			$url     = $hint['url'];
			$is_temp = $hint['transient'];
		} else {
			$url     = '';
			$is_temp = $code >= 500 || 429 === $code;
			$reason  = '' !== $reason ? $reason : 'unknown';

			if ( '' !== $remote_message ) {
				$message = sprintf(
					/* translators: %s: error message returned by the YouTube Data API. */
					__( 'The YouTube Data API returned an error: %s', 'creatorstack-ai' ),
					$remote_message
				);
			} else {
				$message = sprintf(
					/* translators: %d: HTTP status code. */
					__( 'The YouTube Data API request failed with HTTP status %d.', 'creatorstack-ai' ),
					$code
				);
			}
		}

		// A 403 is never a transient upstream hiccup: it is always credentials,
		// key restrictions, or quota, and retrying cannot fix it.
		if ( 403 === $code || 400 === $code ) {
			$is_temp = false;
		}

		if ( $is_temp ) {
			$category = 429 === $code ? 'rate_limit' : 'upstream';
		} elseif ( $code >= 500 ) {
			$category = 'upstream';
		} else {
			$category = 'configuration';
		}

		$data = array(
			'status'         => $code,
			'error_category' => $category,
			'reason'         => $reason,
			'remote_message' => $remote_message,
		);

		if ( 'configuration' === $category ) {
			if ( '' !== $url ) {
				$data['configuration_url']   = $url;
				$data['configuration_label'] = __( 'Open Google Cloud Console', 'creatorstack-ai' );
			} else {
				$data['configuration_url']   = YouTube_Connector::get_configuration_url();
				$data['configuration_label'] = __( 'Update YouTube settings', 'creatorstack-ai' );
			}
		}

		return new \WP_Error( 'wttba_youtube_api_error', $message, $data );
	}

	/**
	 * Format raw YouTube search result items.
	 *
	 * @param array $items Raw items from YouTube API.
	 * @return array Formatted items.
	 */
	private function format_video_items( array $items ): array {
		$formatted = array();

		foreach ( $items as $item ) {
			$video_id = $item['id']['videoId'] ?? '';
			if ( '' === $video_id ) {
				continue;
			}

			$formatted[] = array(
				'id'          => $video_id,
				'title'       => $item['snippet']['title'] ?? '',
				'description' => $item['snippet']['description'] ?? '',
				'thumbnail'   => $item['snippet']['thumbnails']['high']['url'] ?? $item['snippet']['thumbnails']['default']['url'] ?? '',
				'publishedAt' => $item['snippet']['publishedAt'] ?? '',
			);
		}

		return $formatted;
	}
}
