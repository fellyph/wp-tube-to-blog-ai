<?php
/**
 * Main plugin orchestrator.
 *
 * @package CreatorStack_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin singleton that wires all components via WordPress hooks.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — wire components.
	 */
	private function __construct() {
		$this->check_dependencies();

		new Settings();
		new YouTube_OAuth();
		new Dashboard_Widget();
		new Admin_Videos_Page();
		new Editor_Integration();

		add_action( 'rest_api_init', array( new REST_Controller(), 'register_routes' ) );
		add_action( 'init', array( $this, 'register_post_meta' ) );
	}

	/**
	 * Check for required dependencies and show admin notice if missing.
	 */
	private function check_dependencies(): void {
		if ( ! AI_Provider_Status::is_supported_wordpress_version() || ! AI_Provider_Status::is_ai_client_available() ) {
			add_action( 'admin_notices', array( $this, 'missing_ai_client_notice' ) );
		}
	}

	/**
	 * Display admin notice when wp-ai-client is not available.
	 */
	public function missing_ai_client_notice(): void {
		$message = AI_Provider_Status::get_unavailable_message();

		if ( ! AI_Provider_Status::is_supported_wordpress_version() ) {
			$message = sprintf(
				/* translators: %s: current WordPress version. */
				__( 'CreatorStack AI requires WordPress 7.0 or newer because it uses the AI Client and Connectors APIs from Core. Current WordPress version: %s.', 'creatorstack-ai' ),
				AI_Provider_Status::get_wordpress_version()
			);
		}
		?>
		<div class="notice notice-warning">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Register protected plugin post meta.
	 */
	public function register_post_meta(): void {
		$auth_callback = static function ( $allowed, $meta_key, $post_id ): bool {
			$post_id = absint( $post_id );

			if ( $post_id > 0 ) {
				return current_user_can( 'edit_post', $post_id );
			}

			return current_user_can( 'edit_posts' );
		};

		register_post_meta(
			'post',
			'_wttba_source_type',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_key',
				'auth_callback'     => $auth_callback,
			)
		);

		register_post_meta(
			'post',
			'_wttba_source_attachment_id',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth_callback,
			)
		);

		register_post_meta(
			'post',
			Post_Audio_Generator::AUDIO_ATTACHMENT_META_KEY,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth_callback,
			)
		);

		register_post_meta(
			'post',
			Generation_Logger::META_KEY,
			array(
				'type'              => 'object',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( Generation_Logger::class, 'sanitize_metadata' ),
				'auth_callback'     => $auth_callback,
			)
		);
	}
}
