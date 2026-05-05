<?php
/**
 * REST API controller.
 *
 * @package WP_Tube_To_Blog_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles REST API routes for the plugin.
 */
class REST_Controller {

	/**
	 * REST namespace.
	 */
	private const NAMESPACE = 'wttba/v1';

	/**
	 * Register all REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/capabilities',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_capabilities' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/ai/test',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_ai_connection' ),
				'permission_callback' => array( $this, 'can_manage_options' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/videos',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_videos' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
				'args'                => array(
					'page_token'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
					'max_results' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return $value >= 1 && $value <= 50;
						},
						'default'           => 5,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/videos/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_video' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
				'args'                => array(
					'id' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return preg_match( '/^[a-zA-Z0-9_-]+$/', $value );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview_post' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
				'args'                => array(
					'video_id' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return preg_match( '/^[a-zA-Z0-9_-]+$/', $value );
						},
					),
					'language' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return array_key_exists( $value, Settings::LANGUAGES );
						},
						'default'           => '',
					),
					'persona'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
						'default'           => '',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/save-draft',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_draft' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
				'args'                => array(
					'video_id' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return preg_match( '/^[a-zA-Z0-9_-]+$/', $value );
						},
					),
					'title'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'content'  => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'wp_kses_post',
					),
					'ai_metadata' => array(
						'type'              => 'object',
						'sanitize_callback' => array( $this, 'sanitize_metadata_arg' ),
						'default'           => array(),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/audio-post/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview_audio_post' ),
				'permission_callback' => array( $this, 'can_preview_audio_post' ),
				'args'                => array(
					'post_id'       => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'attachment_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'language'      => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return array_key_exists( $value, Settings::LANGUAGES );
						},
						'default'           => '',
					),
					'persona'       => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
						'default'           => '',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>[\d]+)/audio',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_post_audio' ),
				'permission_callback' => array( $this, 'can_generate_post_audio' ),
				'args'                => array(
					'id'              => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'voice'           => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'default'           => '',
					),
					'overwrite_block' => array(
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
						'default'           => true,
					),
				),
			)
		);
	}

	/**
	 * HTTP status code mapping for known error codes.
	 */
	private const ERROR_STATUS_MAP = array(
		'wttba_not_configured'      => 422,
		'wttba_ai_client_missing'   => 422,
		'wttba_ai_not_supported'    => 422,
		'wttba_rate_limited'        => 429,
		'wttba_invalid_video_id'    => 400,
		'wttba_video_not_found'     => 404,
		'wttba_no_captions'         => 404,
		'wttba_no_tracks'           => 404,
		'wttba_no_track_url'        => 404,
		'wttba_empty_transcript'    => 404,
		'wttba_transcript_page_error' => 502,
		'wttba_transcript_rate_limited' => 429,
		'wttba_captions_parse_error' => 502,
		'wttba_xml_parse_error'     => 502,
		'wttba_youtube_oauth_missing_credentials' => 422,
		'wttba_youtube_oauth_not_connected' => 422,
		'wttba_youtube_oauth_required_for_captions' => 422,
		'wttba_youtube_oauth_failed' => 422,
		'wttba_youtube_oauth_forbidden' => 403,
		'wttba_youtube_caption_not_found' => 404,
		'wttba_youtube_caption_download_failed' => 502,
		'wttba_ai_parse_error'      => 502,
		'wttba_youtube_api_error'   => 502,
		'wttba_ai_disabled'         => 422,
		'wttba_audio_input_not_supported' => 422,
		'wttba_tts_not_supported'   => 422,
		'wttba_invalid_audio_attachment' => 400,
		'wttba_audio_file_missing'  => 404,
		'wttba_audio_too_large'     => 400,
		'wttba_post_not_found'      => 404,
		'wttba_empty_post_content'  => 400,
		'wttba_audio_generation_failed' => 502,
		'wttba_audio_save_failed'   => 500,
	);

