<?php
/**
 * YouTube Data API v3 wrapper.
 *
 * @package WP_Tube_To_Blog_AI
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
		return (string) get_option( 'wttba_youtube_api_key', '' );
	}

	/**
	 * Get the configured Channel ID.
	 *
	 * @return string
	 */
	private function get_channel_id(): string {
		return (string) get_option( 'wttba_youtube_channel_id', '' );
	}

	/**
	 * Check if the API is configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->get_api_key() && '' !== $this->get_channel_id();
	}

	/**
	 * Fetch videos from the connected YouTube channel.
	 *
	 * @param string $page_token Optional pagination token.
	 * @param int    $max_results Number of results per page (max 50).
	 * @return array{items: array, nextPageToken?: string, totalResults: int}|\WP_Error
	 */
	public function get_videos( string $page_token = '', int $max_results = 5 ): array|\WP_Error {
		if ( ! $this->is_configured() ) {
			return new \WP_Error(
				'wttba_not_configured',
				__( 'YouTube API key and Channel ID are required. Please configure them in Settings > Tube-to-Blog AI.', 'wp-tube-to-blog-ai' )
			);
		}

		$cache_key = 'wttba_videos_' . md5( $page_token . $max_results );
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
			$message = $body['error']['message'] ?? __( 'Unknown YouTube API error.', 'wp-tube-to-blog-ai' );
			return new \WP_Error( 'wttba_youtube_api_error', $message, array( 'status' => $code ) );
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
		$cache_key = 'wttba_video_' . $video_id;
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
			$message = $body['error']['message'] ?? __( 'Unknown YouTube API error.', 'wp-tube-to-blog-ai' );
			return new \WP_Error( 'wttba_youtube_api_error', $message, array( 'status' => $code ) );
		}

		if ( empty( $body['items'] ) ) {
			return new \WP_Error(
				'wttba_video_not_found',
				__( 'The video could not be found. Please check the video ID and ensure it is publicly accessible.', 'wp-tube-to-blog-ai' )
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
