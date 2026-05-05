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
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// YouTube Channel ID.
		register_setting(
			'wttba_settings',
			'wttba_youtube_channel_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// YouTube OAuth Client ID.
		register_setting(
			'wttba_settings',
			'wttba_youtube_oauth_client_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// YouTube OAuth Client Secret.
		register_setting(
			'wttba_settings',
			'wttba_youtube_oauth_client_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
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

		// YouTube section.
		add_settings_section(
			'wttba_youtube_section',
			__( 'YouTube Integration', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_youtube_section' ),
			'wttba-settings'
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
			'wttba-settings'
		);

		add_settings_field(
			'wttba_default_language',
			__( 'Default Output Language', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_language_field' ),
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
			'wttba-settings'
		);

		add_settings_section(
			'wttba_usage_section',
			__( 'AI Usage', 'wp-tube-to-blog-ai' ),
			array( $this, 'render_usage_section' ),
			'wttba-settings'
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
			)
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Content Suite Settings', 'wp-tube-to-blog-ai' ); ?></h1>
			<?php $this->render_oauth_status_notice(); ?>
			<?php Admin_Navigation::render( 'settings' ); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wttba_settings' );
				do_settings_sections( 'wttba-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * YouTube section description.
	 */
	public function render_youtube_section(): void {
		$status = $this->get_youtube_auth_setup_status();
		?>
		<p><?php esc_html_e( 'Enter your YouTube Data API v3 credentials to connect your channel. OAuth is used to download captions through the official YouTube Captions API for videos the connected account can edit.', 'wp-tube-to-blog-ai' ); ?></p>
		<div class="notice notice-info inline wttba-auth-checklist-notice">
			<p><strong><?php esc_html_e( 'YouTube authentication setup', 'wp-tube-to-blog-ai' ); ?></strong></p>
			<ul class="wttba-auth-checklist">
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
			'apiKeyConfigured'           => '' !== trim( (string) get_option( 'wttba_youtube_api_key', '' ) ),
			'channelConfigured'          => '' !== trim( (string) get_option( 'wttba_youtube_channel_id', '' ) ),
			'oauthCredentialsConfigured' => YouTube_OAuth::has_credentials(),
			'redirectUriVerified'        => YouTube_OAuth::is_redirect_uri_verified(),
			'oauthConnected'             => $oauth_connected,
		);
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
				<span class="description wttba-auth-checklist__description"><?php echo esc_html( $description ); ?></span>
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
