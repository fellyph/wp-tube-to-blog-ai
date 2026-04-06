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
	}

	/**
	 * Add the settings page under the Settings menu.
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'WP Tube-to-Blog AI', 'wp-tube-to-blog-ai' ),
			__( 'Tube-to-Blog AI', 'wp-tube-to-blog-ai' ),
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
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP Tube-to-Blog AI Settings', 'wp-tube-to-blog-ai' ); ?></h1>
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
		echo '<p>' . esc_html__( 'Enter your YouTube Data API v3 credentials to connect your channel.', 'wp-tube-to-blog-ai' ) . '</p>';
	}

	/**
	 * Content section description.
	 */
	public function render_content_section(): void {
		echo '<p>' . esc_html__( 'Configure the default language for generated blog posts. You can override this per-video when generating.', 'wp-tube-to-blog-ai' ) . '</p>';
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
}
