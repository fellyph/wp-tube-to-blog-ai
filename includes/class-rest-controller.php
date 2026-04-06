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
			'/generate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_post' ),
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
	}

	/**
	 * Permission check: current user can edit posts.
	 *
	 * @return bool
	 */
	public function can_edit_posts(): bool {
		return current_user_can( 'edit_posts' );
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
			return $result;
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
			return $result;
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /generate — Generate a blog post from a video.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_post( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$video_id = $request->get_param( 'video_id' );
		$language = $request->get_param( 'language' );
		$persona  = $request->get_param( 'persona' );

		// Default to the saved setting if no language provided.
		if ( empty( $language ) ) {
			$language = get_option( 'wttba_default_language', 'en' );
		}

		$generator = new Post_Generator();
		$result    = $generator->generate( $video_id, $language, $persona );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 201 );
	}
}
