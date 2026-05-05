<?php
/**
 * Block editor and post-list integration.
 *
 * @package WP_Tube_To_Blog_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds AI Content Suite actions to the post editor and posts list table.
 */
class Editor_Integration {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_filter( 'post_row_actions', array( $this, 'add_post_row_action' ), 10, 2 );
		add_action( 'admin_post_wttba_generate_post_audio', array( $this, 'handle_post_audio_action' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	/**
	 * Enqueue the editor sidebar script.
	 */
	public function enqueue_editor_assets(): void {
		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->post_type ) {
			return;
		}

		$asset_file = WTTBA_PLUGIN_DIR . 'build/editor.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'wttba-editor',
			WTTBA_PLUGIN_URL . 'build/editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'wttba-editor',
			WTTBA_PLUGIN_URL . 'build/style-editor.css',
			array(),
			$asset['version']
		);

		wp_set_script_translations(
			'wttba-editor',
			'wp-tube-to-blog-ai',
			WTTBA_PLUGIN_DIR . 'languages'
		);

		$content_generator = new Content_Generator();

		wp_localize_script(
			'wttba-editor',
			'wttbaEditorConfig',
			array(
				'restUrl'                => rest_url( 'wttba/v1' ),
				'nonce'                  => wp_create_nonce( 'wp_rest' ),
				'defaultLanguage'        => get_option( 'wttba_default_language', 'en' ),
				'defaultPersona'         => get_option( 'wttba_default_persona', '' ),
				'languages'              => Settings::LANGUAGES,
				'settingsUrl'            => admin_url( 'options-general.php?page=wttba-settings' ),
				'connectorsUrl'          => AI_Provider_Status::get_configuration_url(),
				'ai'                     => AI_Provider_Status::get_admin_config(),
				'allowedAudioExtensions' => Content_Generator::ALLOWED_AUDIO_EXTENSIONS,
				'maxAudioBytes'          => $content_generator->get_max_audio_bytes(),
			)
		);
	}

	/**
	 * Add a manual post-to-audio action to the posts list.
	 *
	 * @param array<string, string> $actions Row actions.
	 * @param \WP_Post             $post    Post object.
	 * @return array<string, string>
	 */
	public function add_post_row_action( array $actions, \WP_Post $post ): array {
		if ( 'post' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		if ( ! AI_Provider_Status::is_text_to_speech_supported() ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'wttba_generate_post_audio',
					'post_id' => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'wttba_generate_post_audio_' . $post->ID
		);

		$actions['wttba_generate_audio'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Generate Audio', 'wp-tube-to-blog-ai' )
		);

		return $actions;
	}

	/**
	 * Handle posts-list post-to-audio action.
	 */
	public function handle_post_audio_action(): void {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to generate audio for this post.', 'wp-tube-to-blog-ai' ) );
		}

		check_admin_referer( 'wttba_generate_post_audio_' . $post_id );

		$generator = new Post_Audio_Generator();
		$result    = $generator->generate_for_post( $post_id );
		$status    = is_wp_error( $result ) ? 'error' : 'success';
		$message   = is_wp_error( $result ) ? $result->get_error_message() : __( 'Audio generated and attached to the post.', 'wp-tube-to-blog-ai' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'wttba_audio_status'  => $status,
					'wttba_audio_message' => rawurlencode( $message ),
				),
				wp_get_referer() ?: admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Render admin notices for post-list audio generation.
	 */
	public function render_admin_notices(): void {
		if ( empty( $_GET['wttba_audio_status'] ) || empty( $_GET['wttba_audio_message'] ) ) {
			return;
		}

		$status  = sanitize_key( wp_unslash( $_GET['wttba_audio_status'] ) );
		$message = sanitize_text_field( rawurldecode( wp_unslash( $_GET['wttba_audio_message'] ) ) );
		$class   = 'success' === $status ? 'notice-success' : 'notice-error';

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}
}
