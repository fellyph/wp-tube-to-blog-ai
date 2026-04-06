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
		'wttba_rate_limited'        => 429,
		'wttba_video_not_found'     => 404,
		'wttba_no_captions'         => 404,
		'wttba_no_tracks'           => 404,
		'wttba_no_track_url'        => 404,
		'wttba_empty_transcript'    => 404,
		'wttba_captions_parse_error' => 502,
		'wttba_xml_parse_error'     => 502,
		'wttba_ai_parse_error'      => 502,
		'wttba_youtube_api_error'   => 502,
	);

	/**
	 * Error category mapping for known error codes.
	 */
	private const ERROR_CATEGORY_MAP = array(
		'wttba_not_configured'      => 'configuration',
		'wttba_ai_client_missing'   => 'configuration',
		'wttba_rate_limited'        => 'rate_limit',
		'wttba_video_not_found'     => 'not_found',
		'wttba_no_captions'         => 'not_found',
		'wttba_no_tracks'           => 'not_found',
		'wttba_no_track_url'        => 'not_found',
		'wttba_empty_transcript'    => 'not_found',
		'wttba_captions_parse_error' => 'upstream',
		'wttba_xml_parse_error'     => 'upstream',
		'wttba_ai_parse_error'      => 'upstream',
		'wttba_youtube_api_error'   => 'upstream',
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

		// Preserve existing status if already set (e.g., YouTube API errors).
		if ( empty( $data['status'] ) ) {
			$data['status'] = self::ERROR_STATUS_MAP[ $code ] ?? 500;
		}

		if ( empty( $data['error_category'] ) ) {
			$data['error_category'] = self::ERROR_CATEGORY_MAP[ $code ] ?? 'internal';
		}

		$error->add_data( $data, $code );

		return $error;
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
			$request->get_param( 'page_token' ),
			$request->get_param( 'max_results' )
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
		$video_id = $request->get_param( 'video_id' );
		$language = $request->get_param( 'language' );
		$persona  = $request->get_param( 'persona' );

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
		$video_id = $request->get_param( 'video_id' );
		$title    = $request->get_param( 'title' );
		$content  = $request->get_param( 'content' );

		$generator = new Post_Generator();
		$result    = $generator->save_draft( $video_id, $title, $content );

		if ( is_wp_error( $result ) ) {
			return $this->prepare_error_response( $result );
		}

		return new \WP_REST_Response( $result, 201 );
	}
}
