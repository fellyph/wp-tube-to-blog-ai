<?php
/**
 * Plugin settings page.
 *
 * @package WP_Tube_To_Blog_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the Settings API registration and rendering.
 */
class Settings {

	/**
	 * Supported languages for blog post generation.
	 *
	 * @var array<string, string>
	 */
	public const LANGUAGES = array(
		'en' => 'English',
		'es' => 'Español',
		'fr' => 'Français',
		'de' => 'Deutsch',
		'it' => 'Italiano',
		'pt' => 'Português',
		'pt-br' => 'Português (Brasil)',
		'nl' => 'Nederlands',
		'ja' => '日本語',
		'ko' => '한국어',
		'zh' => '中文',
		'ar' => 'العربية',
		'hi' => 'हिन्दी',
		'ru' => 'Русский',
		'tr' => 'Türkçe',
		'pl' => 'Polski',
		'sv' => 'Svenska',
	);

	/**
	 * Preferred model option values.
	 *
	 * @var array<int, string>
	 */
	public const AI_MODEL_IDS = array(
		'',
		'claude-sonnet-4-6',
		'gpt-5.4',
		'gemini-3-flash-preview',
		'gemini-3-pro-preview',
		'gemini-3.1-pro-preview',
		'gemini-3.1-flash-lite-preview',
		'gemma-4-31b-it',
		'gemini-2.5-flash',
		'gpt-4o-mini',
	);

	/**
	 * Default post length option.
	 */
	public const DEFAULT_POST_LENGTH = 'medium';

