<?php
/**
 * Dashboard widget.
 *
 * @package WP_Tube_To_Blog_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the YouTube to Blog dashboard widget.
 */
class Dashboard_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
	}

	/**
	 * Register the dashboard widget.
	 */
	public function register_widget(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'wttba_dashboard_widget',
			__( 'YouTube to Blog', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Render the widget container and enqueue scripts.
	 */
	public function render_widget(): void {
		$this->enqueue_assets();

		echo '<div id="wttba-dashboard-widget"></div>';
	}

	/**
	 * Enqueue dashboard widget scripts and styles.
	 */
	private function enqueue_assets(): void {
		$asset_file = WTTBA_PLUGIN_DIR . 'build/dashboard-widget.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			echo '<p>' . esc_html__( 'Widget assets not built. Run npm run build.', 'wp-tube-to-blog-ai' ) . '</p>';
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'wttba-dashboard-widget',
			WTTBA_PLUGIN_URL . 'build/dashboard-widget.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'wttba-dashboard-widget',
			WTTBA_PLUGIN_URL . 'build/style-dashboard-widget.css',
			array(),
			$asset['version']
		);

		wp_set_script_translations(
			'wttba-dashboard-widget',
			'wp-tube-to-blog-ai',
			WTTBA_PLUGIN_DIR . 'languages'
		);

		wp_localize_script(
			'wttba-dashboard-widget',
			'wttbaConfig',
			array(
				'restUrl'         => rest_url( 'wttba/v1' ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'adminVideosUrl'  => admin_url( 'admin.php?page=wttba-videos' ),
				'defaultLanguage' => get_option( 'wttba_default_language', 'en' ),
				'defaultPersona'  => get_option( 'wttba_default_persona', '' ),
				'languages'       => Settings::LANGUAGES,
				'isConfigured'    => ( new YouTube_API() )->is_configured(),
				'settingsUrl'     => admin_url( 'options-general.php?page=wttba-settings' ),
			)
		);
	}
}