	/**
	 * Error category mapping for known error codes.
	 */
	private const ERROR_CATEGORY_MAP = array(
		'wttba_not_configured'      => 'configuration',
		'wttba_ai_client_missing'   => 'configuration',
		'wttba_ai_not_supported'    => 'configuration',
		'wttba_rate_limited'        => 'rate_limit',
		'wttba_invalid_video_id'    => 'validation',
		'wttba_video_not_found'     => 'not_found',
		'wttba_no_captions'         => 'not_found',
		'wttba_no_tracks'           => 'not_found',
		'wttba_no_track_url'        => 'not_found',
		'wttba_empty_transcript'    => 'not_found',
		'wttba_transcript_page_error' => 'upstream',
		'wttba_transcript_rate_limited' => 'rate_limit',
		'wttba_captions_parse_error' => 'upstream',
		'wttba_xml_parse_error'     => 'upstream',
		'wttba_youtube_oauth_missing_credentials' => 'configuration',
		'wttba_youtube_oauth_not_connected' => 'configuration',
		'wttba_youtube_oauth_required_for_captions' => 'configuration',
		'wttba_youtube_oauth_failed' => 'configuration',
		'wttba_youtube_oauth_forbidden' => 'configuration',
		'wttba_youtube_caption_not_found' => 'not_found',
		'wttba_youtube_caption_download_failed' => 'upstream',
		'wttba_ai_parse_error'      => 'upstream',
		'wttba_youtube_api_error'   => 'upstream',
		'wttba_ai_disabled'         => 'configuration',
		'wttba_audio_input_not_supported' => 'configuration',
		'wttba_tts_not_supported'   => 'configuration',
		'wttba_invalid_audio_attachment' => 'validation',
		'wttba_audio_file_missing'  => 'not_found',
		'wttba_audio_too_large'     => 'validation',
		'wttba_post_not_found'      => 'not_found',
		'wttba_empty_post_content'  => 'validation',
		'wttba_audio_generation_failed' => 'upstream',
		'wttba_audio_save_failed'   => 'internal',
	);