	/**
	 * Post length option values.
	 *
	 * @var array<int, string>
	 */
	public const POST_LENGTH_IDS = array( 'short', 'medium', 'long' );

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ) );
	}

	/**
	 * Add the settings page under the Settings menu.
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'AI Content Suite', 'wp-tube-to-blog-ai' ),
			__( 'AI Content Suite', 'wp-tube-to-blog-ai' ),
			'manage_options',
			'wttba-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public function register_settings(): void {
		// YouTube API Key.
		register_setting(
			'wttba_settings',
			'wttba_youtube_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_youtube_api_key' ),
				'default'           => '',
			)
		);

		// YouTube Channel ID.
		register_setting(
			'wttba_settings',
			'wttba_youtube_channel_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_youtube_channel_id' ),
				'default'           => '',
			)
		);

		// YouTube OAuth Client ID.
		register_setting(
			'wttba_settings',
			'wttba_youtube_oauth_client_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_oauth_client_id' ),
				'default'           => '',
			)
		);

		// YouTube OAuth Client Secret.
		register_setting(
			'wttba_settings',
			'wttba_youtube_oauth_client_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_oauth_client_secret' ),
				'default'           => '',
			)
		);

		// Default Language.
		register_setting(
			'wttba_settings',
			'wttba_default_language',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_language' ),
				'default'           => 'en',
			)
		);

		// Default Persona.
		register_setting(
			'wttba_settings',
			'wttba_default_persona',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => '',
			)
		);

		// Default post length.
		register_setting(
			'wttba_settings',
			'wttba_post_length',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_post_length' ),
				'default'           => self::DEFAULT_POST_LENGTH,
			)
		);

		// Preferred AI model.
		register_setting(
			'wttba_settings',
			'wttba_ai_model',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_ai_model' ),
				'default'           => '',
			)
		);

		// YouTube section.
		add_settings_section(
			'wttba_youtube_section',
			__( 'YouTube Integration', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_youtube_section' ),
			'wttba-settings',
			$this->get_settings_section_args( 'youtube' )
		);

		add_settings_field(
			'wttba_youtube_api_key',
			__( 'YouTube API Key', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_api_key_field' ),
			'wttba-settings',
			'wttba_youtube_section'
		);

		add_settings_field(
			'wttba_youtube_channel_id',
			__( 'YouTube Channel ID', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_channel_id_field' ),
			'wttba-settings',
			'wttba_youtube_section'
		);

		add_settings_field(
			'wttba_youtube_oauth_client_id',
			__( 'OAuth Client ID', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_oauth_client_id_field' ),
			'wttba-settings',
			'wttba_youtube_section'
		);

		add_settings_field(
			'wttba_youtube_oauth_client_secret',
			__( 'OAuth Client Secret', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_oauth_client_secret_field' ),
			'wttba-settings',
			'wttba_youtube_section'
		);

		add_settings_field(
			'wttba_youtube_oauth_connection',
			__( 'OAuth Connection', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_oauth_connection_field' ),
			'wttba-settings',
			'wttba_youtube_section'
		);

		// Content section.
		add_settings_section(
			'wttba_content_section',
			__( 'Content Settings', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_content_section' ),
			'wttba-settings',
			$this->get_settings_section_args( 'content' )
		);

		add_settings_field(
			'wttba_default_language',
			__( 'Default Output Language', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_language_field' ),
			'wttba-settings',
			'wttba_content_section'
		);

		add_settings_field(
			'wttba_post_length',
			__( 'Post Length', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_post_length_field' ),
			'wttba-settings',
			'wttba_content_section'
		);

		add_settings_field(
			'wttba_default_persona',
			__( 'Writing Persona', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_persona_field' ),
			'wttba-settings',
			'wttba_content_section'
		);

		// AI Provider section.
		add_settings_section(
			'wttba_ai_section',
			__( 'AI Provider', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_ai_section' ),
			'wttba-settings',
			$this->get_settings_section_args( 'ai-provider' )
		);

		add_settings_field(
			'wttba_ai_model',
			__( 'Preferred AI Model', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_ai_model_field' ),
			'wttba-settings',
			'wttba_ai_section'
		);

		add_settings_section(
			'wttba_usage_section',
			__( 'AI Usage', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_usage_section' ),
			'wttba-settings',
			$this->get_settings_section_args( 'usage' )
		);
	}

	/**
	 * Build section wrapper arguments for Settings API output.
	 *
	 * @param string $modifier Section modifier slug.
	 * @return array{before_section: string, after_section: string, section_class: string}
	 */
	private function get_settings_section_args( string $modifier ): array {
		return array(
			'before_section' => '<div class="%s">',
			'after_section'  => '</div>',
			'section_class'  => 'wttba-settings-section wttba-settings-section--' . sanitize_html_class( $modifier ),
		);
	}

	/**
	 * Sanitize language value against whitelist.
	 *
	 * @param string $value The submitted value.
	 * @return string
	 */
	public function sanitize_language( string $value ): string {
		if ( array_key_exists( $value, self::LANGUAGES ) ) {
			return $value;
		}
		return 'en';
	}

	/**
	 * Sanitize the preferred AI model option.
	 *
	 * @param string $value Submitted model ID.
	 * @return string
	 */
	public function sanitize_ai_model( string $value ): string {
		$value = sanitize_text_field( trim( $value ) );

		return in_array( $value, self::AI_MODEL_IDS, true ) ? $value : '';
	}

	/**
	 * Sanitize the default post length option.
	 *
	 * @param string $value Submitted post length.
	 * @return string
	 */
	public function sanitize_post_length( string $value ): string {
		$value = sanitize_key( $value );

		return in_array( $value, self::POST_LENGTH_IDS, true ) ? $value : self::DEFAULT_POST_LENGTH;
	}

	/**
	 * Get AI model choices for settings UI.
	 *
	 * @return array<string, string>
	 */
	public static function get_ai_model_options(): array {
		return array(
			''                                => __( 'Automatic (recommended)', 'wp-tube-to-blog-ai' ),
			'claude-sonnet-4-6'               => 'Claude Sonnet 4.6',
			'gpt-5.4'                         => 'GPT-5.4',
			'gemini-3-flash-preview'          => 'Gemini 3 Flash Preview',
			'gemini-3-pro-preview'            => 'Gemini 3 Pro Preview',
			'gemini-3.1-pro-preview'          => 'Gemini 3.1 Pro Preview',
			'gemini-3.1-flash-lite-preview'   => 'Gemini 3.1 Flash Lite Preview',
			'gemma-4-31b-it'                  => 'Gemma 4 31B IT',
			'gemini-2.5-flash'                => 'Gemini 2.5 Flash',
			'gpt-4o-mini'                     => 'GPT-4o mini',
		);
	}

	/**
	 * Get post length choices for settings UI.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function get_post_length_options(): array {
		return array(
			'short'  => array(
				'label'       => __( 'Short', 'wp-tube-to-blog-ai' ),
				'description' => __( 'About 600 to 900 words.', 'wp-tube-to-blog-ai' ),
			),
			'medium' => array(
				'label'       => __( 'Medium', 'wp-tube-to-blog-ai' ),
				'description' => __( 'About 1,000 to 1,500 words.', 'wp-tube-to-blog-ai' ),
			),
			'long'   => array(
				'label'       => __( 'Long', 'wp-tube-to-blog-ai' ),
				'description' => __( 'About 1,800 to 2,500 words.', 'wp-tube-to-blog-ai' ),
			),
		);
	}

	/**
	 * Get generation settings for the selected post length.
	 *
	 * @param string|null $length Optional length key.
	 * @return array{max_tokens: int, instruction: string}
	 */
	public static function get_post_length_generation_config( ?string $length = null ): array {
		$length = null === $length ? (string) get_option( 'wttba_post_length', self::DEFAULT_POST_LENGTH ) : $length;
		$length = in_array( $length, self::POST_LENGTH_IDS, true ) ? $length : self::DEFAULT_POST_LENGTH;

		if ( 'short' === $length ) {
			return array(
				'max_tokens'  => 3500,
				'instruction' => __( 'Aim for a concise post of about 600 to 900 words. Focus on the core takeaways and avoid extended background.', 'wp-tube-to-blog-ai' ),
			);
		}

		if ( 'long' === $length ) {
			return array(
				'max_tokens'  => 8000,
				'instruction' => __( 'Aim for a detailed post of about 1,800 to 2,500 words. Expand important sections with context, examples, and practical takeaways.', 'wp-tube-to-blog-ai' ),
			);
		}

		return array(
			'max_tokens'  => 5500,
			'instruction' => __( 'Aim for a balanced post of about 1,000 to 1,500 words. Cover the main ideas with enough context and examples.', 'wp-tube-to-blog-ai' ),
		);
	}

	/**
	 * Sanitize and validate the YouTube API key option.
	 *
	 * @param string $value Submitted API key.
	 * @return string
	 */
	public function sanitize_youtube_api_key( string $value ): string {
		$value = sanitize_text_field( trim( $value ) );

		if ( '' === $value || self::is_valid_youtube_api_key( $value ) ) {
			return $value;
		}

		add_settings_error(
			'wttba_youtube_api_key',
			'wttba_youtube_api_key_invalid',
			__( 'Enter a valid YouTube Data API key from Google Cloud.', 'wp-tube-to-blog-ai' )
		);

		return $this->get_previous_valid_option( 'wttba_youtube_api_key', array( self::class, 'is_valid_youtube_api_key' ) );
	}

	/**
	 * Sanitize and validate the YouTube Channel ID option.
	 *
	 * @param string $value Submitted Channel ID.
	 * @return string
	 */
	public function sanitize_youtube_channel_id( string $value ): string {
		$value = sanitize_text_field( trim( $value ) );

		if ( '' === $value || self::is_valid_youtube_channel_id( $value ) ) {
			return $value;
		}

		add_settings_error(
			'wttba_youtube_channel_id',
			'wttba_youtube_channel_id_invalid',
			__( 'Enter a valid YouTube Channel ID. Channel IDs start with UC followed by 22 characters.', 'wp-tube-to-blog-ai' )
		);

		return $this->get_previous_valid_option( 'wttba_youtube_channel_id', array( self::class, 'is_valid_youtube_channel_id' ) );
	}

	/**
	 * Sanitize and validate the OAuth client ID option.
	 *
	 * @param string $value Submitted OAuth client ID.
	 * @return string
	 */
	public function sanitize_oauth_client_id( string $value ): string {
		$value = sanitize_text_field( trim( $value ) );

		if ( '' === $value || YouTube_OAuth::is_valid_client_id( $value ) ) {
			return $value;
		}

		add_settings_error(
			'wttba_youtube_oauth_client_id',
			'wttba_youtube_oauth_client_id_invalid',
			__( 'Enter a valid Google OAuth Web application Client ID ending in .apps.googleusercontent.com.', 'wp-tube-to-blog-ai' )
		);

		return $this->get_previous_valid_option( 'wttba_youtube_oauth_client_id', array( YouTube_OAuth::class, 'is_valid_client_id' ) );
	}

	/**
	 * Sanitize and validate the OAuth client secret option.
	 *
	 * @param string $value Submitted OAuth client secret.
	 * @return string
	 */
	public function sanitize_oauth_client_secret( string $value ): string {
		$value = sanitize_text_field( trim( $value ) );

		if ( '' === $value || YouTube_OAuth::is_valid_client_secret( $value ) ) {
			return $value;
		}

		add_settings_error(
			'wttba_youtube_oauth_client_secret',
			'wttba_youtube_oauth_client_secret_invalid',
			__( 'Enter a valid Google OAuth Client Secret from the Web application client.', 'wp-tube-to-blog-ai' )
		);

		return $this->get_previous_valid_option( 'wttba_youtube_oauth_client_secret', array( YouTube_OAuth::class, 'is_valid_client_secret' ) );
	}

	/**
	 * Get the previously saved option only when it is still valid.
	 *
	 * @param string   $option    Option name.
	 * @param callable $validator Validator callback.
	 * @return string
	 */
	private function get_previous_valid_option( string $option, callable $validator ): string {
		$previous = trim( (string) get_option( $option, '' ) );

		return $validator( $previous ) ? $previous : '';
	}

	/**
	 * Whether a value looks like a YouTube Data API key.
	 *
	 * @param string $api_key API key.
	 * @return bool
	 */
	private static function is_valid_youtube_api_key( string $api_key ): bool {
		return 1 === preg_match( '/^AIza[0-9A-Za-z_-]{35}$/', trim( $api_key ) );
	}

	/**
	 * Whether a value looks like a YouTube Channel ID.
	 *
	 * @param string $channel_id Channel ID.
	 * @return bool
	 */
	private static function is_valid_youtube_channel_id( string $channel_id ): bool {
		return 1 === preg_match( '/^UC[0-9A-Za-z_-]{22}$/', trim( $channel_id ) );
	}

	/**
	 * Build local environment compatibility details.
	 *
	 * @return array{supported: bool, isLocal: bool, host: string, siteUrl: string, homeUrl: string, restUrl: string, message: string}
	 */
	public static function get_localhost_status(): array {
		$site_url  = site_url();
		$home_url  = home_url();
		$rest_url  = rest_url( 'wttba/v1' );
		$site_host = (string) wp_parse_url( $site_url, PHP_URL_HOST );
		$home_host = (string) wp_parse_url( $home_url, PHP_URL_HOST );
		$host      = '' !== $site_host ? $site_host : $home_host;
		$is_local  = self::is_localhost_host( $site_host ) || self::is_localhost_host( $home_host );
		$message   = $is_local
			? __( 'This site is running locally. The plugin can run on localhost when WordPress can make outbound HTTPS requests to YouTube and the configured AI provider.', 'wp-tube-to-blog-ai' )
			: __( 'This site is not currently using a localhost URL. Localhost is supported for development when outbound HTTPS requests and provider credentials are available.', 'wp-tube-to-blog-ai' );

		return array(
			'supported' => true,
			'isLocal'   => $is_local,
			'host'      => sanitize_text_field( $host ),
			'siteUrl'   => esc_url_raw( $site_url ),
			'homeUrl'   => esc_url_raw( $home_url ),
			'restUrl'   => esc_url_raw( $rest_url ),
			'message'   => $message,
		);
	}

	/**
	 * Enqueue scripts for the settings screen.
	 *
	 * @param string $hook_suffix Current admin screen hook suffix.
	 */
	public function enqueue_settings_assets( string $hook_suffix ): void {
		if ( 'settings_page_wttba-settings' !== $hook_suffix ) {
			return;
		}

		$asset_file = WTTBA_PLUGIN_DIR . 'build/settings.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'wttba-settings',
			WTTBA_PLUGIN_URL . 'build/settings.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'wttba-settings',
			WTTBA_PLUGIN_URL . 'build/style-settings.css',
			array( 'dashicons' ),
			$asset['version']
		);

		wp_set_script_translations(
			'wttba-settings',
			'wp-tube-to-blog-ai',
			WTTBA_PLUGIN_DIR . 'languages'
		);

		wp_localize_script(
			'wttba-settings',
			'wttbaSettingsConfig',
			array(
				'testPath'          => '/wttba/v1/ai/test',
				'configurationUrl'  => AI_Provider_Status::get_configuration_url(),
				'localhost'         => self::get_localhost_status(),
				'configurationLabel' => __( 'Configure AI Provider', 'wp-tube-to-blog-ai' ),
				'redirectUri'       => YouTube_OAuth::get_redirect_uri(),
			)
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		?>
		<div class="wrap wttba-settings-page">
			<div class="wttba-settings-shell">
				<header class="wttba-settings-hero">
					<div class="wttba-settings-hero__content">
						<p class="wttba-settings-eyebrow"><?php esc_html_e( 'WP Tube-to-Blog AI', 'wp-tube-to-blog-ai' ); ?></p>
						<h1><?php esc_html_e( 'AI Content Suite Settings', 'wp-tube-to-blog-ai' ); ?></h1>
						<p class="wttba-settings-hero__description"><?php esc_html_e( 'Connect YouTube, tune generated posts, and verify your AI provider from one focused workspace.', 'wp-tube-to-blog-ai' ); ?></p>
					</div>
				</header>
				<?php $this->render_oauth_status_notice(); ?>
				<?php Admin_Navigation::render( 'settings' ); ?>
				<form method="post" action="options.php" class="wttba-settings-form">
					<?php
					settings_fields( 'wttba_settings' );
					do_settings_sections( 'wttba-settings' );
					submit_button();
					?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * YouTube section description.
	 */
	public function render_youtube_section(): void {
		$status = $this->get_youtube_auth_setup_status();

		if ( $this->is_youtube_auth_setup_complete( $status ) ) {
			?>
			<p><?php esc_html_e( 'YouTube authentication is configured. You can update credentials below when they change.', 'wp-tube-to-blog-ai' ); ?></p>
			<?php
			return;
		}
		?>
		<p><?php esc_html_e( 'Enter your YouTube Data API v3 credentials to connect your channel. OAuth is used to download captions through the official YouTube Captions API for videos the connected account can edit.', 'wp-tube-to-blog-ai' ); ?></p>
		<div class="notice notice-info inline wttba-auth-checklist-notice">
			<p><strong><?php esc_html_e( 'YouTube authentication setup', 'wp-tube-to-blog-ai' ); ?></strong></p>
			<ul class="wttba-auth-checklist" aria-label="<?php esc_attr_e( 'YouTube authentication setup status', 'wp-tube-to-blog-ai' ); ?>">
				<?php
				$this->render_auth_setup_item(
					'api-key',
					__( 'YouTube API key saved', 'wp-tube-to-blog-ai' ),
					$status['apiKeyConfigured'],
					__( 'Required for listing channel videos and loading video details.', 'wp-tube-to-blog-ai' )
				);
				$this->render_auth_setup_item(
					'channel-id',
					__( 'YouTube Channel ID saved', 'wp-tube-to-blog-ai' ),
					$status['channelConfigured'],
					__( 'Required for browsing videos from the correct channel.', 'wp-tube-to-blog-ai' )
				);
				$this->render_auth_setup_item(
					'oauth-credentials',
					__( 'OAuth client credentials saved', 'wp-tube-to-blog-ai' ),
					$status['oauthCredentialsConfigured'],
					__( 'Required before WordPress can start the Google OAuth flow.', 'wp-tube-to-blog-ai' )
				);
				$this->render_auth_setup_item(
					'redirect-uri',
					__( 'Authorized redirect URI verified', 'wp-tube-to-blog-ai' ),
					$status['redirectUriVerified'],
					$status['redirectUriVerified']
						? __( 'Google returned to WordPress successfully, so the redirect URI is accepted.', 'wp-tube-to-blog-ai' )
						: __( 'Verified after Google redirects back to WordPress. If Google reports redirect_uri_mismatch, copy the URI shown below into Google Cloud.', 'wp-tube-to-blog-ai' )
				);
				$this->render_auth_setup_item(
					'youtube-account',
					__( 'YouTube account connected', 'wp-tube-to-blog-ai' ),
					$status['oauthConnected'],
					__( 'Required for official caption downloads from editable videos.', 'wp-tube-to-blog-ai' )
				);
				?>
			</ul>
		</div>
		<?php $this->render_youtube_setup_wizard(); ?>
		<?php
	}

	/**
	 * Render helper instructions for collecting YouTube and Google credentials.
	 */
	private function render_youtube_setup_wizard(): void {
		$redirect_uri = YouTube_OAuth::get_redirect_uri();
		?>
		<div id="wttba-oauth-setup-wizard" class="wttba-oauth-wizard" aria-labelledby="wttba-oauth-wizard-title">
			<h3 id="wttba-oauth-wizard-title"><?php esc_html_e( 'YouTube setup wizard', 'wp-tube-to-blog-ai' ); ?></h3>
			<p><?php esc_html_e( 'Use these steps to collect the YouTube and Google Cloud values WordPress needs for video listing, details, and official caption downloads.', 'wp-tube-to-blog-ai' ); ?></p>
			<ol class="wttba-oauth-wizard__steps">
				<li class="wttba-oauth-wizard__step">
					<strong><?php esc_html_e( 'Prepare Google Cloud', 'wp-tube-to-blog-ai' ); ?></strong>
					<p><?php esc_html_e( 'Open the Google Cloud project that owns the YouTube channel integration, then enable the YouTube Data API v3 if it is not already enabled.', 'wp-tube-to-blog-ai' ); ?></p>
					<p>
						<a class="button button-secondary" href="https://console.cloud.google.com/apis/library/youtube.googleapis.com" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Open YouTube Data API v3', 'wp-tube-to-blog-ai' ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'wp-tube-to-blog-ai' ); ?></span>
						</a>
					</p>
				</li>
				<li class="wttba-oauth-wizard__step">
					<strong><?php esc_html_e( 'Create a YouTube Data API key', 'wp-tube-to-blog-ai' ); ?></strong>
					<p><?php esc_html_e( 'In Google Cloud, go to APIs & Services > Credentials, create an API key, and paste it into the YouTube API Key field below. The API key is used for public video listing and video details.', 'wp-tube-to-blog-ai' ); ?></p>
					<p>
						<a class="button button-secondary" href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Open Google credentials', 'wp-tube-to-blog-ai' ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'wp-tube-to-blog-ai' ); ?></span>
						</a>
					</p>
				</li>
				<li class="wttba-oauth-wizard__step">
					<strong><?php esc_html_e( 'Find the YouTube Channel ID', 'wp-tube-to-blog-ai' ); ?></strong>
					<p><?php esc_html_e( 'Open YouTube account advanced settings, copy the value labeled Channel ID, and paste it into the YouTube Channel ID field below. Channel IDs usually start with UC.', 'wp-tube-to-blog-ai' ); ?></p>
					<p>
						<a class="button button-secondary" href="https://support.google.com/youtube/answer/3250431" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Find Channel ID help', 'wp-tube-to-blog-ai' ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'wp-tube-to-blog-ai' ); ?></span>
						</a>
					</p>
				</li>
				<li class="wttba-oauth-wizard__step">
					<strong><?php esc_html_e( 'Create a Web application OAuth client', 'wp-tube-to-blog-ai' ); ?></strong>
					<p><?php esc_html_e( 'In Google Cloud, go to APIs & Services > Credentials, create an OAuth client ID, and choose Web application as the application type.', 'wp-tube-to-blog-ai' ); ?></p>
					<p>
						<a class="button button-secondary" href="https://console.cloud.google.com/apis/credentials/oauthclient" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Create OAuth client', 'wp-tube-to-blog-ai' ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'wp-tube-to-blog-ai' ); ?></span>
						</a>
					</p>
				</li>
				<li class="wttba-oauth-wizard__step">
					<strong><?php esc_html_e( 'Add this Authorized redirect URI', 'wp-tube-to-blog-ai' ); ?></strong>
					<p><?php esc_html_e( 'Paste this exact URI into the Authorized redirect URIs field for the Web application client. Google requires an exact match.', 'wp-tube-to-blog-ai' ); ?></p>
					<div class="wttba-oauth-wizard__copy-row">
						<label class="screen-reader-text" for="wttba-oauth-wizard-redirect-uri">
							<?php esc_html_e( 'Authorized redirect URI', 'wp-tube-to-blog-ai' ); ?>
						</label>
						<input
							type="text"
							id="wttba-oauth-wizard-redirect-uri"
							class="large-text code"
							value="<?php echo esc_attr( $redirect_uri ); ?>"
							readonly
						/>
						<button type="button" class="button button-secondary" id="wttba-copy-redirect-uri">
							<?php esc_html_e( 'Copy URI', 'wp-tube-to-blog-ai' ); ?>
						</button>
					</div>
					<p id="wttba-copy-redirect-uri-status" class="description" aria-live="polite"></p>
				</li>
				<li class="wttba-oauth-wizard__step">
					<strong><?php esc_html_e( 'Download or copy the client secret JSON', 'wp-tube-to-blog-ai' ); ?></strong>
					<p id="wttba-oauth-client-json-help"><?php esc_html_e( 'After Google creates the client, download the client_secret.json file or copy its contents. Paste it here to fill the Client ID and Client Secret fields below. The secret is only stored after you click Save Changes.', 'wp-tube-to-blog-ai' ); ?></p>
					<textarea
						id="wttba-oauth-client-json"
						class="large-text code"
						rows="5"
						aria-describedby="wttba-oauth-client-json-help"
						placeholder="<?php esc_attr_e( 'Paste client_secret.json contents here', 'wp-tube-to-blog-ai' ); ?>"
					></textarea>
					<p>
						<button type="button" class="button button-secondary" id="wttba-fill-oauth-fields">
							<?php esc_html_e( 'Fill OAuth fields', 'wp-tube-to-blog-ai' ); ?>
						</button>
					</p>
					<p id="wttba-oauth-client-json-result" class="description" aria-live="polite"></p>
				</li>
				<li class="wttba-oauth-wizard__step">
					<strong><?php esc_html_e( 'Save, then connect YouTube', 'wp-tube-to-blog-ai' ); ?></strong>
					<p><?php esc_html_e( 'Click Save Changes so WordPress stores the Client ID and Client Secret. After the page reloads, use Connect YouTube to finish the Google account consent flow.', 'wp-tube-to-blog-ai' ); ?></p>
				</li>
			</ol>
		</div>
		<?php
	}

	/**
	 * Get setup state for the YouTube authentication flow.
	 *
	 * @return array{apiKeyConfigured: bool, channelConfigured: bool, oauthCredentialsConfigured: bool, redirectUriVerified: bool, oauthConnected: bool}
	 */
	private function get_youtube_auth_setup_status(): array {
		$oauth_connected = YouTube_OAuth::is_connected();

		return array(
			'apiKeyConfigured'           => self::is_valid_youtube_api_key( (string) get_option( 'wttba_youtube_api_key', '' ) ),
			'channelConfigured'          => self::is_valid_youtube_channel_id( (string) get_option( 'wttba_youtube_channel_id', '' ) ),
			'oauthCredentialsConfigured' => YouTube_OAuth::has_credentials(),
			'redirectUriVerified'        => YouTube_OAuth::is_redirect_uri_verified(),
			'oauthConnected'             => $oauth_connected,
		);
	}

	/**
	 * Determine whether all YouTube authentication setup steps are complete.
	 *
	 * @param array{apiKeyConfigured: bool, channelConfigured: bool, oauthCredentialsConfigured: bool, redirectUriVerified: bool, oauthConnected: bool} $status Setup status.
	 * @return bool
	 */
	private function is_youtube_auth_setup_complete( array $status ): bool {
		return ! in_array( false, $status, true );
	}

	/**
	 * Render one setup checklist item.
	 *
	 * @param string $step        Stable step key for tests and styling.
	 * @param string $label       Step label.
	 * @param bool   $is_complete Whether the step is complete.
	 * @param string $description Step description.
	 */
	private function render_auth_setup_item( string $step, string $label, bool $is_complete, string $description ): void {
		$status_key   = $is_complete ? 'complete' : 'missing';
		$status_label = $is_complete ? __( 'Done', 'wp-tube-to-blog-ai' ) : __( 'Pending', 'wp-tube-to-blog-ai' );
		$icon_class   = $is_complete ? 'dashicons dashicons-yes-alt' : 'dashicons dashicons-no-alt';
		?>
		<li
			class="wttba-auth-checklist__item wttba-auth-checklist__item--<?php echo esc_attr( $status_key ); ?>"
			data-step="<?php echo esc_attr( $step ); ?>"
			data-status="<?php echo esc_attr( $status_key ); ?>"
		>
			<span class="wttba-auth-checklist__icon <?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></span>
			<span class="wttba-auth-checklist__body">
				<strong class="wttba-auth-checklist__label"><?php echo esc_html( $label ); ?></strong>:
				<span class="wttba-auth-checklist__status"><?php echo esc_html( $status_label ); ?></span>
				<?php if ( ! $is_complete ) : ?>
					<span class="description wttba-auth-checklist__description"><?php echo esc_html( $description ); ?></span>
				<?php endif; ?>
			</span>
		</li>
		<?php
	}

	/**
	 * Content section description.
	 */
	public function render_content_section(): void {
		echo '<p>' . esc_html__( 'Configure the default language and style for generated content. You can override these settings for individual video or audio generations.', 'wp-tube-to-blog-ai' ) . '</p>';
	}

	/**
	 * Render Post Length field.
	 */
	public function render_post_length_field(): void {
		$value   = $this->sanitize_post_length( (string) get_option( 'wttba_post_length', self::DEFAULT_POST_LENGTH ) );
		$options = self::get_post_length_options();
		?>
		<select id="wttba_post_length" name="wttba_post_length">
			<?php foreach ( $options as $length => $option ) : ?>
				<option value="<?php echo esc_attr( $length ); ?>" <?php selected( $value, $length ); ?>>
					<?php echo esc_html( $option['label'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Controls the target article length and AI token budget for generated posts.', 'wp-tube-to-blog-ai' ); ?>
		</p>
		<ul class="description wttba-settings-option-help">
			<?php foreach ( $options as $option ) : ?>
				<li><?php echo esc_html( $option['label'] . ': ' . $option['description'] ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * AI Provider section description.
	 */
	public function render_ai_section(): void {
		$config_url = AI_Provider_Status::get_configuration_url();
		$localhost  = self::get_localhost_status();
		$message    = AI_Provider_Status::is_text_generation_supported()
			? __( 'A text-generation AI provider is available. Provider credentials are managed by WordPress Connectors, not by this plugin.', 'wp-tube-to-blog-ai' )
			: AI_Provider_Status::get_unavailable_message();
		?>
		<p><?php echo esc_html( $message ); ?></p>
		<ul>
			<li><?php echo esc_html( AI_Provider_Status::is_audio_input_generation_supported() ? __( 'Audio-to-post generation is available.', 'wp-tube-to-blog-ai' ) : __( 'Audio-to-post generation requires an AI provider with audio input support.', 'wp-tube-to-blog-ai' ) ); ?></li>
			<li><?php echo esc_html( AI_Provider_Status::is_text_to_speech_supported() ? __( 'Post-to-audio generation is available.', 'wp-tube-to-blog-ai' ) : __( 'Post-to-audio generation requires an AI provider with text-to-speech support.', 'wp-tube-to-blog-ai' ) ); ?></li>
		</ul>
		<p>
			<a href="<?php echo esc_url( $config_url ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Manage AI Providers', 'wp-tube-to-blog-ai' ); ?>
			</a>
		</p>
		<div id="wttba-ai-test" class="wttba-ai-test">
			<h3><?php esc_html_e( 'Connection Test', 'wp-tube-to-blog-ai' ); ?></h3>
			<p><?php esc_html_e( 'Run a quick text generation to confirm the configured AI provider can respond from this WordPress site.', 'wp-tube-to-blog-ai' ); ?></p>
			<p>
				<button type="button" class="button button-secondary" id="wttba-ai-test-button">
					<?php esc_html_e( 'Test AI Connection', 'wp-tube-to-blog-ai' ); ?>
				</button>
				<span class="spinner" id="wttba-ai-test-spinner"></span>
			</p>
			<div id="wttba-ai-test-result" aria-live="polite"></div>
			<div id="wttba-ai-test-sample" class="notice notice-info inline" hidden></div>
		</div>
		<h3><?php esc_html_e( 'Localhost Compatibility', 'wp-tube-to-blog-ai' ); ?></h3>
		<p><?php echo esc_html( $localhost['message'] ); ?></p>
		<p class="description">
			<?php
			printf(
				/* translators: %s: current site host. */
				esc_html__( 'Current detected host: %s', 'wp-tube-to-blog-ai' ),
				esc_html( '' !== $localhost['host'] ? $localhost['host'] : __( 'unknown', 'wp-tube-to-blog-ai' ) )
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render Preferred AI Model field.
	 */
	public function render_ai_model_field(): void {
		$value   = $this->sanitize_ai_model( (string) get_option( 'wttba_ai_model', '' ) );
		$options = self::get_ai_model_options();
		?>
		<select id="wttba_ai_model" name="wttba_ai_model">
			<?php foreach ( $options as $model_id => $label ) : ?>
				<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $value, $model_id ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'This is a preference passed to the WordPress AI Client. If the selected model is unavailable, the AI Client can use another compatible configured model.', 'wp-tube-to-blog-ai' ); ?>
		</p>
		<?php
	}

	/**
	 * Render recent AI usage summary.
	 */
	public function render_usage_section(): void {
		$entries = Generation_Logger::get_recent_entries( 10 );

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'No AI generations have been recorded yet.', 'wp-tube-to-blog-ai' ) . '</p>';
			return;
		}
		?>
		<p><?php esc_html_e( 'Recent AI generations are recorded to help administrators review feature usage and token consumption.', 'wp-tube-to-blog-ai' ); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'wp-tube-to-blog-ai' ); ?></th>
					<th><?php esc_html_e( 'Source', 'wp-tube-to-blog-ai' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-tube-to-blog-ai' ); ?></th>
					<th><?php esc_html_e( 'Provider', 'wp-tube-to-blog-ai' ); ?></th>
					<th><?php esc_html_e( 'Model', 'wp-tube-to-blog-ai' ); ?></th>
					<th><?php esc_html_e( 'Tokens', 'wp-tube-to-blog-ai' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( (string) ( $entry['generated_at'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $entry['source_type'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $entry['status'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $entry['provider']['name'] ?? $entry['provider']['id'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $entry['model']['name'] ?? $entry['model']['id'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( isset( $entry['token_usage']['totalTokens'] ) ? number_format_i18n( (int) $entry['token_usage']['totalTokens'] ) : '-' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render YouTube API Key field.
	 */
	public function render_api_key_field(): void {
		$value = get_option( 'wttba_youtube_api_key', '' );
		?>
		<input
			type="password"
			id="wttba_youtube_api_key"
			name="wttba_youtube_api_key"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			autocomplete="off"
		/>
		<p class="description">
			<?php esc_html_e( 'Your YouTube Data API v3 key. Get one from the Google Cloud Console.', 'wp-tube-to-blog-ai' ); ?>
		</p>
		<?php
	}

	/**
	 * Render Channel ID field.
	 */
	public function render_channel_id_field(): void {
		$value = get_option( 'wttba_youtube_channel_id', '' );
		?>
		<input
			type="text"
			id="wttba_youtube_channel_id"
			name="wttba_youtube_channel_id"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Your YouTube Channel ID (e.g., UCxxxxxxxxxxxxxxxx).', 'wp-tube-to-blog-ai' ); ?>
		</p>
		<?php
	}

	/**
	 * Render OAuth Client ID field.
	 */
	public function render_oauth_client_id_field(): void {
		$value = get_option( 'wttba_youtube_oauth_client_id', '' );
		?>
		<input
			type="text"
			id="wttba_youtube_oauth_client_id"
			name="wttba_youtube_oauth_client_id"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			autocomplete="off"
		/>
		<p class="description">
			<?php esc_html_e( 'Create a Web application OAuth client in Google Cloud and paste its client ID here.', 'wp-tube-to-blog-ai' ); ?>
		</p>
		<?php
	}

	/**
	 * Render OAuth Client Secret field.
	 */
	public function render_oauth_client_secret_field(): void {
		$value = get_option( 'wttba_youtube_oauth_client_secret', '' );
		?>
		<input
			type="password"
			id="wttba_youtube_oauth_client_secret"
			name="wttba_youtube_oauth_client_secret"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			autocomplete="off"
		/>
		<p class="description">
			<?php esc_html_e( 'Store this only on trusted WordPress environments. Google shows the client secret only when the OAuth client is created.', 'wp-tube-to-blog-ai' ); ?>
		</p>
		<?php
	}

	/**
	 * Render OAuth connection controls.
	 */
	public function render_oauth_connection_field(): void {
		$connect_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wttba_youtube_oauth_connect' ),
			'wttba_youtube_oauth_connect'
		);
		$disconnect_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wttba_youtube_oauth_disconnect' ),
			'wttba_youtube_oauth_disconnect'
		);
		$is_connected   = YouTube_OAuth::is_connected();
		$has_credentials = YouTube_OAuth::has_credentials();
		$status_message  = __( 'OAuth credentials have not been saved yet.', 'wp-tube-to-blog-ai' );

		if ( $is_connected && $has_credentials ) {
			$status_message = __( 'YouTube is connected.', 'wp-tube-to-blog-ai' );
		} elseif ( $is_connected ) {
			$status_message = __( 'YouTube is connected, but OAuth client credentials are missing. Save them before reconnecting.', 'wp-tube-to-blog-ai' );
		} elseif ( $has_credentials ) {
			$status_message = __( 'OAuth credentials are saved. You can connect YouTube.', 'wp-tube-to-blog-ai' );
		}
		?>
		<p>
			<label for="wttba_youtube_oauth_redirect_uri">
				<?php esc_html_e( 'Authorized redirect URI', 'wp-tube-to-blog-ai' ); ?>
			</label>
		</p>
		<input
			type="text"
			id="wttba_youtube_oauth_redirect_uri"
			value="<?php echo esc_attr( YouTube_OAuth::get_redirect_uri() ); ?>"
			class="large-text code"
			readonly
		/>
		<p class="description">
			<?php esc_html_e( 'Add this exact URI to the OAuth client in Google Cloud before connecting.', 'wp-tube-to-blog-ai' ); ?>
		</p>
		<p>
			<strong><?php echo esc_html( $status_message ); ?></strong>
		</p>
		<p>
			<?php if ( $has_credentials ) : ?>
				<a href="<?php echo esc_url( $connect_url ); ?>" class="button button-secondary">
					<?php echo esc_html( $is_connected ? __( 'Reconnect YouTube', 'wp-tube-to-blog-ai' ) : __( 'Connect YouTube', 'wp-tube-to-blog-ai' ) ); ?>
				</a>
			<?php else : ?>
				<button type="button" class="button button-secondary" disabled>
					<?php esc_html_e( 'Save OAuth credentials first', 'wp-tube-to-blog-ai' ); ?>
				</button>
			<?php endif; ?>
			<?php if ( $is_connected ) : ?>
				<a href="<?php echo esc_url( $disconnect_url ); ?>" class="button button-link-delete">
					<?php esc_html_e( 'Disconnect YouTube', 'wp-tube-to-blog-ai' ); ?>
				</a>
			<?php endif; ?>
		</p>
		<p class="description">
			<?php echo esc_html( $has_credentials ? __( 'The connected account must be able to edit the videos whose captions you want to use.', 'wp-tube-to-blog-ai' ) : __( 'Enter the OAuth Client ID and Client Secret, save changes, then return here to connect YouTube.', 'wp-tube-to-blog-ai' ) ); ?>
		</p>
		<?php
	}

	/**
	 * Render Writing Persona textarea.
	 */
	public function render_persona_field(): void {
		$value = get_option( 'wttba_default_persona', '' );
		?>
		<textarea
			id="wttba_default_persona"
			name="wttba_default_persona"
			rows="6"
			class="large-text"
		><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Describe the writing style for generated posts (e.g., tone, structure, audience). This will be used as default guidance for the AI. Leave empty for a generic professional tone.', 'wp-tube-to-blog-ai' ); ?>
		</p>
		<?php
	}

	/**
	 * Render Default Language dropdown.
	 */
	public function render_language_field(): void {
		$current = get_option( 'wttba_default_language', 'en' );
		?>
		<select id="wttba_default_language" name="wttba_default_language">
			<?php foreach ( self::LANGUAGES as $code => $label ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current, $code ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Determine whether a host is a local development host.
	 *
	 * @param string $host Host name.
	 * @return bool
	 */
	private static function is_localhost_host( string $host ): bool {
		$host = trim( strtolower( $host ), '[]' );

		return in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true )
			|| str_ends_with( $host, '.localhost' )
			|| str_ends_with( $host, '.test' )
			|| str_ends_with( $host, '.local' );
	}

	/**
	 * Render a notice after OAuth actions redirect back to settings.
	 */
	private function render_oauth_status_notice(): void {
		if ( empty( $_GET['wttba_youtube_oauth'] ) ) {
			return;
		}

		$status = sanitize_key( wp_unslash( $_GET['wttba_youtube_oauth'] ) );

		$messages = array(
			'connected'           => array( 'success', __( 'YouTube OAuth is connected.', 'wp-tube-to-blog-ai' ) ),
			'disconnected'        => array( 'success', __( 'YouTube OAuth has been disconnected.', 'wp-tube-to-blog-ai' ) ),
			'missing_credentials' => array( 'error', __( 'Save the OAuth client ID and client secret before connecting YouTube.', 'wp-tube-to-blog-ai' ) ),
			'invalid_state'       => array( 'error', __( 'The OAuth callback could not be verified. Please try connecting again.', 'wp-tube-to-blog-ai' ) ),
			'missing_code'        => array( 'error', __( 'Google did not return an authorization code. Please try connecting again.', 'wp-tube-to-blog-ai' ) ),
			'wttba_youtube_oauth_failed' => array( 'error', __( 'The OAuth token exchange failed. Check the OAuth client configuration and try reconnecting.', 'wp-tube-to-blog-ai' ) ),
			'access_denied'       => array( 'error', __( 'YouTube OAuth access was denied.', 'wp-tube-to-blog-ai' ) ),
			'redirect_uri_mismatch' => array(
				'error',
				sprintf(
					/* translators: %s: OAuth redirect URI. */
					__( 'Google rejected the redirect URI. Add this exact Authorized redirect URI to the OAuth client in Google Cloud: %s', 'wp-tube-to-blog-ai' ),
					YouTube_OAuth::get_redirect_uri()
				),
			),
			'invalid_client'      => array( 'error', __( 'Google rejected the OAuth client. Check the saved Client ID and Client Secret, then reconnect YouTube.', 'wp-tube-to-blog-ai' ) ),
			'unauthorized_client' => array( 'error', __( 'Google rejected this OAuth client for the requested YouTube scope. Check the OAuth consent screen and client type in Google Cloud.', 'wp-tube-to-blog-ai' ) ),
		);

		$notice = $messages[ $status ] ?? array( 'error', __( 'YouTube OAuth could not be completed. Please try again.', 'wp-tube-to-blog-ai' ) );
		?>
		<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice[1] ); ?></p>
		</div>
		<?php
	}
}
