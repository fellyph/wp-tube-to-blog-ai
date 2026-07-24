<?php
/**
 * 3D Reader: HTML-in-Canvas + WebGL page-tear transitions on the frontend.
 *
 * @package CreatorStack_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Progressive enhancement for single posts: snapshots the live DOM into a
 * WebGL texture via the experimental HTML-in-Canvas API (Chrome origin trial)
 * and plays a paper-tear transition when the reader moves to the next post.
 */
class Reader_3D {

	/**
	 * Option: feature toggle.
	 */
	const OPTION_ENABLED = 'wttba_reader3d_enabled';

	/**
	 * Option: HTML-in-Canvas origin trial token.
	 */
	const OPTION_OT_TOKEN = 'wttba_reader3d_ot_token';

	/**
	 * Option: CSS selector override for the content root (advanced).
	 */
	const OPTION_SELECTOR = 'wttba_reader3d_selector';

	/**
	 * Script/style handle.
	 */
	const HANDLE = 'wttba-reader3d';

	/**
	 * Wire up hooks.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
		add_action( 'wp_head', array( $this, 'print_origin_trial_meta' ), 1 );
		add_action( 'wp_footer', array( $this, 'print_frontend_data' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Whether the 3D reader should run on the current request.
	 *
	 * @return bool
	 */
	private function is_active(): bool {
		return is_singular( 'post' ) && '1' === get_option( self::OPTION_ENABLED, '1' );
	}

	/**
	 * Enqueue the frontend script and stylesheet on single posts.
	 */
	public function maybe_enqueue_assets(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			WTTBA_R3D_PLUGIN_URL . 'assets/css/reader-3d.css',
			array(),
			WTTBA_R3D_VERSION
		);

