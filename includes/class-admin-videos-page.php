<?php
/**
 * Admin videos page.
 *
 * @package WP_Tube_To_Blog_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the full videos list admin page.
 */
class Admin_Videos_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
	}

	/**
	 * Add the admin menu page.
	 */
	public function add_menu_page(): void {
		add_menu_page(
			__( 'YouTube Videos', 'wp-tube-to-blog-ai' ),
			__( 'Tube-to-Blog', 'wp-tube-to-blog-ai' ),
			'edit_posts',
			'wttba-videos',
			array( $this, 'render_page' ),
			'dashicons-video-alt3',
			30
		);

		add_submenu_page(
			'wttba-videos',
			__( 'YouTube Content', 'wp-tube-to-blog-ai' ),
			__( 'YouTube Content', 'wp-tube-to-blog-ai' ),
			'edit_posts',
			'wttba-videos',
			array( $this, 'render_page' )
		);

		add_submenu_page(
			'wttba-videos',
			__( 'Audio to Post', 'wp-tube-to-blog-ai' ),
			__( 'Audio to Post', 'wp-tube-to-blog-ai' ),
			'edit_posts',
			'wttba-audio-to-post',
			array( $this, 'render_audio_page' )
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_page(): void {
		$this->enqueue_assets();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'YouTube Videos', 'wp-tube-to-blog-ai' ); ?></h1>
			<?php Admin_Navigation::render( 'youtube' ); ?>
			<div id="wttba-admin-videos"></div>
		</div>
		<?php
	}

	/**
	 * Render the audio-to-post page.
	 */
	public function render_audio_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Audio to Post', 'wp-tube-to-blog-ai' ); ?></h1>
			<?php Admin_Navigation::render( 'audio' ); ?>
			<div class="wttba-audio-to-post">
				<h2><?php esc_html_e( 'Create a draft from an audio file', 'wp-tube-to-blog-ai' ); ?></h2>
				<p>
					<?php esc_html_e( 'Open a new post draft, select an audio file from the AI Content Suite panel, then generate the article into the editor.', 'wp-tube-to-blog-ai' ); ?>
				</p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=post' ) ); ?>">
						<?php esc_html_e( 'Create Draft From Audio', 'wp-tube-to-blog-ai' ); ?>
					</a>
					<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'upload.php?mode=list' ) ); ?>">
						<?php esc_html_e( 'Media Library', 'wp-tube-to-blog-ai' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin videos page scripts and styles.
	 */
	private function enqueue_assets(): void {
		$asset_file = WTTBA_PLUGIN_DIR . 'build/admin-videos.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			echo '<p>' . esc_html__( 'Assets not built. Run npm run build.', 'wp-tube-to-blog-ai' ) . '</p>';
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'wttba-admin-videos',
			WTTBA_PLUGIN_URL . 'build/admin-videos.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'wttba-admin-videos',
			WTTBA_PLUGIN_URL . 'build/style-admin-videos.css',
			array(),
			$asset['version']
		);

		wp_set_script_translations(
			'wttba-admin-videos',
			'wp-tube-to-blog-ai',
			WTTBA_PLUGIN_DIR . 'languages'
		);

		wp_localize_script(
			'wttba-admin-videos',
			'wttbaConfig',
			array(
				'restUrl'         => rest_url( 'wttba/v1' ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'defaultLanguage' => get_option( 'wttba_default_language', 'en' ),
				'defaultPersona'  => get_option( 'wttba_default_persona', '' ),
				'languages'       => Settings::LANGUAGES,
				'isConfigured'    => ( new YouTube_API() )->is_configured(),
				'settingsUrl'     => admin_url( 'options-general.php?page=wttba-settings' ),
				'ai'              => AI_Provider_Status::get_admin_config(),
			)
		);
	}
}
