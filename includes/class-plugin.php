<?php
/**
 * Main plugin orchestrator.
 *
 * @package WP_Tube_To_Blog_AI
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
		new Dashboard_Widget();
		new Admin_Videos_Page();

		add_action( 'rest_api_init', array( new REST_Controller(), 'register_routes' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init_ai_client' ) );
	}

	/**
	 * Initialize the WordPress AI Client SDK.
	 */
	public function init_ai_client(): void {
		if ( class_exists( \WordPress\AI_Client\AI_Client::class ) ) {
			\WordPress\AI_Client\AI_Client::init();
		}
	}

	/**
	 * Load plugin text domain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wp-tube-to-blog-ai',
			false,
			dirname( plugin_basename( WTTBA_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Check for required dependencies and show admin notice if missing.
	 */
	private function check_dependencies(): void {
		if ( ! function_exists( 'wp_ai_client_prompt' ) && ! class_exists( \WordPress\AI_Client\AI_Client::class ) ) {
			add_action( 'admin_notices', array( $this, 'missing_ai_client_notice' ) );
		}
	}

	/**
	 * Display admin notice when wp-ai-client is not available.
	 */
	public function missing_ai_client_notice(): void {
		?>
		<div class="notice notice-warning">
			<p>
				<?php
				esc_html_e(
					'WP Tube-to-Blog AI requires the WordPress AI Client plugin to generate blog posts. Please install and activate it.',
					'wp-tube-to-blog-ai'
				);
				?>
			</p>
		</div>
		<?php
	}
}
