<?php
/**
 * YouTube transcript fetcher.
 *
 * @package WP_Tube_To_Blog_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches video transcripts from YouTube's timedtext endpoint.
 */
class Transcript_Fetcher {

	/**
	 * Maximum transcript length in characters (~6000 words).
	 */
	private const MAX_LENGTH = 30000;

	/**
	 * Fetch the transcript for a YouTube video.
	 *
	 * @param string $video_id The YouTube video ID.
	 * @param string $lang     Preferred language code (e.g., 'en').
	 * @return string|\WP_Error The transcript text or an error.
	 */
	public function fetch( string $video_id, string $lang = 'en' ): string|\WP_Error {
		// Step 1: Get the video page to extract caption track info.
		$page_url = 'https://www.youtube.com/watch?v=' . urlencode( $video_id );
		$response = wp_remote_get(
			$page_url,
			array(
				'timeout'    => 20,
				'user-agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo( 'version' ) . ')',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );

		// Step 2: Extract captions JSON from the page source.
		$captions_data = $this->extract_captions_data( $body );

		if ( is_wp_error( $captions_data ) ) {
			return $captions_data;
		}

		// Step 3: Find the best caption track URL.
		$track_url = $this->find_caption_track( $captions_data, $lang );

		if ( is_wp_error( $track_url ) ) {
			return $track_url;
		}

		// Step 4: Fetch and parse the transcript XML.
		return $this->fetch_transcript_xml( $track_url );
	}

	/**
	 * Extract captions player response data from the YouTube page HTML.
	 *
	 * @param string $html The YouTube page HTML.
	 * @return array|\WP_Error Parsed captions data or error.
	 */
	private function extract_captions_data( string $html ): array|\WP_Error {
		// Look for the captions data in ytInitialPlayerResponse.
		if ( ! preg_match( '/"captions"\s*:\s*(\{.*?"captionTracks".*?\})\s*,\s*"videoDetails"/s', $html, $matches ) ) {
			// Try alternative pattern.
			if ( ! preg_match( '/"captionTracks"\s*:\s*(\[.*?\])/s', $html, $matches ) ) {
				return new \WP_Error(
					'wttba_no_captions',
					__( 'No captions found for this video. The video may not have subtitles available.', 'wp-tube-to-blog-ai' )
				);
			}

			$tracks = json_decode( $matches[1], true );
			if ( ! is_array( $tracks ) ) {
				return new \WP_Error( 'wttba_captions_parse_error', __( 'Failed to parse caption data.', 'wp-tube-to-blog-ai' ) );
			}

			return array( 'captionTracks' => $tracks );
		}

		$data = json_decode( $matches[1], true );
		if ( ! is_array( $data ) || empty( $data['captionTracks'] ) ) {
			return new \WP_Error(
				'wttba_no_captions',
				__( 'No captions found for this video.', 'wp-tube-to-blog-ai' )
			);
		}

		return $data;
	}

	/**
	 * Find the best matching caption track URL.
	 *
	 * @param array  $captions_data The parsed captions data.
	 * @param string $lang          Preferred language code.
	 * @return string|\WP_Error The caption track URL or error.
	 */
	private function find_caption_track( array $captions_data, string $lang ): string|\WP_Error {
		$tracks = $captions_data['captionTracks'] ?? array();

		if ( empty( $tracks ) ) {
			return new \WP_Error( 'wttba_no_tracks', __( 'No caption tracks available.', 'wp-tube-to-blog-ai' ) );
		}

		// Try exact language match first.
		foreach ( $tracks as $track ) {
			if ( isset( $track['languageCode'] ) && $track['languageCode'] === $lang ) {
				return $track['baseUrl'];
			}
		}

		// Try partial match (e.g., 'en' matches 'en-US').
		foreach ( $tracks as $track ) {
			if ( isset( $track['languageCode'] ) && str_starts_with( $track['languageCode'], $lang ) ) {
				return $track['baseUrl'];
			}
		}

		// Fallback to English.
		if ( 'en' !== $lang ) {
			foreach ( $tracks as $track ) {
				if ( isset( $track['languageCode'] ) && str_starts_with( $track['languageCode'], 'en' ) ) {
					return $track['baseUrl'];
				}
			}
		}

		// Fallback to the first available track.
		if ( ! empty( $tracks[0]['baseUrl'] ) ) {
			return $tracks[0]['baseUrl'];
		}

		return new \WP_Error( 'wttba_no_track_url', __( 'Could not find a usable caption track.', 'wp-tube-to-blog-ai' ) );
	}

	/**
	 * Fetch and parse the transcript from the timedtext XML endpoint.
	 *
	 * @param string $url The caption track URL.
	 * @return string|\WP_Error The transcript text or error.
	 */
	private function fetch_transcript_xml( string $url ): string|\WP_Error {
		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$xml_body = wp_remote_retrieve_body( $response );

		// Parse the XML to extract text nodes.
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $xml_body );

		if ( false === $xml ) {
			return new \WP_Error( 'wttba_xml_parse_error', __( 'Failed to parse transcript XML.', 'wp-tube-to-blog-ai' ) );
		}

		$lines = array();
		foreach ( $xml->text as $node ) {
			$text = html_entity_decode( (string) $node, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$text = trim( strip_tags( $text ) );
			if ( '' !== $text ) {
				$lines[] = $text;
			}
		}

		if ( empty( $lines ) ) {
			return new \WP_Error( 'wttba_empty_transcript', __( 'The transcript is empty.', 'wp-tube-to-blog-ai' ) );
		}

		$transcript = implode( ' ', $lines );

		// Truncate if too long to avoid exceeding AI token limits.
		if ( mb_strlen( $transcript ) > self::MAX_LENGTH ) {
			$transcript = mb_substr( $transcript, 0, self::MAX_LENGTH );
			$transcript .= "\n\n[Transcript truncated]";
		}

		return $transcript;
	}
}
