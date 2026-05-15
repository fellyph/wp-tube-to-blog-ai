<?php
/**
 * Plugin settings page.
 *
 * @package CreatorStack_AI
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
	 * Feature key for YouTube-to-post generation.
	 */
	public const FEATURE_YOUTUBE_TO_POST = 'youtube_to_post';

	/**
	 * Feature key for audio-to-post generation.
	 */
	public const FEATURE_AUDIO_TO_POST = 'audio_to_post';

	/**
	 * Feature key for post-to-audio generation.
	 */
	public const FEATURE_POST_TO_AUDIO = 'post_to_audio';

	/**
	 * Feature option map.
	 *
	 * @var array<string, array{option: string, default: bool, jsKey: string}>
	 */
	private const FEATURE_OPTION_MAP = array(
		self::FEATURE_YOUTUBE_TO_POST => array(
			'option'  => 'wttba_feature_youtube_to_post',
			'default' => true,
			'jsKey'   => 'youtubeToPost',
		),
		self::FEATURE_AUDIO_TO_POST   => array(
			'option'  => 'wttba_feature_audio_to_post',
			'default' => true,
			'jsKey'   => 'audioToPost',
		),
		self::FEATURE_POST_TO_AUDIO   => array(
			'option'  => 'wttba_feature_post_to_audio',
			'default' => false,
			'jsKey'   => 'postToAudio',
		),
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
			__( 'CreatorStack AI', 'creatorstack-ai' ),
			__( 'CreatorStack AI', 'creatorstack-ai' ),
			'manage_options',
			'wttba-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public function register_settings(): void {
		foreach ( self::FEATURE_OPTION_MAP as $feature ) {
			register_setting(
				'wttba_settings',
				$feature['option'],
				array(
					'type'              => 'boolean',
					'sanitize_callback' => array( $this, 'sanitize_feature_enabled' ),
					'default'           => $feature['default'],
				)
			);
		}

		// YouTube Channel ID.
		register_setting(
			'wttba_settings',
			YouTube_Connector::CHANNEL_ID_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_youtube_channel_id' ),
				'default'           => '',
			)
		);

		// YouTube OAuth Client ID.
		register_setting(
			'wttba_settings',
			YouTube_Connector::OAUTH_CLIENT_ID_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_oauth_client_id' ),
				'default'           => '',
			)
		);

		// YouTube OAuth Client Secret.
		register_setting(
			'wttba_settings',
			YouTube_Connector::OAUTH_CLIENT_SECRET_OPTION,
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

		// Features section.
		add_settings_section(
			'wttba_features_section',
			__( 'Enabled Functionality', 'creatorstack-ai' ),
			array( $this, 'render_features_section' ),
			'wttba-settings',
			$this->get_settings_section_args( 'features' )
		);

		add_settings_field(
			'wttba_enabled_features',
			__( 'Available workflows', 'creatorstack-ai' ),
			array( $this, 'render_features_field' ),
			'wttba-settings',
			'wttba_features_section'
		);

		// YouTube section.
		add_settings_section(
			'wttba_youtube_section',
			__( 'YouTube Integration', 'creatorstack-ai' ),
			array( $this, 'render_youtube_section' ),
			'wttba-settings',
			$this->get_settings_section_args( 'youtube' )
		);

		add_settings_field(
			'wttba_youtube_connector',
			__( 'YouTube API Key', 'creatorstack-ai' ),
			array( $this, 'render_youtube_connector_field' ),
			'wttba-settings',
			'wttba_youtube_section'
		);

		add_settings_field(
			YouTube_Connector::CHANNEL_ID_OPTION,
			__( 'YouTube Channel ID', 'creatorstack-ai' ),
			array( $this, 'render_channel_id_field' ),
			'wttba-settings',
			'wttba_youtube_section'
		);

		add_settings_field(
			YouTube_Connector::OAUTH_CLIENT_ID_OPTION,
			__( 'OAuth Client ID', 'creatorstack-ai' ),
			array( $this, 'render_oauth_client_id_field' ),
			'wttba-settings',
			'wttba_youtube_section'
		);

		add_settings_field(
			YouTube_Connector::OAUTH_CLIENT_SECRET_OPTION,
			__( 'OAuth Client Secret', 'creatorstack-ai' ),
			array( $this, 'render_oauth_client_secret_field' ),
			'wttba-settings',
			'wttba_youtube_section'
		);

		add_settings_field(
			'wttba_youtube_oauth_connection',
			__( 'OAuth Connection', 'creatorstack-ai' ),
			array( $this, 'render_oauth_connection_field' ),
			'wttba-settings',
			'wttba_youtube_section'
		);

		// Content section.
		add_settings_section(
			'wttba_content_section',
			__( 'Content Settings', 'creatorstack-ai' ),
			array( $this, 'render_content_section' ),
			'wttba-settings',
			$this->get_settings_section_args( 'content' )
		);

		add_settings_field(
			'wttba_default_language',
			__( 'Default Output Language', 'creatorstack-ai' ),
			array( $this, 'render_language_field' ),
			'wttba-settings',
			'wttba_content_section'
		);

		add_settings_field(
			'wttba_post_length',
			__( 'Post Length', 'creatorstack-ai' ),
			array( $this, 'render_post_length_field' ),
			'wttba-settings',
			'wttba_content_section'
		);

		add_settings_field(
			'wttba_default_persona',
			__( 'Writing Persona', 'creatorstack-ai' ),
			array( $this, 'render_persona_field' ),
			'wttba-settings',
			'wttba_content_section'
		);

		// AI Provider section.
		add_settings_section(
			'wttba_ai_section',
			__( 'AI Provider', 'creatorstack-ai' ),
			array( $this, 'render_ai_section' ),
			'wttba-settings',
			$this->get_settings_section_args( 'ai-provider' )
		);

		add_settings_field(
			'wttba_ai_model',
			__( 'Preferred AI Model', 'creatorstack-ai' ),
			array( $this, 'render_ai_model_field' ),
			'wttba-settings',
			'wttba_ai_section'
		);

		add_settings_section(
			'wttba_usage_section',
			__( 'AI Usage', 'creatorstack-ai' ),
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
	 * Get configurable feature definitions.
	 *
	 * @return array<string, array{option: string, default: bool, jsKey: string, label: string, description: string}>
	 */
	public static function get_feature_definitions(): array {
		return array(
			self::FEATURE_YOUTUBE_TO_POST => array(
				'option'      => self::FEATURE_OPTION_MAP[ self::FEATURE_YOUTUBE_TO_POST ]['option'],
				'default'     => self::FEATURE_OPTION_MAP[ self::FEATURE_YOUTUBE_TO_POST ]['default'],
				'jsKey'       => self::FEATURE_OPTION_MAP[ self::FEATURE_YOUTUBE_TO_POST ]['jsKey'],
				'label'       => __( 'YouTube to Post', 'creatorstack-ai' ),
				'description' => __( 'Browse channel videos, generate posts from YouTube transcripts, and create draft articles.', 'creatorstack-ai' ),
			),
			self::FEATURE_AUDIO_TO_POST   => array(
				'option'      => self::FEATURE_OPTION_MAP[ self::FEATURE_AUDIO_TO_POST ]['option'],
				'default'     => self::FEATURE_OPTION_MAP[ self::FEATURE_AUDIO_TO_POST ]['default'],
				'jsKey'       => self::FEATURE_OPTION_MAP[ self::FEATURE_AUDIO_TO_POST ]['jsKey'],
				'label'       => __( 'Audio to Post', 'creatorstack-ai' ),
				'description' => __( 'Record or select audio, transcribe it with AI, and generate draft posts from spoken content.', 'creatorstack-ai' ),
			),
			self::FEATURE_POST_TO_AUDIO   => array(
				'option'      => self::FEATURE_OPTION_MAP[ self::FEATURE_POST_TO_AUDIO ]['option'],
				'default'     => self::FEATURE_OPTION_MAP[ self::FEATURE_POST_TO_AUDIO ]['default'],
				'jsKey'       => self::FEATURE_OPTION_MAP[ self::FEATURE_POST_TO_AUDIO ]['jsKey'],
				'label'       => __( 'Post to Audio', 'creatorstack-ai' ),
				'description' => __( 'Generate narrated audio from post content and attach the result to the post.', 'creatorstack-ai' ),
			),
		);
	}

	/**
	 * Sanitize feature toggle values from the settings form.
	 *
	 * @param mixed $value Submitted value.
	 * @return bool
	 */
	public function sanitize_feature_enabled( mixed $value ): bool {
		return rest_sanitize_boolean( $value );
	}

	/**
	 * Check whether a feature is enabled.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public static function is_feature_enabled( string $feature ): bool {
		$definition = self::get_feature_definitions()[ $feature ] ?? null;

		if ( null === $definition ) {
			return false;
		}

		return rest_sanitize_boolean(
			get_option(
				$definition['option'],
				$definition['default'] ? '1' : '0'
			)
		);
	}

	/**
	 * Check whether YouTube-to-post functionality is enabled.
	 */
	public static function is_youtube_to_post_enabled(): bool {
		return self::is_feature_enabled( self::FEATURE_YOUTUBE_TO_POST );
	}

	/**
	 * Check whether audio-to-post functionality is enabled.
	 */
	public static function is_audio_to_post_enabled(): bool {
		return self::is_feature_enabled( self::FEATURE_AUDIO_TO_POST );
	}

	/**
	 * Check whether post-to-audio functionality is enabled.
	 */
	public static function is_post_to_audio_enabled(): bool {
		return self::is_feature_enabled( self::FEATURE_POST_TO_AUDIO );
	}

	/**
	 * Get feature states for JavaScript configuration and REST responses.
	 *
	 * @return array<string, bool>
	 */
	public static function get_feature_states(): array {
		$states = array();

		foreach ( self::get_feature_definitions() as $key => $definition ) {
			$states[ $definition['jsKey'] ] = self::is_feature_enabled( $key );
		}

		return $states;
	}

	/**
	 * Get the human-readable label for a feature.
	 *
	 * @param string $feature Feature key.
	 * @return string
	 */
	public static function get_feature_label( string $feature ): string {
		$definition = self::get_feature_definitions()[ $feature ] ?? null;

		return null === $definition ? __( 'This feature', 'creatorstack-ai' ) : $definition['label'];
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
			''                                => __( 'Automatic (recommended)', 'creatorstack-ai' ),
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
				'label'       => __( 'Short', 'creatorstack-ai' ),
				'description' => __( 'About 600 to 900 words.', 'creatorstack-ai' ),
			),
			'medium' => array(
				'label'       => __( 'Medium', 'creatorstack-ai' ),
				'description' => __( 'About 1,000 to 1,500 words.', 'creatorstack-ai' ),
			),
			'long'   => array(
				'label'       => __( 'Long', 'creatorstack-ai' ),
				'description' => __( 'About 1,800 to 2,500 words.', 'creatorstack-ai' ),
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
				'instruction' => __( 'Aim for a concise post of about 600 to 900 words. Focus on the core takeaways and avoid extended background.', 'creatorstack-ai' ),
			);
		}

		if ( 'long' === $length ) {
			return array(
				'max_tokens'  => 8000,
				'instruction' => __( 'Aim for a detailed post of about 1,800 to 2,500 words. Expand important sections with context, examples, and practical takeaways.', 'creatorstack-ai' ),
			);
		}

		return array(
			'max_tokens'  => 5500,
			'instruction' => __( 'Aim for a balanced post of about 1,000 to 1,500 words. Cover the main ideas with enough context and examples.', 'creatorstack-ai' ),
		);
	}

	/**
	 * Sanitize and validate the YouTube Channel ID option.
	 *
	 * @param string $value Submitted Channel ID.
	 * @return string
	 */
	public function sanitize_youtube_channel_id( string $value ): string {
		$value = sanitize_text_field( trim( $value ) );

		if ( '' === $value || YouTube_Connector::is_valid_channel_id( $value ) ) {
			return $value;
		}

		add_settings_error(
			YouTube_Connector::CHANNEL_ID_OPTION,
			'wttba_youtube_channel_id_invalid',
			__( 'Enter a valid YouTube Channel ID. Channel IDs start with UC followed by 22 characters.', 'creatorstack-ai' )
		);

		return $this->get_previous_valid_option( YouTube_Connector::CHANNEL_ID_OPTION, array( YouTube_Connector::class, 'is_valid_channel_id' ) );
	}

	/**
	 * Sanitize and validate the OAuth client ID option.
	 *
	 * @param string $value Submitted OAuth client ID.
	 * @return string
	 */
	public function sanitize_oauth_client_id( string $value ): string {
		$value = sanitize_text_field( trim( $value ) );

		if ( '' === $value || YouTube_Connector::is_valid_oauth_client_id( $value ) ) {
			return $value;
		}

		add_settings_error(
			YouTube_Connector::OAUTH_CLIENT_ID_OPTION,
			'wttba_youtube_oauth_client_id_invalid',
			__( 'Enter a valid Google OAuth Web application Client ID ending in .apps.googleusercontent.com.', 'creatorstack-ai' )
		);

		return $this->get_previous_valid_option( YouTube_Connector::OAUTH_CLIENT_ID_OPTION, array( YouTube_Connector::class, 'is_valid_oauth_client_id' ) );
	}

	/**
	 * Sanitize and validate the OAuth client secret option.
	 *
	 * @param string $value Submitted OAuth client secret.
	 * @return string
	 */
	public function sanitize_oauth_client_secret( string $value ): string {
		$value = sanitize_text_field( trim( $value ) );

		if ( '' === $value || YouTube_Connector::is_valid_oauth_client_secret( $value ) ) {
			return $value;
		}

		add_settings_error(
			YouTube_Connector::OAUTH_CLIENT_SECRET_OPTION,
			'wttba_youtube_oauth_client_secret_invalid',
			__( 'Enter a valid Google OAuth Client Secret from the Web application client.', 'creatorstack-ai' )
		);

		return $this->get_previous_valid_option( YouTube_Connector::OAUTH_CLIENT_SECRET_OPTION, array( YouTube_Connector::class, 'is_valid_oauth_client_secret' ) );
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
			? __( 'This site is running locally. The plugin can run on localhost when WordPress can make outbound HTTPS requests to YouTube and the configured AI provider.', 'creatorstack-ai' )
			: __( 'This site is not currently using a localhost URL. Localhost is supported for development when outbound HTTPS requests and provider credentials are available.', 'creatorstack-ai' );

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
			'creatorstack-ai',
			WTTBA_PLUGIN_DIR . 'languages'
		);

		wp_localize_script(
			'wttba-settings',
			'wttbaSettingsConfig',
			array(
				'testPath'          => '/wttba/v1/ai/test',
				'configurationUrl'  => AI_Provider_Status::get_configuration_url(),
				'localhost'         => self::get_localhost_status(),
				'configurationLabel' => __( 'Configure AI Provider', 'creatorstack-ai' ),
				'redirectUri'       => YouTube_OAuth::get_redirect_uri(),
				'youtube'           => YouTube_Connector::get_admin_config(),
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
						<p class="wttba-settings-eyebrow"><?php esc_html_e( 'CreatorStack AI', 'creatorstack-ai' ); ?></p>
						<h1><?php esc_html_e( 'CreatorStack AI Settings', 'creatorstack-ai' ); ?></h1>
						<p class="wttba-settings-hero__description"><?php esc_html_e( 'Enable creator workflows, connect YouTube, tune generated posts, and verify your AI provider from one focused workspace.', 'creatorstack-ai' ); ?></p>
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
	 * Feature section description.
	 */
	public function render_features_section(): void {
		echo '<p>' . esc_html__( 'Choose which CreatorStack AI workflows are available in the WordPress admin, editor sidebar, dashboard, and REST API.', 'creatorstack-ai' ) . '</p>';
	}

	/**
	 * Render feature toggles.
	 */
	public function render_features_field(): void {
		?>
		<div class="wttba-feature-toggles" role="group" aria-label="<?php esc_attr_e( 'Available CreatorStack AI workflows', 'creatorstack-ai' ); ?>">
			<?php foreach ( self::get_feature_definitions() as $key => $definition ) : ?>
				<?php
				$enabled  = self::is_feature_enabled( $key );
				$input_id = $definition['option'];
				?>
				<label class="wttba-feature-toggle" for="<?php echo esc_attr( $input_id ); ?>">
					<input
						type="hidden"
						name="<?php echo esc_attr( $definition['option'] ); ?>"
						value="0"
					/>
					<input
						type="checkbox"
						id="<?php echo esc_attr( $input_id ); ?>"
						name="<?php echo esc_attr( $definition['option'] ); ?>"
						value="1"
						<?php checked( $enabled ); ?>
					/>
					<span class="wttba-feature-toggle__body">
						<span class="wttba-feature-toggle__header">
							<span class="wttba-feature-toggle__title"><?php echo esc_html( $definition['label'] ); ?></span>
						</span>
						<span class="wttba-feature-toggle__description"><?php echo esc_html( $definition['description'] ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
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
			<p><?php esc_html_e( 'YouTube authentication is configured. Update the API key from Settings > Connectors, and update channel or OAuth details below when they change.', 'creatorstack-ai' ); ?></p>
			<?php
			return;
		}
		?>
		<p><?php esc_html_e( 'Configure the YouTube connector API key, channel ID, and OAuth details used by CreatorStack AI. OAuth is used to download captions through the official YouTube Captions API for videos the connected account can edit.', 'creatorstack-ai' ); ?></p>
		<div class="notice notice-info inline wttba-auth-checklist-notice">
			<p><strong><?php esc_html_e( 'YouTube authentication setup', 'creatorstack-ai' ); ?></strong></p>
			<ul class="wttba-auth-checklist" aria-label="<?php esc_attr_e( 'YouTube authentication setup status', 'creatorstack-ai' ); ?>">
				<?php
				$this->render_auth_setup_item(
					'api-key',
					__( 'YouTube connector API key configured', 'creatorstack-ai' ),
					$status['apiKeyConfigured'],
					__( 'Required for listing channel videos and loading video details. Manage this in Settings > Connectors.', 'creatorstack-ai' )
				);
				$this->render_auth_setup_item(
					'channel-id',
					__( 'YouTube Channel ID saved', 'creatorstack-ai' ),
					$status['channelConfigured'],
					__( 'Required for browsing videos from the correct channel.', 'creatorstack-ai' )
				);
				$this->render_auth_setup_item(
					'oauth-credentials',
					__( 'OAuth client credentials saved', 'creatorstack-ai' ),
					$status['oauthCredentialsConfigured'],
					__( 'Required before WordPress can start the Google OAuth flow.', 'creatorstack-ai' )
				);
				$this->render_auth_setup_item(
					'redirect-uri',
					__( 'Authorized redirect URI verified', 'creatorstack-ai' ),
					$status['redirectUriVerified'],
					$status['redirectUriVerified']
						? __( 'Google returned to WordPress successfully, so the redirect URI is accepted.', 'creatorstack-ai' )
						: __( 'Verified after Google redirects back to WordPress. If Google reports redirect_uri_mismatch, copy the URI shown below into Google Cloud.', 'creatorstack-ai' )
				);
				$this->render_auth_setup_item(
					'youtube-account',
					__( 'YouTube account connected', 'creatorstack-ai' ),
					$status['oauthConnected'],
					__( 'Required for official caption downloads from editable videos.', 'creatorstack-ai' )
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
			<h3 id="wttba-oauth-wizard-title"><?php esc_html_e( 'YouTube setup wizard', 'creatorstack-ai' ); ?></h3>
			<p><?php esc_html_e( 'Use these steps to collect the YouTube and Google Cloud values WordPress needs for video listing, details, and official caption downloads.', 'creatorstack-ai' ); ?></p>
			<ol class="wttba-oauth-wizard__steps">
				<li class="wttba-oauth-wizard__step">
					<strong><?php esc_html_e( 'Prepare Google Cloud', 'creatorstack-ai' ); ?></strong>
					<p><?php esc_html_e( 'Open the Google Cloud project that owns the YouTube channel integration, then enable the YouTube Data API v3 if it is not already enabled.', 'creatorstack-ai' ); ?></p>
					<p>
						<a class="button button-secondary" href="https://console.cloud.google.com/apis/library/youtube.googleapis.com" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Open YouTube Data API v3', 'creatorstack-ai' ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'creatorstack-ai' ); ?></span>
						</a>
					</p>
				</li>
				<li class="wttba-oauth-wizard__step">
					<strong><?php esc_html_e( 'Configure the YouTube connector API key', 'creatorstack-ai' ); ?></strong>
					<p><?php esc_html_e( 'In Google Cloud, go to APIs & Services > Credentials and create an API key. Then add it to the YouTube connector in Settings > Connectors, or provide it with the YOUTUBE_DATA_API_KEY environment variable or PHP constant.', 'creatorstack-ai' ); ?></p>
					<p>
						<a class="button button-secondary" href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Open Google credentials', 'creatorstack-ai' ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'creatorstack-ai' ); ?></span>
						</a>
						<a class="button button-secondary" href="<?php echo esc_url( YouTube_Connector::get_connector_url() ); ?>">
							<?php esc_html_e( 'Manage YouTube connector', 'creatorstack-ai' ); ?>
						</a>
					</p>
				</li>
				<li class="wttba-oauth-wizard__step">
					<strong><?php esc_html_e( 'Find the YouTube Channel ID', 'creatorstack-ai' ); ?></strong>
					<p><?php esc_html_e( 'Open YouTube account advanced settings, copy the value labeled Channel ID, and paste it into the YouTube Channel ID field below. Channel IDs usually start with UC.', 'creatorstack-ai' ); ?></p>
					<p>
						<a class="button button-secondary" href="https://support.google.com/youtube/answer/3250431" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Find Channel ID help', 'creatorstack-ai' ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'creatorstack-ai' ); ?></span>
						</a>
					</p>
				</li>
				<li class="wttba-oauth-wizard__step">
					<strong><?php esc_html_e( 'Create a Web application OAuth client', 'creatorstack-ai' ); ?></strong>
					<p><?php esc_html_e( 'In Google Cloud, go to APIs & Services > Credentials, create an OAuth client ID, and choose Web application as the application type.', 'creatorstack-ai' ); ?></p>
					<p>
						<a class="button button-secondary" href="https://console.cloud.google.com/apis/credentials/oauthclient" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Create OAuth client', 'creatorstack-ai' ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'creatorstack-ai' ); ?></span>
						</a>
					</p>
				</li>
				<li class="wttba-oauth-wizard__step">
					<strong><?php esc_html_e( 'Add this Authorized redirect URI', 'creatorstack-ai' ); ?></strong>
					<p><?php esc_html_e( 'Paste this exact URI into the Authorized redirect URIs field for the Web application client. Google requires an exact match.', 'creatorstack-ai' ); ?></p>
					<div class="wttba-oauth-wizard__copy-row">
						<label class="screen-reader-text" for="wttba-oauth-wizard-redirect-uri">
							<?php esc_html_e( 'Authorized redirect URI', 'creatorstack-ai' ); ?>
						</label>
						<input
							type="text"
							id="wttba-oauth-wizard-redirect-uri"
							class="large-text code"
							value="<?php echo esc_attr( $redirect_uri ); ?>"
							readonly
						/>
						<button type="button" class="button button-secondary" id="wttba-copy-redirect-uri">
							<?php esc_html_e( 'Copy URI', 'creatorstack-ai' ); ?>
						</button>
					</div>
					<p id="wttba-copy-redirect-uri-status" class="description" aria-live="polite"></p>
				</li>
				<li class="wttba-oauth-wizard__step">
					<strong><?php esc_html_e( 'Download or copy the client secret JSON', 'creatorstack-ai' ); ?></strong>
					<p id="wttba-oauth-client-json-help"><?php esc_html_e( 'After Google creates the client, download the client_secret.json file or copy its contents. Paste it here to fill the Client ID and Client Secret fields below. The secret is only stored after you click Save Changes.', 'creatorstack-ai' ); ?></p>
					<textarea
						id="wttba-oauth-client-json"
						class="large-text code"
						rows="5"
						aria-describedby="wttba-oauth-client-json-help"
						placeholder="<?php esc_attr_e( 'Paste client_secret.json contents here', 'creatorstack-ai' ); ?>"
					></textarea>
					<p>
						<button type="button" class="button button-secondary" id="wttba-fill-oauth-fields">
							<?php esc_html_e( 'Fill OAuth fields', 'creatorstack-ai' ); ?>
						</button>
					</p>
					<p id="wttba-oauth-client-json-result" class="description" aria-live="polite"></p>
				</li>
				<li class="wttba-oauth-wizard__step">
					<strong><?php esc_html_e( 'Save, then connect YouTube', 'creatorstack-ai' ); ?></strong>
					<p><?php esc_html_e( 'Click Save Changes so WordPress stores the Client ID and Client Secret. After the page reloads, use Connect YouTube to finish the Google account consent flow.', 'creatorstack-ai' ); ?></p>
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
			'apiKeyConfigured'           => YouTube_Connector::is_api_key_configured(),
			'channelConfigured'          => YouTube_Connector::is_channel_configured(),
			'oauthCredentialsConfigured' => YouTube_Connector::has_oauth_credentials(),
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
		$status_label = $is_complete ? __( 'Done', 'creatorstack-ai' ) : __( 'Pending', 'creatorstack-ai' );
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
		echo '<p>' . esc_html__( 'Configure the default language and style for generated content. You can override these settings for individual video or audio generations.', 'creatorstack-ai' ) . '</p>';
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
			<?php esc_html_e( 'Controls the target article length and AI token budget for generated posts.', 'creatorstack-ai' ); ?>
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
			? __( 'A text-generation AI provider is available. Provider credentials are managed by WordPress Connectors, not by this plugin.', 'creatorstack-ai' )
			: AI_Provider_Status::get_unavailable_message();
		$audio_to_post_message = self::is_audio_to_post_enabled()
			? ( AI_Provider_Status::is_audio_input_generation_supported() ? __( 'Audio-to-post generation is available.', 'creatorstack-ai' ) : __( 'Audio-to-post generation requires an AI provider with audio input support.', 'creatorstack-ai' ) )
			: __( 'Audio-to-post generation is disabled in Enabled Functionality.', 'creatorstack-ai' );
		$post_to_audio_message = self::is_post_to_audio_enabled()
			? ( AI_Provider_Status::is_text_to_speech_supported() ? __( 'Post-to-audio generation is available.', 'creatorstack-ai' ) : __( 'Post-to-audio generation requires an AI provider with text-to-speech support.', 'creatorstack-ai' ) )
			: __( 'Post-to-audio generation is disabled in Enabled Functionality.', 'creatorstack-ai' );
		?>
		<p><?php echo esc_html( $message ); ?></p>
		<ul>
			<li><?php echo esc_html( $audio_to_post_message ); ?></li>
			<li><?php echo esc_html( $post_to_audio_message ); ?></li>
		</ul>
		<p>
			<a href="<?php echo esc_url( $config_url ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Manage AI Providers', 'creatorstack-ai' ); ?>
			</a>
		</p>
		<div id="wttba-ai-test" class="wttba-ai-test">
			<h3><?php esc_html_e( 'Connection Test', 'creatorstack-ai' ); ?></h3>
			<p><?php esc_html_e( 'Run a quick text generation to confirm the configured AI provider can respond from this WordPress site.', 'creatorstack-ai' ); ?></p>
			<p>
				<button type="button" class="button button-secondary" id="wttba-ai-test-button">
					<?php esc_html_e( 'Test AI Connection', 'creatorstack-ai' ); ?>
				</button>
				<span class="spinner" id="wttba-ai-test-spinner"></span>
			</p>
			<div id="wttba-ai-test-result" aria-live="polite"></div>
			<div id="wttba-ai-test-sample" class="notice notice-info inline" hidden></div>
		</div>
		<h3><?php esc_html_e( 'Localhost Compatibility', 'creatorstack-ai' ); ?></h3>
		<p><?php echo esc_html( $localhost['message'] ); ?></p>
		<p class="description">
			<?php
			printf(
				/* translators: %s: current site host. */
				esc_html__( 'Current detected host: %s', 'creatorstack-ai' ),
				esc_html( '' !== $localhost['host'] ? $localhost['host'] : __( 'unknown', 'creatorstack-ai' ) )
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
			<?php esc_html_e( 'This is a preference passed to the WordPress AI Client. If the selected model is unavailable, the AI Client can use another compatible configured model.', 'creatorstack-ai' ); ?>
		</p>
		<?php
	}

	/**
	 * Render recent AI usage summary.
	 */
	public function render_usage_section(): void {
		$entries = Generation_Logger::get_recent_entries( 10 );

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'No AI generations have been recorded yet.', 'creatorstack-ai' ) . '</p>';
			return;
		}
		?>
		<p><?php esc_html_e( 'Recent AI generations are recorded to help administrators review feature usage and token consumption.', 'creatorstack-ai' ); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'creatorstack-ai' ); ?></th>
					<th><?php esc_html_e( 'Source', 'creatorstack-ai' ); ?></th>
					<th><?php esc_html_e( 'Status', 'creatorstack-ai' ); ?></th>
					<th><?php esc_html_e( 'Provider', 'creatorstack-ai' ); ?></th>
					<th><?php esc_html_e( 'Model', 'creatorstack-ai' ); ?></th>
					<th><?php esc_html_e( 'Tokens', 'creatorstack-ai' ); ?></th>
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
	 * Render YouTube connector status.
	 */
	public function render_youtube_connector_field(): void {
		$is_configured = YouTube_Connector::is_api_key_configured();
		$source        = YouTube_Connector::get_api_key_source();
		$status        = $is_configured
			? sprintf(
				/* translators: %s: API key source. */
				__( 'Configured from %s.', 'creatorstack-ai' ),
				$this->get_api_key_source_label( $source )
			)
			: __( 'Not configured.', 'creatorstack-ai' );
		?>
		<p>
			<strong><?php echo esc_html( $status ); ?></strong>
		</p>
		<p>
			<a class="button button-secondary" href="<?php echo esc_url( YouTube_Connector::get_connector_url() ); ?>">
				<?php esc_html_e( 'Manage YouTube connector', 'creatorstack-ai' ); ?>
			</a>
		</p>
		<p class="description">
			<?php esc_html_e( 'The YouTube Data API v3 key is managed by WordPress Connectors. WordPress checks the YOUTUBE_DATA_API_KEY environment variable, the YOUTUBE_DATA_API_KEY PHP constant, and then the connector database setting.', 'creatorstack-ai' ); ?>
		</p>
		<?php
	}

	/**
	 * Get a human-readable API key source label.
	 *
	 * @param string $source API key source.
	 * @return string
	 */
	private function get_api_key_source_label( string $source ): string {
		return match ( $source ) {
			'env'      => __( 'environment variable', 'creatorstack-ai' ),
			'constant' => __( 'PHP constant', 'creatorstack-ai' ),
			'database' => __( 'Settings > Connectors', 'creatorstack-ai' ),
			'legacy'   => __( 'legacy plugin setting', 'creatorstack-ai' ),
			default    => __( 'unknown source', 'creatorstack-ai' ),
		};
	}

	/**
	 * Render Channel ID field.
	 */
	public function render_channel_id_field(): void {
		$value = YouTube_Connector::get_channel_id();
		?>
		<input
			type="text"
			id="<?php echo esc_attr( YouTube_Connector::CHANNEL_ID_OPTION ); ?>"
			name="<?php echo esc_attr( YouTube_Connector::CHANNEL_ID_OPTION ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Your YouTube Channel ID (e.g., UCxxxxxxxxxxxxxxxx).', 'creatorstack-ai' ); ?>
		</p>
		<?php
	}

	/**
	 * Render OAuth Client ID field.
	 */
	public function render_oauth_client_id_field(): void {
		$value = YouTube_Connector::get_oauth_client_id();
		?>
		<input
			type="text"
			id="<?php echo esc_attr( YouTube_Connector::OAUTH_CLIENT_ID_OPTION ); ?>"
			name="<?php echo esc_attr( YouTube_Connector::OAUTH_CLIENT_ID_OPTION ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			autocomplete="off"
		/>
		<p class="description">
			<?php esc_html_e( 'Create a Web application OAuth client in Google Cloud and paste its client ID here.', 'creatorstack-ai' ); ?>
		</p>
		<?php
	}

	/**
	 * Render OAuth Client Secret field.
	 */
	public function render_oauth_client_secret_field(): void {
		$value = YouTube_Connector::get_oauth_client_secret();
		?>
		<input
			type="password"
			id="<?php echo esc_attr( YouTube_Connector::OAUTH_CLIENT_SECRET_OPTION ); ?>"
			name="<?php echo esc_attr( YouTube_Connector::OAUTH_CLIENT_SECRET_OPTION ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			autocomplete="off"
		/>
		<p class="description">
			<?php esc_html_e( 'Store this only on trusted WordPress environments. Google shows the client secret only when the OAuth client is created.', 'creatorstack-ai' ); ?>
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
		$has_credentials = YouTube_Connector::has_oauth_credentials();
		$status_message  = __( 'OAuth credentials have not been saved yet.', 'creatorstack-ai' );

		if ( $is_connected && $has_credentials ) {
			$status_message = __( 'YouTube is connected.', 'creatorstack-ai' );
		} elseif ( $is_connected ) {
			$status_message = __( 'YouTube is connected, but OAuth client credentials are missing. Save them before reconnecting.', 'creatorstack-ai' );
		} elseif ( $has_credentials ) {
			$status_message = __( 'OAuth credentials are saved. You can connect YouTube.', 'creatorstack-ai' );
		}
		?>
		<p>
			<label for="wttba_youtube_oauth_redirect_uri">
				<?php esc_html_e( 'Authorized redirect URI', 'creatorstack-ai' ); ?>
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
			<?php esc_html_e( 'Add this exact URI to the OAuth client in Google Cloud before connecting.', 'creatorstack-ai' ); ?>
		</p>
		<p>
			<strong><?php echo esc_html( $status_message ); ?></strong>
		</p>
		<p>
			<?php if ( $has_credentials ) : ?>
				<a href="<?php echo esc_url( $connect_url ); ?>" class="button button-secondary">
					<?php echo esc_html( $is_connected ? __( 'Reconnect YouTube', 'creatorstack-ai' ) : __( 'Connect YouTube', 'creatorstack-ai' ) ); ?>
				</a>
			<?php else : ?>
				<button type="button" class="button button-secondary" disabled>
					<?php esc_html_e( 'Save OAuth credentials first', 'creatorstack-ai' ); ?>
				</button>
			<?php endif; ?>
			<?php if ( $is_connected ) : ?>
				<a href="<?php echo esc_url( $disconnect_url ); ?>" class="button button-link-delete">
					<?php esc_html_e( 'Disconnect YouTube', 'creatorstack-ai' ); ?>
				</a>
			<?php endif; ?>
		</p>
		<p class="description">
			<?php echo esc_html( $has_credentials ? __( 'The connected account must be able to edit the videos whose captions you want to use.', 'creatorstack-ai' ) : __( 'Enter the OAuth Client ID and Client Secret, save changes, then return here to connect YouTube.', 'creatorstack-ai' ) ); ?>
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
			<?php esc_html_e( 'Describe the writing style for generated posts (e.g., tone, structure, audience). This will be used as default guidance for the AI. Leave empty for a generic professional tone.', 'creatorstack-ai' ); ?>
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
		$status = YouTube_OAuth::consume_status_notice();

		if ( '' === $status ) {
			return;
		}

		$messages = array(
			'connected'           => array( 'success', __( 'YouTube OAuth is connected.', 'creatorstack-ai' ) ),
			'disconnected'        => array( 'success', __( 'YouTube OAuth has been disconnected.', 'creatorstack-ai' ) ),
			'missing_credentials' => array( 'error', __( 'Save the OAuth client ID and client secret before connecting YouTube.', 'creatorstack-ai' ) ),
			'invalid_state'       => array( 'error', __( 'The OAuth callback could not be verified. Please try connecting again.', 'creatorstack-ai' ) ),
			'missing_code'        => array( 'error', __( 'Google did not return an authorization code. Please try connecting again.', 'creatorstack-ai' ) ),
			'wttba_youtube_oauth_failed' => array( 'error', __( 'The OAuth token exchange failed. Check the OAuth client configuration and try reconnecting.', 'creatorstack-ai' ) ),
			'access_denied'       => array( 'error', __( 'YouTube OAuth access was denied.', 'creatorstack-ai' ) ),
			'redirect_uri_mismatch' => array(
				'error',
				sprintf(
					/* translators: %s: OAuth redirect URI. */
					__( 'Google rejected the redirect URI. Add this exact Authorized redirect URI to the OAuth client in Google Cloud: %s', 'creatorstack-ai' ),
					YouTube_OAuth::get_redirect_uri()
				),
			),
			'invalid_client'      => array( 'error', __( 'Google rejected the OAuth client. Check the saved Client ID and Client Secret, then reconnect YouTube.', 'creatorstack-ai' ) ),
			'unauthorized_client' => array( 'error', __( 'Google rejected this OAuth client for the requested YouTube scope. Check the OAuth consent screen and client type in Google Cloud.', 'creatorstack-ai' ) ),
			'oauth_redirect_failed' => array( 'error', __( 'CreatorStack AI could not redirect to Google OAuth. Please try again.', 'creatorstack-ai' ) ),
		);

		$notice = $messages[ $status ] ?? array( 'error', __( 'YouTube OAuth could not be completed. Please try again.', 'creatorstack-ai' ) );
		?>
		<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice[1] ); ?></p>
		</div>
		<?php
	}
}