		wp_enqueue_script(
			self::HANDLE,
			WTTBA_R3D_PLUGIN_URL . 'assets/js/reader-3d.js',
			array(),
			WTTBA_R3D_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Print the origin trial meta tag so Chrome enables the API for this origin.
	 *
	 * The HTML-in-Canvas API is in origin trial in Chrome 148–150; without a
	 * token (or the chrome://flags/#canvas-draw-element flag) the script
	 * gracefully falls back to regular navigation.
	 */
	public function print_origin_trial_meta(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$token = trim( (string) get_option( self::OPTION_OT_TOKEN, '' ) );

		if ( '' === $token ) {
			return;
		}

		printf(
			'<meta http-equiv="origin-trial" content="%s">' . "\n",
			esc_attr( $token )
		);
	}

	/**
	 * Print adjacent-post data as JSON plus the floating post navigation.
	 *
	 * The JSON block is also parsed out of fetched documents during soft
	 * navigation, so each post carries its own next/prev pointers.
	 */
	public function print_frontend_data(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$next = get_next_post();
		$prev = get_previous_post();

		$data = array(
			'next'     => $next ? array(
				'url'   => get_permalink( $next ),
				'title' => get_the_title( $next ),
			) : null,
			'prev'     => $prev ? array(
				'url'   => get_permalink( $prev ),
				'title' => get_the_title( $prev ),
			) : null,
			'selector' => (string) get_option( self::OPTION_SELECTOR, '' ),
		);

		printf(
			'<script type="application/json" id="wttba-r3d-data">%s</script>' . "\n",
			wp_json_encode( $data )
		);

		if ( ! $next && ! $prev ) {
			return;
		}

		echo '<nav class="wttba-r3d-nav" aria-label="' . esc_attr__( 'Post navigation', 'creatorstack-ai' ) . '">';

		if ( $prev ) {
			printf(
				'<a class="wttba-r3d-btn wttba-r3d-prev" rel="prev" href="%s"><span aria-hidden="true">&larr;</span> %s</a>',
				esc_url( get_permalink( $prev ) ),
				esc_html( wp_html_excerpt( get_the_title( $prev ), 40, '…' ) )
			);
		}

		if ( $next ) {
			printf(
				'<a class="wttba-r3d-btn wttba-r3d-next" rel="next" href="%s">%s <span aria-hidden="true">&rarr;</span></a>',
				esc_url( get_permalink( $next ) ),
				esc_html( wp_html_excerpt( get_the_title( $next ), 40, '…' ) )
			);
		}

		echo '</nav>';
	}

	/**
	 * Register the settings page under Settings.
	 */
	public function register_settings_page(): void {
		add_options_page(
			__( '3D Reader', 'creatorstack-ai' ),
			__( '3D Reader', 'creatorstack-ai' ),
			'manage_options',
			'wttba-reader3d',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public function register_settings(): void {
		register_setting(
			'wttba_reader3d',
			self::OPTION_ENABLED,
			array(
				'type'              => 'string',
				'sanitize_callback' => static fn( $value ) => '1' === $value ? '1' : '0',
				'default'           => '1',
			)
		);

		register_setting(
			'wttba_reader3d',
			self::OPTION_OT_TOKEN,
			array(
				'type'              => 'string',
				'sanitize_callback' => static fn( $value ) => preg_replace( '/[^A-Za-z0-9+\/=]/', '', (string) $value ),
				'default'           => '',
			)
		);

		register_setting(
			'wttba_reader3d',
			self::OPTION_SELECTOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		add_settings_section(
			'wttba_reader3d_main',
			__( 'HTML-in-Canvas 3D Reader', 'creatorstack-ai' ),
			array( $this, 'render_section_intro' ),
			'wttba-reader3d'
		);

		add_settings_field(
			self::OPTION_ENABLED,
			__( 'Enable 3D transitions', 'creatorstack-ai' ),
			array( $this, 'render_enabled_field' ),
			'wttba-reader3d',
			'wttba_reader3d_main'
		);

		add_settings_field(
			self::OPTION_OT_TOKEN,
			__( 'Origin trial token', 'creatorstack-ai' ),
			array( $this, 'render_token_field' ),
			'wttba-reader3d',
			'wttba_reader3d_main'
		);

		add_settings_field(
			self::OPTION_SELECTOR,
			__( 'Content selector (advanced)', 'creatorstack-ai' ),
			array( $this, 'render_selector_field' ),
			'wttba-reader3d',
			'wttba_reader3d_main'
		);
	}

	/**
	 * Section intro text.
	 */
	public function render_section_intro(): void {
		echo '<p>';
		esc_html_e( 'Plays a WebGL paper-tear transition between posts by drawing the live page into a canvas with the experimental HTML-in-Canvas API. Browsers without the API keep regular navigation.', 'creatorstack-ai' );
		echo '</p><p>';
		printf(
			/* translators: 1: origin trial registration URL, 2: Chrome flag. */
			esc_html__( 'The API is Chromium-only for now. Register your origin for the trial at %1$s, or test locally with the %2$s flag.', 'creatorstack-ai' ),
			'<a href="https://developer.chrome.com/origintrials" target="_blank" rel="noopener noreferrer">developer.chrome.com/origintrials</a>',
			'<code>chrome://flags/#canvas-draw-element</code>'
		);
		echo '</p>';
	}

	/**
	 * Enabled checkbox field.
	 */
	public function render_enabled_field(): void {
		printf(
			'<label><input type="checkbox" name="%s" value="1" %s> %s</label>',
			esc_attr( self::OPTION_ENABLED ),
			checked( '1', get_option( self::OPTION_ENABLED, '1' ), false ),
			esc_html__( 'Use the 3D page-tear effect when navigating between posts', 'creatorstack-ai' )
		);
	}

	/**
	 * Origin trial token field.
	 */
	public function render_token_field(): void {
		printf(
			'<textarea name="%s" rows="3" class="large-text code">%s</textarea><p class="description">%s</p>',
			esc_attr( self::OPTION_OT_TOKEN ),
			esc_textarea( (string) get_option( self::OPTION_OT_TOKEN, '' ) ),
			esc_html__( 'Printed as an origin-trial meta tag on single posts so visitors on Chrome get the API without flags.', 'creatorstack-ai' )
		);
	}

	/**
	 * Selector override field.
	 */
	public function render_selector_field(): void {
		printf(
			'<input type="text" name="%s" value="%s" class="regular-text code" placeholder="article"><p class="description">%s</p>',
			esc_attr( self::OPTION_SELECTOR ),
			esc_attr( (string) get_option( self::OPTION_SELECTOR, '' ) ),
			esc_html__( 'CSS selector of the element swapped during soft navigation. Leave empty to swap the whole page body.', 'creatorstack-ai' )
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '3D Reader', 'creatorstack-ai' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'wttba_reader3d' );
				do_settings_sections( 'wttba-reader3d' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