	/**
	 * Permission check: current user can edit posts.
	 *
	 * @return bool
	 */
	public function can_edit_posts(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission check: current user can manage plugin settings.
	 *
	 * @return bool
	 */
	public function can_manage_options(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for audio-to-post previews.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function can_preview_audio_post( \WP_REST_Request $request ): bool {
		$post_id       = absint( $request->get_param( 'post_id' ) );
		$attachment_id = absint( $request->get_param( 'attachment_id' ) );

		return $post_id > 0
			&& $attachment_id > 0
			&& current_user_can( 'edit_post', $post_id )
			&& current_user_can( 'edit_post', $attachment_id );
	}

	/**
	 * Permission check for post-to-audio generation.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function can_generate_post_audio( \WP_REST_Request $request ): bool {
		$post_id = absint( $request->get_param( 'id' ) );

		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Sanitize nested AI metadata.
	 *
	 * @param mixed $value Metadata argument.
	 * @return array<string, mixed>
	 */
	public function sanitize_metadata_arg( mixed $value, mixed ...$unused ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return Generation_Logger::sanitize_metadata( $value );
	}

	/**
	 * Enrich a WP_Error with HTTP status code and error category for the REST response.
	 *
	 * @param \WP_Error $error The original error.
	 * @return \WP_Error The enriched error.
	 */
	private function prepare_error_response( \WP_Error $error ): \WP_Error {
		$code = $error->get_error_code();
		$data = $error->get_error_data( $code );

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$needs_data_update = false;

		// Preserve existing status if already set (e.g., YouTube API errors).
		if ( empty( $data['status'] ) ) {
			$data['status']     = self::ERROR_STATUS_MAP[ $code ] ?? 500;
			$needs_data_update = true;
		}

		if ( empty( $data['error_category'] ) ) {
			$data['error_category'] = self::ERROR_CATEGORY_MAP[ $code ] ?? 'internal';
			$needs_data_update      = true;
		}

		if ( $needs_data_update ) {
			$error->add_data( $data, $code );
		}

		return $error;
	}

	/**
	 * GET /capabilities - Current AI and source limits.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_capabilities(): \WP_REST_Response {
		$content_generator = new Content_Generator();

		return new \WP_REST_Response(
			array(
				'aiSupportedBySite'        => AI_Provider_Status::is_ai_supported_by_site(),
				'aiClientAvailable'       => AI_Provider_Status::is_ai_client_available(),
				'connectorsAvailable'     => AI_Provider_Status::is_connectors_api_available(),
				'textGenerationSupported' => AI_Provider_Status::is_text_generation_supported(),
				'audioInputSupported'      => AI_Provider_Status::is_audio_input_generation_supported(),
				'textToSpeechSupported'    => AI_Provider_Status::is_text_to_speech_supported(),
				'configurationUrl'        => AI_Provider_Status::get_configuration_url(),
				'configurationLabel'      => __( 'Configure AI Provider', 'wp-tube-to-blog-ai' ),
				'unavailableMessage'      => AI_Provider_Status::get_unavailable_message(),
				'providers'               => AI_Provider_Status::get_registered_ai_connectors(),
				'audioUpload'             => array(
					'maxBytes'          => $content_generator->get_max_audio_bytes(),
					'allowedExtensions' => Content_Generator::ALLOWED_AUDIO_EXTENSIONS,
				),
			),
			200
		);
	}

	/**
	 * POST /ai/test - Verify the configured AI provider with a tiny generation.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function test_ai_connection(): \WP_REST_Response|\WP_Error {
		$generator = new Content_Generator();
		$result    = $generator->test_text_generation();

		if ( is_wp_error( $result ) ) {
			Generation_Logger::record( null, Generation_Logger::metadata_from_error( 'ai_connection_test', $result ) );
			return $this->prepare_error_response( $result );
		}

		Generation_Logger::record( null, $result['ai_metadata'] );

		return new \WP_REST_Response(
			array(
				'message'     => __( 'AI provider connection test succeeded.', 'wp-tube-to-blog-ai' ),
				'summary'     => $result['summary'],
				'ai_metadata' => $result['ai_metadata'],
				'localhost'   => Settings::get_localhost_status(),
			),
			200
		);
	}

	/**
	 * GET /videos — List channel videos.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_videos( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$youtube = new YouTube_API();
		$result  = $youtube->get_videos(
			(string) ( $request->get_param( 'page_token' ) ?? '' ),
			absint( $request->get_param( 'max_results' ) ?: 5 )
		);

		if ( is_wp_error( $result ) ) {
			return $this->prepare_error_response( $result );
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * GET /videos/{id} — Single video details.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_video( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$youtube = new YouTube_API();
		$result  = $youtube->get_video( $request->get_param( 'id' ) );

		if ( is_wp_error( $result ) ) {
			return $this->prepare_error_response( $result );
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /preview — Generate a blog post preview without creating a draft.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function preview_post( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$video_id = (string) ( $request->get_param( 'video_id' ) ?? '' );
		$language = (string) ( $request->get_param( 'language' ) ?? '' );
		$persona  = (string) ( $request->get_param( 'persona' ) ?? '' );

		if ( '' === $video_id || ! preg_match( '/^[a-zA-Z0-9_-]+$/', $video_id ) ) {
			return $this->prepare_error_response(
				new \WP_Error(
					'wttba_invalid_video_id',
					__( 'A valid YouTube video ID is required.', 'wp-tube-to-blog-ai' )
				)
			);
		}

		// Default to the saved setting if no language provided.
		if ( empty( $language ) ) {
			$language = get_option( 'wttba_default_language', 'en' );
		}

		$generator = new Post_Generator();
		$result    = $generator->preview( $video_id, $language, $persona );

		if ( is_wp_error( $result ) ) {
			return $this->prepare_error_response( $result );
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /save-draft — Save AI-generated content as a WordPress draft.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_draft( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$video_id = (string) ( $request->get_param( 'video_id' ) ?? '' );
		$title    = (string) ( $request->get_param( 'title' ) ?? '' );
		$content  = (string) ( $request->get_param( 'content' ) ?? '' );
		$metadata = $request->get_param( 'ai_metadata' );

		$generator = new Post_Generator();
		$result    = $generator->save_draft( $video_id, $title, $content, is_array( $metadata ) ? $metadata : array() );

		if ( is_wp_error( $result ) ) {
			return $this->prepare_error_response( $result );
		}

		return new \WP_REST_Response( $result, 201 );
	}

	/**
	 * POST /audio-post/preview - Generate a blog post preview from an audio attachment.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function preview_audio_post( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id       = absint( $request->get_param( 'post_id' ) );
		$attachment_id = absint( $request->get_param( 'attachment_id' ) );
		$language      = (string) ( $request->get_param( 'language' ) ?? '' );
		$persona       = (string) ( $request->get_param( 'persona' ) ?? '' );

		if ( empty( $language ) ) {
			$language = get_option( 'wttba_default_language', 'en' );
		}

		$generator = new Content_Generator();
		$result    = $generator->generate_from_audio_attachment( $attachment_id, $language, $persona );

		if ( is_wp_error( $result ) ) {
			Generation_Logger::record( $post_id, Generation_Logger::metadata_from_error( 'audio_upload', $result ) );
			return $this->prepare_error_response( $result );
		}

		wp_update_post(
			array(
				'ID'          => $attachment_id,
				'post_parent' => $post_id,
			)
		);

		update_post_meta( $post_id, '_wttba_source_type', 'audio_upload' );
		update_post_meta( $post_id, '_wttba_source_attachment_id', $attachment_id );
		Generation_Logger::record( $post_id, $result['ai_metadata'] );

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /posts/{id}/audio - Generate an audio attachment from a post.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_post_audio( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id         = absint( $request->get_param( 'id' ) );
		$voice           = (string) $request->get_param( 'voice' );
		$overwrite_block = rest_sanitize_boolean( $request->get_param( 'overwrite_block' ) );

		$generator = new Post_Audio_Generator();
		$result    = $generator->generate_for_post( $post_id, $voice, $overwrite_block );

		if ( is_wp_error( $result ) ) {
			Generation_Logger::record( $post_id, Generation_Logger::metadata_from_error( 'post_audio', $result ) );
			return $this->prepare_error_response( $result );
		}

		return new \WP_REST_Response( $result, 201 );
	}
}
