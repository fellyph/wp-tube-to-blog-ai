<?php
/**
 * YouTube connector integration.
 *
 * @package CreatorStack_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and resolves the YouTube Data API connector.
 */
class YouTube_Connector {

	public const CONNECTOR_ID      = 'youtube';
	public const CONNECTOR_TYPE    = 'content_source';
	public const API_KEY_SETTING   = 'connectors_content_source_youtube_api_key';
	public const API_KEY_ENV_VAR   = 'YOUTUBE_DATA_API_KEY';
	public const API_KEY_CONSTANT  = 'YOUTUBE_DATA_API_KEY';
	public const LEGACY_API_OPTION = 'wttba_youtube_api_key';

	public const CHANNEL_ID_OPTION          = 'wttba_youtube_channel_id';
	public const OAUTH_CLIENT_ID_OPTION     = 'wttba_youtube_oauth_client_id';
	public const OAUTH_CLIENT_SECRET_OPTION = 'wttba_youtube_oauth_client_secret';

	/**
	 * Whether the connector hooks have already been registered.
	 */
	private static bool $initialized = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		add_action( 'wp_connectors_init', array( $this, 'register_connector' ) );
		add_action( 'init', array( $this, 'migrate_legacy_api_key' ), 21 );
		add_filter( 'plugin_action_links_' . self::get_connector_plugin_basename(), array( $this, 'plugin_action_links' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'validate_connector_api_key' ), 9, 3 );
		add_filter( 'script_module_data_options-connectors-wp-admin', array( $this, 'set_connector_status' ), 11 );
	}

	/**
	 * Register the YouTube connector with WordPress.
	 *
	 * @param object $registry Connector registry instance.
	 */
	public function register_connector( object $registry ): void {
		if ( ! method_exists( $registry, 'register' ) ) {
			return;
		}

		if ( method_exists( $registry, 'is_registered' ) && $registry->is_registered( self::CONNECTOR_ID ) ) {
			return;
		}

		$registry->register(
			self::CONNECTOR_ID,
			array(
				'name'           => __( 'YouTube', 'creatorstack-ai' ),
				'description'    => __( 'Connects CreatorStack AI to the YouTube Data API for video listings and details.', 'creatorstack-ai' ),
				'type'           => self::CONNECTOR_TYPE,
				'plugin'         => array(
					'file' => self::get_connector_plugin_basename(),
				),
				'authentication' => array(
					'method'          => 'api_key',
					'credentials_url' => 'https://console.cloud.google.com/apis/credentials',
					'setting_name'    => self::API_KEY_SETTING,
					'env_var_name'    => self::API_KEY_ENV_VAR,
					'constant_name'   => self::API_KEY_CONSTANT,
				),
			)
		);
	}

	/**
	 * Add connector management links to the Plugins screen.
	 *
	 * @param array<string> $links Existing plugin action links.
	 * @return array<string> Modified plugin action links.
	 */
	public function plugin_action_links( array $links ): array {
		$connector_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( self::get_connector_url() ),
			esc_html__( 'Connectors', 'creatorstack-ai' )
		);
		$settings_link  = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( self::get_settings_url() ),
			esc_html__( 'YouTube settings', 'creatorstack-ai' )
		);

		array_unshift( $links, $settings_link, $connector_link );

		return $links;
	}

	/**
	 * Get the currently resolved YouTube Data API key.
	 *
	 * @return string API key.
	 */
	public static function get_api_key(): string {
		$auth = self::get_api_key_authentication();

		$env_var_name = (string) ( $auth['env_var_name'] ?? '' );
		if ( '' !== $env_var_name ) {
			$env_value = getenv( $env_var_name );
			if ( false !== $env_value && '' !== $env_value ) {
				return (string) $env_value;
			}
		}

		$constant_name = (string) ( $auth['constant_name'] ?? '' );
		if ( '' !== $constant_name && defined( $constant_name ) ) {
			$constant_value = constant( $constant_name );
			if ( is_string( $constant_value ) && '' !== $constant_value ) {
				return $constant_value;
			}
		}

		$setting_name = (string) ( $auth['setting_name'] ?? self::API_KEY_SETTING );
		$db_value     = (string) get_option( $setting_name, '' );
		if ( '' !== $db_value ) {
			return $db_value;
		}

		return (string) get_option( self::LEGACY_API_OPTION, '' );
	}

	/**
	 * Get the source used for the current API key.
	 *
	 * @return string One of env, constant, database, legacy, or none.
	 */
	public static function get_api_key_source(): string {
		$auth = self::get_api_key_authentication();

		$env_var_name = (string) ( $auth['env_var_name'] ?? '' );
		if ( '' !== $env_var_name ) {
			$env_value = getenv( $env_var_name );
			if ( false !== $env_value && '' !== $env_value ) {
				return 'env';
			}
		}

		$constant_name = (string) ( $auth['constant_name'] ?? '' );
		if ( '' !== $constant_name && defined( $constant_name ) ) {
			$constant_value = constant( $constant_name );
			if ( is_string( $constant_value ) && '' !== $constant_value ) {
				return 'constant';
			}
		}

		$setting_name = (string) ( $auth['setting_name'] ?? self::API_KEY_SETTING );
		if ( '' !== (string) get_option( $setting_name, '' ) ) {
			return 'database';
		}

		if ( '' !== (string) get_option( self::LEGACY_API_OPTION, '' ) ) {
			return 'legacy';
		}

		return 'none';
	}

	/**
	 * Check whether the YouTube Data API key is configured and shaped correctly.
	 *
	 * @return bool Whether a valid-looking key is available.
	 */
	public static function is_api_key_configured(): bool {
		return self::is_valid_api_key( self::get_api_key() );
	}

	/**
	 * Get the configured channel ID.
	 *
	 * @return string Channel ID.
	 */
	public static function get_channel_id(): string {
		return trim( (string) get_option( self::CHANNEL_ID_OPTION, '' ) );
	}

	/**
	 * Check whether the YouTube channel ID is configured and shaped correctly.
	 *
	 * @return bool Whether a valid-looking channel ID is available.
	 */
	public static function is_channel_configured(): bool {
		return self::is_valid_channel_id( self::get_channel_id() );
	}

	/**
	 * Check whether the YouTube API can be used.
	 *
	 * @return bool Whether required YouTube configuration exists.
	 */
	public static function is_configured(): bool {
		return self::is_api_key_configured() && self::is_channel_configured();
	}

	/**
	 * Get URL for configuring the connector API key.
	 *
	 * @return string Admin URL.
	 */
	public static function get_connector_url(): string {
		return admin_url( 'options-connectors.php' );
	}

	/**
	 * Get URL for configuring CreatorStack YouTube settings.
	 *
	 * @return string Admin URL.
	 */
	public static function get_settings_url(): string {
		return admin_url( 'options-general.php?page=wttba-settings' );
	}

	/**
	 * Get the most relevant configuration URL for the current missing state.
	 *
	 * @return string Admin URL.
	 */
	public static function get_configuration_url(): string {
		return self::is_api_key_configured() ? self::get_settings_url() : self::get_connector_url();
	}

	/**
	 * Get the most relevant configuration action label for the current missing state.
	 *
	 * @return string Label.
	 */
	public static function get_configuration_label(): string {
		return self::is_api_key_configured() ? __( 'Update YouTube settings', 'creatorstack-ai' ) : __( 'Configure YouTube connector', 'creatorstack-ai' );
	}

	/**
	 * Get REST error data for YouTube configuration failures.
	 *
	 * @return array<string, string>
	 */
	public static function get_configuration_error_data(): array {
		return array(
			'configuration_url'   => self::get_configuration_url(),
			'configuration_label' => self::get_configuration_label(),
		);
	}

	/**
	 * Build localized YouTube configuration for JavaScript entry points.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_admin_config(): array {
		return array(
			'isConfigured'       => self::is_configured(),
			'isConnectorActive'  => self::is_connector_plugin_active(),
			'apiKeyConfigured'   => self::is_api_key_configured(),
			'channelConfigured'  => self::is_channel_configured(),
			'apiKeySource'       => self::get_api_key_source(),
			'configurationUrl'   => self::get_configuration_url(),
			'configurationLabel' => self::get_configuration_label(),
			'connectorUrl'       => self::get_connector_url(),
			'settingsUrl'        => self::get_settings_url(),
		);
	}

	/**
	 * Whether a value looks like a YouTube Data API key.
	 *
	 * @param string $api_key API key.
	 * @return bool
	 */
	public static function is_valid_api_key( string $api_key ): bool {
		return 1 === preg_match( '/^AIza[0-9A-Za-z_-]{35}$/', trim( $api_key ) );
	}

	/**
	 * Whether a value looks like a YouTube Channel ID.
	 *
	 * @param string $channel_id Channel ID.
	 * @return bool
	 */
	public static function is_valid_channel_id( string $channel_id ): bool {
		return 1 === preg_match( '/^UC[0-9A-Za-z_-]{22}$/', trim( $channel_id ) );
	}

	/**
	 * Get OAuth client ID.
	 *
	 * @return string OAuth client ID.
	 */
	public static function get_oauth_client_id(): string {
		return trim( (string) get_option( self::OAUTH_CLIENT_ID_OPTION, '' ) );
	}

	/**
	 * Get OAuth client secret.
	 *
	 * @return string OAuth client secret.
	 */
	public static function get_oauth_client_secret(): string {
		return trim( (string) get_option( self::OAUTH_CLIENT_SECRET_OPTION, '' ) );
	}

	/**
	 * Whether OAuth client credentials are configured.
	 *
	 * @return bool
	 */
	public static function has_oauth_credentials(): bool {
		return self::is_valid_oauth_client_id( self::get_oauth_client_id() ) && self::is_valid_oauth_client_secret( self::get_oauth_client_secret() );
	}

	/**
	 * Whether a value looks like a Google OAuth client ID.
	 *
	 * @param string $client_id OAuth client ID.
	 * @return bool
	 */
	public static function is_valid_oauth_client_id( string $client_id ): bool {
		return 1 === preg_match( '/^[0-9]+-[0-9A-Za-z_-]+\.apps\.googleusercontent\.com$/', trim( $client_id ) );
	}

	/**
	 * Whether a value looks like a Google OAuth client secret.
	 *
	 * @param string $client_secret OAuth client secret.
	 * @return bool
	 */
	public static function is_valid_oauth_client_secret( string $client_secret ): bool {
		return 1 === preg_match( '/^[0-9A-Za-z_-]{8,}$/', trim( $client_secret ) );
	}

	/**
	 * Get the connector plugin file path.
	 *
	 * @return string Absolute plugin file path.
	 */
	public static function get_connector_plugin_file(): string {
		if ( defined( 'WTTBA_YOUTUBE_CONNECTOR_PLUGIN_FILE' ) ) {
			return WTTBA_YOUTUBE_CONNECTOR_PLUGIN_FILE;
		}

		return dirname( __DIR__ ) . '/creatorstack-youtube-connector.php';
	}

	/**
	 * Get the connector plugin basename.
	 *
	 * @return string Plugin basename.
	 */
	public static function get_connector_plugin_basename(): string {
		return plugin_basename( self::get_connector_plugin_file() );
	}

	/**
	 * Check whether the connector plugin file is available.
	 *
	 * @return bool Whether the connector plugin can be activated.
	 */
	public static function is_connector_plugin_available(): bool {
		return file_exists( self::get_connector_plugin_file() );
	}

	/**
	 * Check whether the connector plugin is active.
	 *
	 * @return bool Whether the connector plugin is active.
	 */
	public static function is_connector_plugin_active(): bool {
		$plugin = self::get_connector_plugin_basename();

		if ( function_exists( 'is_plugin_active' ) ) {
			return is_plugin_active( $plugin );
		}

		return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true );
	}

	/**
	 * Get an admin URL that activates the connector plugin.
	 *
	 * @return string Activation URL.
	 */
	public static function get_connector_activation_url(): string {
		$plugin = self::get_connector_plugin_basename();

		return wp_nonce_url(
			self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $plugin ) ),
			'activate-plugin_' . $plugin
		);
	}

	/**
	 * Migrate the legacy plugin API key option into the connector setting.
	 */
	public static function migrate_legacy_api_key(): void {
		$legacy_key = trim( (string) get_option( self::LEGACY_API_OPTION, '' ) );

		if ( '' === $legacy_key || ! self::is_valid_api_key( $legacy_key ) ) {
			return;
		}

		if ( '' === (string) get_option( self::API_KEY_SETTING, '' ) ) {
			update_option( self::API_KEY_SETTING, $legacy_key, false );
		}

		delete_option( self::LEGACY_API_OPTION );
	}

	/**
	 * Validate the connector API key after it is saved through the REST settings endpoint.
	 *
	 * @param \WP_HTTP_Response $response REST response.
	 * @param \WP_REST_Server   $server   REST server.
	 * @param \WP_REST_Request  $request  REST request.
	 * @return \WP_HTTP_Response
	 */
	public function validate_connector_api_key( \WP_HTTP_Response $response, \WP_REST_Server $server, \WP_REST_Request $request ): \WP_HTTP_Response {
		unset( $server );

		if ( '/wp/v2/settings' !== $request->get_route() ) {
			return $response;
		}

		if ( 'POST' !== $request->get_method() && 'PUT' !== $request->get_method() ) {
			return $response;
		}

		$data = $response->get_data();
		if ( ! is_array( $data ) || ! array_key_exists( self::API_KEY_SETTING, $data ) ) {
			return $response;
		}

		$key = $data[ self::API_KEY_SETTING ];
		if ( ! is_string( $key ) || '' === $key || self::is_valid_api_key( $key ) ) {
			return $response;
		}

		update_option( self::API_KEY_SETTING, '', false );
		$data[ self::API_KEY_SETTING ] = '';
		$response->set_data( $data );

		return $response;
	}

	/**
	 * Set connector status from the validated key shape.
	 *
	 * @param array<string, mixed> $data Script module data.
	 * @return array<string, mixed>
	 */
	public function set_connector_status( array $data ): array {
		if ( isset( $data['connectors'][ self::CONNECTOR_ID ]['authentication'] ) ) {
			$data['connectors'][ self::CONNECTOR_ID ]['authentication']['isConnected'] = self::is_api_key_configured();
		}

		return $data;
	}

	/**
	 * Get registered connector authentication metadata or defaults.
	 *
	 * @return array<string, string>
	 */
	private static function get_api_key_authentication(): array {
		if ( function_exists( 'wp_get_connector' ) ) {
			$connector = wp_get_connector( self::CONNECTOR_ID );

			if ( is_array( $connector ) && is_array( $connector['authentication'] ?? null ) ) {
				return $connector['authentication'];
			}
		}

		return array(
			'method'        => 'api_key',
			'setting_name'  => self::API_KEY_SETTING,
			'env_var_name'  => self::API_KEY_ENV_VAR,
			'constant_name' => self::API_KEY_CONSTANT,
		);
	}

	/**
	 * Get the plugin file associated with the connector card.
	 *
	 * @return string Plugin file path.
	 */
	private static function get_plugin_file(): string {
		return self::get_connector_plugin_file();
	}
}
