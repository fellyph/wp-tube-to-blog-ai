<?php
/**
 * YouTube transcript fetcher.
 *
 * @package CreatorStack_AI
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
		$official_transcript = YouTube_OAuth::fetch_transcript( $video_id, $lang );
		if ( ! is_wp_error( $official_transcript ) ) {
			return $this->truncate_transcript( $official_transcript );
		}

		if ( ! in_array( $official_transcript->get_error_code(), $this->get_oauth_fallback_error_codes(), true ) ) {
			return $official_transcript;
		}

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

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error(
				'wttba_transcript_page_error',
				__( 'Could not load the YouTube video page to find captions. Please try again later.', 'creatorstack-ai' ),
				array( 'status' => 502 )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === trim( $body ) ) {
			return new \WP_Error(
				'wttba_transcript_page_error',
				__( 'YouTube returned an empty video page while looking for captions. Please try again later.', 'creatorstack-ai' ),
				array( 'status' => 502 )
			);
		}

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

		// Step 4: Fetch and parse the transcript data.
		$transcript = $this->fetch_transcript_xml( $track_url );

		if ( is_wp_error( $transcript ) && $this->should_prompt_for_oauth( $official_transcript, $transcript ) ) {
			return $this->build_oauth_required_error();
		}

		return $transcript;
	}

	/**
	 * Extract captions player response data from the YouTube page HTML.
	 *
	 * @param string $html The YouTube page HTML.
	 * @return array|\WP_Error Parsed captions data or error.
	 */
	private function extract_captions_data( string $html ): array|\WP_Error {
		$captions_json = $this->extract_json_value_after_key( $html, '"captions"', '{' );
		if ( null !== $captions_json ) {
			$data = json_decode( $captions_json, true );
			if ( ! is_array( $data ) ) {
				return new \WP_Error(
					'wttba_captions_parse_error',
					__( 'Failed to parse the video caption data. YouTube may have changed its format. Please try again later.', 'creatorstack-ai' )
				);
			}

			$tracks = $this->get_caption_tracks( $data );
			if ( ! empty( $tracks ) ) {
				return array( 'captionTracks' => $tracks );
			}
		}

		$tracks_json = $this->extract_json_value_after_key( $html, '"captionTracks"', '[' );
		if ( null === $tracks_json ) {
			return new \WP_Error(
				'wttba_no_captions',
				__( 'No captions found for this video. The video may not have subtitles available.', 'creatorstack-ai' )
			);
		}

		$tracks = json_decode( $tracks_json, true );
		if ( ! is_array( $tracks ) ) {
			return new \WP_Error(
				'wttba_captions_parse_error',
				__( 'Failed to parse the video caption data. YouTube may have changed its format. Please try again later.', 'creatorstack-ai' )
			);
		}

		if ( empty( $tracks ) ) {
			return new \WP_Error(
				'wttba_no_captions',
				__( 'No captions found for this video.', 'creatorstack-ai' )
			);
		}

		return array( 'captionTracks' => $tracks );
	}

	/**
	 * Extract a balanced JSON object or array after a key in a larger script.
	 *
	 * @param string $source  Source text.
	 * @param string $key     JSON key to find, including quotes.
	 * @param string $opening Opening delimiter, either "{" or "[".
	 * @return string|null JSON value if found.
	 */
	private function extract_json_value_after_key( string $source, string $key, string $opening ): ?string {
		$key_position = strpos( $source, $key );
		if ( false === $key_position ) {
			return null;
		}

		$value_start = strpos( $source, $opening, $key_position + strlen( $key ) );
		if ( false === $value_start ) {
			return null;
		}

		return $this->extract_balanced_json_value( $source, $value_start );
	}

	/**
	 * Extract a JSON object or array while respecting strings and escapes.
	 *
	 * @param string $source Source text.
	 * @param int    $start  Offset of the opening delimiter.
	 * @return string|null JSON value if the delimiters are balanced.
	 */
	private function extract_balanced_json_value( string $source, int $start ): ?string {
		$opening    = $source[ $start ] ?? '';
		$closing    = '{' === $opening ? '}' : ']';
		$depth      = 0;
		$length     = strlen( $source );
		$in_string  = false;
		$is_escaped = false;

		for ( $i = $start; $i < $length; $i++ ) {
			$char = $source[ $i ];

			if ( $in_string ) {
				if ( $is_escaped ) {
					$is_escaped = false;
					continue;
				}

				if ( '\\' === $char ) {
					$is_escaped = true;
					continue;
				}

				if ( '"' === $char ) {
					$in_string = false;
				}

				continue;
			}

			if ( '"' === $char ) {
				$in_string = true;
				continue;
			}

			if ( $opening === $char ) {
				$depth++;
				continue;
			}

			if ( $closing === $char ) {
				$depth--;

				if ( 0 === $depth ) {
					return substr( $source, $start, $i - $start + 1 );
				}
			}
		}

		return null;
	}

	/**
	 * Normalize caption tracks from known YouTube player response shapes.
	 *
	 * YouTube exposes tracks either as a direct captionTracks array or nested
	 * under playerCaptionsTracklistRenderer, depending on where the JSON was
	 * extracted from in the page.
	 *
	 * @param array $data Parsed captions/player response data.
	 * @return array<int, array<string, mixed>> Caption track arrays.
	 */
	private function get_caption_tracks( array $data ): array {
		if ( isset( $data['captionTracks'] ) && is_array( $data['captionTracks'] ) ) {
			return $data['captionTracks'];
		}

		if (
			isset( $data['playerCaptionsTracklistRenderer']['captionTracks'] )
			&& is_array( $data['playerCaptionsTracklistRenderer']['captionTracks'] )
		) {
			return $data['playerCaptionsTracklistRenderer']['captionTracks'];
		}

		if (
			isset( $data['captions']['playerCaptionsTracklistRenderer']['captionTracks'] )
			&& is_array( $data['captions']['playerCaptionsTracklistRenderer']['captionTracks'] )
		) {
			return $data['captions']['playerCaptionsTracklistRenderer']['captionTracks'];
		}

		return array();
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
			return new \WP_Error( 'wttba_no_tracks', __( 'No caption tracks available.', 'creatorstack-ai' ) );
		}

		// Try exact language match first.
		foreach ( $tracks as $track ) {
			if ( ! is_array( $track ) ) {
				continue;
			}

			if ( ! empty( $track['baseUrl'] ) && isset( $track['languageCode'] ) && $track['languageCode'] === $lang ) {
				return $track['baseUrl'];
			}
		}

		// Try partial match (e.g., 'en' matches 'en-US').
		foreach ( $tracks as $track ) {
			if ( ! is_array( $track ) ) {
				continue;
			}

			if ( ! empty( $track['baseUrl'] ) && isset( $track['languageCode'] ) && str_starts_with( $track['languageCode'], $lang ) ) {
				return $track['baseUrl'];
			}
		}

		// Fallback to English.
		if ( 'en' !== $lang ) {
			foreach ( $tracks as $track ) {
				if ( ! is_array( $track ) ) {
					continue;
				}

				if ( ! empty( $track['baseUrl'] ) && isset( $track['languageCode'] ) && str_starts_with( $track['languageCode'], 'en' ) ) {
					return $track['baseUrl'];
				}
			}
		}

		// Fallback to the first available track.
		foreach ( $tracks as $track ) {
			if ( is_array( $track ) && ! empty( $track['baseUrl'] ) ) {
				return $track['baseUrl'];
			}
		}

		return new \WP_Error( 'wttba_no_track_url', __( 'Could not find a usable caption track.', 'creatorstack-ai' ) );
	}

	/**
	 * Fetch and parse the transcript from the timedtext endpoint.
	 *
	 * @param string $url The caption track URL.
	 * @return string|\WP_Error The transcript text or error.
	 */
	private function fetch_transcript_xml( string $url ): string|\WP_Error {
		$formats       = array( 'json3', 'srv3', '' );
		$last_error    = null;
		$empty_response = false;

		foreach ( $formats as $format ) {
			$request_url = '' === $format ? $url : add_query_arg( 'fmt', $format, $url );
			$response    = wp_remote_get(
				$request_url,
				array(
					'timeout'    => 20,
					'user-agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo( 'version' ) . ')',
					'headers'    => array(
						'Accept' => 'application/json, text/xml, application/xml, text/vtt, text/plain, */*',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( 429 === $code ) {
				return new \WP_Error(
					'wttba_transcript_rate_limited',
					__( 'YouTube temporarily blocked transcript requests. Please wait and try again later.', 'creatorstack-ai' ),
					array( 'status' => 429 )
				);
			}

			if ( $code < 200 || $code >= 300 ) {
				$last_error = new \WP_Error(
					'wttba_transcript_page_error',
					__( 'Could not load the YouTube transcript data. Please try again later.', 'creatorstack-ai' ),
					array( 'status' => 502 )
				);
				continue;
			}

			$body = wp_remote_retrieve_body( $response );
			if ( '' === trim( $body ) ) {
				$empty_response = true;
				continue;
			}

			$transcript = 'json3' === $format
				? $this->parse_json_transcript( $body )
				: $this->parse_xml_transcript( $body );

			if ( is_wp_error( $transcript ) ) {
				$last_error = $transcript;
				continue;
			}

			return $this->truncate_transcript( $transcript );
		}

		if ( $empty_response ) {
			return new \WP_Error(
				'wttba_empty_transcript',
				__( 'YouTube returned an empty transcript for this caption track. Try selecting a different video or try again later.', 'creatorstack-ai' )
			);
		}

		return $last_error instanceof \WP_Error
			? $last_error
			: new \WP_Error(
				'wttba_xml_parse_error',
				__( 'Failed to parse the transcript data. Please try again or choose a different video.', 'creatorstack-ai' )
			);
	}

	/**
	 * Parse YouTube json3 transcript data.
	 *
	 * @param string $body Raw response body.
	 * @return string|\WP_Error Transcript text or parse error.
	 */
	private function parse_json_transcript( string $body ): string|\WP_Error {
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['events'] ) || ! is_array( $data['events'] ) ) {
			return $this->transcript_parse_error( 'JSON' );
		}

		$lines = array();
		foreach ( $data['events'] as $event ) {
			if ( empty( $event['segs'] ) || ! is_array( $event['segs'] ) ) {
				continue;
			}

			$text = '';
			foreach ( $event['segs'] as $segment ) {
				if ( is_array( $segment ) && isset( $segment['utf8'] ) ) {
					$text .= (string) $segment['utf8'];
				}
			}

			$text = $this->normalize_transcript_line( $text );
			if ( '' !== $text ) {
				$lines[] = $text;
			}
		}

		return $this->join_transcript_lines( $lines );
	}

	/**
	 * Parse XML transcript data.
	 *
	 * @param string $body Raw response body.
	 * @return string|\WP_Error Transcript text or parse error.
	 */
	private function parse_xml_transcript( string $body ): string|\WP_Error {
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $body );

		if ( false === $xml ) {
			return $this->transcript_parse_error( 'XML' );
		}

		$nodes = $xml->xpath( '//text' );
		if ( empty( $nodes ) ) {
			$nodes = $xml->xpath( '//p' );
		}
		if ( empty( $nodes ) ) {
			$nodes = $xml->xpath( '//s' );
		}

		$lines = array();
		foreach ( $nodes ?: array() as $node ) {
			$text = $this->normalize_transcript_line( (string) $node );
			if ( '' !== $text ) {
				$lines[] = $text;
			}
		}

		return $this->join_transcript_lines( $lines );
	}

	/**
	 * Normalize one transcript line.
	 *
	 * @param string $text Raw transcript text.
	 * @return string Normalized text.
	 */
	private function normalize_transcript_line( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( wp_strip_all_tags( $text ) );

		return preg_replace( '/\s+/', ' ', $text ) ?? $text;
	}

	/**
	 * Join transcript lines or return an empty-transcript error.
	 *
	 * @param array<int, string> $lines Transcript lines.
	 * @return string|\WP_Error Transcript text or empty error.
	 */
	private function join_transcript_lines( array $lines ): string|\WP_Error {
		if ( empty( $lines ) ) {
			return new \WP_Error(
				'wttba_empty_transcript',
				__( 'The transcript for this video appears to be empty. Try selecting a video with captions enabled.', 'creatorstack-ai' )
			);
		}

		return implode( ' ', $lines );
	}

	/**
	 * Truncate transcript text to the supported maximum length.
	 *
	 * @param string $transcript Transcript text.
	 * @return string Truncated transcript.
	 */
	private function truncate_transcript( string $transcript ): string {
		$transcript = trim( $transcript );

		if ( mb_strlen( $transcript ) > self::MAX_LENGTH ) {
			$transcript = mb_substr( $transcript, 0, self::MAX_LENGTH );
			$transcript .= "\n\n[Transcript truncated]";
		}

		return $transcript;
	}

	/**
	 * Build a transcript parse error.
	 *
	 * @param string $format Response format.
	 * @return \WP_Error Parse error.
	 */
	private function transcript_parse_error( string $format ): \WP_Error {
		return new \WP_Error(
			'wttba_xml_parse_error',
			__( 'Failed to parse the transcript data. Please try again or choose a different video.', 'creatorstack-ai' )
		);
	}

	/**
	 * OAuth errors that should still allow the public timedtext fallback.
	 *
	 * @return array<int, string>
	 */
	private function get_oauth_fallback_error_codes(): array {
		return array(
			'wttba_youtube_oauth_missing_credentials',
			'wttba_youtube_caption_not_found',
		);
	}

	/**
	 * Determine whether a failed public transcript fallback should point admins to OAuth.
	 *
	 * @param \WP_Error $oauth_error    Error from the official Captions API path.
	 * @param \WP_Error $fallback_error Error from the public timedtext fallback.
	 * @return bool
	 */
	private function should_prompt_for_oauth( \WP_Error $oauth_error, \WP_Error $fallback_error ): bool {
		if ( 'wttba_youtube_oauth_missing_credentials' !== $oauth_error->get_error_code() ) {
			return false;
		}

		return in_array(
			$fallback_error->get_error_code(),
			array(
				'wttba_empty_transcript',
				'wttba_no_captions',
				'wttba_no_tracks',
				'wttba_no_track_url',
				'wttba_xml_parse_error',
			),
			true
		);
	}

	/**
	 * Build a configuration error for channel-owned videos that need official caption access.
	 *
	 * @return \WP_Error Error with REST response metadata.
	 */
	private function build_oauth_required_error(): \WP_Error {
		return new \WP_Error(
			'wttba_youtube_oauth_required_for_captions',
			__( 'Connect YouTube OAuth to read captions through the official YouTube Captions API. The public transcript fallback returned an empty transcript for this video.', 'creatorstack-ai' ),
			array(
				'status'              => 422,
				'error_category'      => 'configuration',
				'configuration_url'   => admin_url( 'options-general.php?page=wttba-settings' ),
				'configuration_label' => __( 'Connect YouTube OAuth', 'creatorstack-ai' ),
			)
		);
	}
}
