<?php
/**
 * Admin videos page.
 *
 * @package CreatorStack_AI
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add the admin menu page.
	 */
	public function add_menu_page(): void {
		$youtube_enabled = Settings::is_youtube_to_post_enabled();
		$audio_enabled   = Settings::is_audio_to_post_enabled();

		if ( ! $youtube_enabled && ! $audio_enabled ) {
			return;
		}

		$default_slug     = $youtube_enabled ? 'wttba-videos' : 'wttba-audio-to-post';
		$default_title    = $youtube_enabled ? __( 'YouTube Videos', 'creatorstack-ai' ) : __( 'Audio to Post', 'creatorstack-ai' );
		$default_callback = $youtube_enabled ? array( $this, 'render_page' ) : array( $this, 'render_audio_page' );

		add_menu_page(
			$default_title,
			__( 'CreatorStack', 'creatorstack-ai' ),
			'edit_posts',
			$default_slug,
			$default_callback,
			'dashicons-video-alt3',
			30
		);

		if ( $youtube_enabled ) {
			add_submenu_page(
				$default_slug,
				__( 'YouTube Content', 'creatorstack-ai' ),
				__( 'YouTube Content', 'creatorstack-ai' ),
				'edit_posts',
				'wttba-videos',
				array( $this, 'render_page' )
			);
		}

		if ( $audio_enabled ) {
			add_submenu_page(
				$default_slug,
				__( 'Audio to Post', 'creatorstack-ai' ),
				__( 'Audio to Post', 'creatorstack-ai' ),
				'edit_posts',
				'wttba-audio-to-post',
				array( $this, 'render_audio_page' )
			);
		}
	}

	/**
	 * Enqueue scripts and styles on the CreatorStack admin screens.
	 *
	 * @param string $hook_suffix Current admin screen hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading current admin page slug for asset enqueueing.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		$is_videos_page = 'wttba-videos' === $page || str_ends_with( $hook_suffix, '_page_wttba-videos' );
		$is_audio_page  = 'wttba-audio-to-post' === $page || str_ends_with( $hook_suffix, '_page_wttba-audio-to-post' );

		if ( $is_videos_page && Settings::is_youtube_to_post_enabled() ) {
			$this->enqueue_assets();
			return;
		}

		if ( $is_audio_page && Settings::is_audio_to_post_enabled() ) {
			wp_enqueue_media();
			$this->enqueue_assets();
		}
	}

	/**
	 * Render the admin page.
	 */
	public function render_page(): void {
		if ( ! Settings::is_youtube_to_post_enabled() ) {
			$this->render_feature_disabled_page( Settings::FEATURE_YOUTUBE_TO_POST, 'youtube' );
			return;
		}

		$this->enqueue_assets( true );

		?>
		<div class="wrap wttba-admin-page wttba-admin-page--youtube">
			<div class="wttba-admin-shell">
				<?php
				$this->render_page_header(
					__( 'YouTube Content', 'creatorstack-ai' ),
					__( 'Browse channel videos, extract transcripts, and generate WordPress drafts from one focused workspace.', 'creatorstack-ai' )
				);
				Admin_Navigation::render( 'youtube' );
				?>
				<hr class="wp-header-end">
				<div id="wttba-admin-videos"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the audio-to-post page.
	 */
	public function render_audio_page(): void {
		if ( ! Settings::is_audio_to_post_enabled() ) {
			$this->render_feature_disabled_page( Settings::FEATURE_AUDIO_TO_POST, 'audio' );
			return;
		}

		if ( ! did_action( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}

		$this->enqueue_assets( true );

		?>
		<div class="wrap wttba-admin-page wttba-admin-page--audio">
			<div class="wttba-admin-shell">
				<?php
				$this->render_page_header(
					__( 'Audio to Post', 'creatorstack-ai' ),
					__( 'Record or select audio, transcribe it with AI, and generate draft posts from spoken content.', 'creatorstack-ai' )
				);
				Admin_Navigation::render( 'audio' );
				?>
				<hr class="wp-header-end">
				<div id="wttba-audio-to-post"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a disabled feature message for direct page access.
	 *
	 * @param string $feature Feature key.
	 * @param string $active  Active navigation key.
	 */
	private function render_feature_disabled_page( string $feature, string $active ): void {
		$feature_label = Settings::get_feature_label( $feature );
		?>
		<div class="wrap wttba-admin-page wttba-admin-page--disabled">
			<div class="wttba-admin-shell">
				<?php
				$this->render_page_header(
					$feature_label,
					__( 'This CreatorStack AI workflow is currently disabled for this WordPress site.', 'creatorstack-ai' )
				);
				Admin_Navigation::render( $active );
				?>
				<hr class="wp-header-end">
				<div class="notice notice-warning inline">
					<p>
						<?php
						printf(
							/* translators: %s: feature label. */
							esc_html__( '%s is disabled in CreatorStack AI settings.', 'creatorstack-ai' ),
							esc_html( $feature_label )
						);
						?>
						<?php if ( current_user_can( 'manage_options' ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'options-general.php?page=wttba-settings' ) ); ?>">
								<?php esc_html_e( 'Update settings', 'creatorstack-ai' ); ?>
							</a>
						<?php endif; ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the shared CreatorStack page header.
	 *
	 * @param string $title       Page title.
	 * @param string $description Page description.
	 */
	private function render_page_header( string $title, string $description ): void {
		?>
		<header class="wttba-admin-hero">
			<div class="wttba-admin-hero__content">
				<p class="wttba-admin-eyebrow"><?php esc_html_e( 'CreatorStack AI', 'creatorstack-ai' ); ?></p>
				<h1><?php echo esc_html( $title ); ?></h1>
				<p class="wttba-admin-hero__description"><?php echo esc_html( $description ); ?></p>
			</div>
			<img
				class="wttba-admin-hero__logo"
				src="<?php echo esc_url( WTTBA_PLUGIN_URL . 'assets/creatorstack-ai-logo.png' ); ?>"
				alt=""
				width="188"
				height="188"
				decoding="async"
				aria-hidden="true"
			/>
		</header>
		<?php
	}

	/**
	 * Enqueue admin videos page scripts and styles.
	 *
	 * @param bool $show_missing_notice Whether to output a notice when build assets are missing.
	 */
	private function enqueue_assets( bool $show_missing_notice = false ): void {
		if ( wp_script_is( 'wttba-admin-videos', 'enqueued' ) && wp_style_is( 'wttba-admin-videos', 'enqueued' ) ) {
			return;
		}

		$asset_file = WTTBA_PLUGIN_DIR . 'build/admin-videos.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			if ( $show_missing_notice ) {
				echo '<p>' . esc_html__( 'Assets not built. Run npm run build.', 'creatorstack-ai' ) . '</p>';
			}
			return;
		}

		$asset = require $asset_file;
		$content_generator = new Content_Generator();

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
			array( 'wp-components' ),
			$asset['version']
		);

		wp_set_script_translations(
			'wttba-admin-videos',
			'creatorstack-ai',
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
				'isConfigured'    => YouTube_Connector::is_configured(),
				'settingsUrl'     => admin_url( 'options-general.php?page=wttba-settings' ),
				'youtube'         => YouTube_Connector::get_admin_config(),
				'mediaLibraryUrl' => admin_url( 'upload.php?mode=list' ),
				'newPostUrl'      => admin_url( 'post-new.php?post_type=post' ),
				'features'        => Settings::get_feature_states(),
				'ai'              => AI_Provider_Status::get_admin_config(),
				'allowedAudioExtensions' => Content_Generator::ALLOWED_AUDIO_EXTENSIONS,
				'maxAudioBytes'   => $content_generator->get_max_audio_bytes(),
			)
		);
	}
}
