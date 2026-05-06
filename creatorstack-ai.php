<?php
/**
 * Plugin Name:       CreatorStack AI
 * Plugin URI:        https://github.com/fellyph/creatorstack-ai
 * Description:       AI toolkit for creator workflows: turn videos and audio into posts, generate audio from posts, and expand content across channels.
 * Version:           1.0.0
 * Requires at least: 7.0-beta
 * Requires PHP:      8.1
 * Author:            Fellyph Cintra
 * Author URI:        https://github.com/fellyph
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       creatorstack-ai
 * Domain Path:       /languages
 *
 * @package CreatorStack_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WTTBA_VERSION', '1.0.0' );
define( 'WTTBA_PLUGIN_FILE', __FILE__ );
define( 'WTTBA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WTTBA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load plugin classes.
require_once WTTBA_PLUGIN_DIR . 'includes/class-plugin.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-admin-navigation.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-settings.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-ai-provider-status.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-generation-logger.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-content-generator.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-youtube-api.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-youtube-oauth.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-transcript-fetcher.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-post-generator.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-post-audio-generator.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-rest-controller.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-dashboard-widget.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-admin-videos-page.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-editor-integration.php';

/**
 * Initialize the plugin.
 */
function wttba_init() {
	\WTTBA\Plugin::get_instance();
}
add_action( 'plugins_loaded', 'wttba_init' );

/**
 * Plugin activation hook.
 */
function wttba_activate() {
	if ( ! get_option( 'wttba_default_language' ) ) {
		add_option( 'wttba_default_language', 'en' );
	}
}
register_activation_hook( __FILE__, 'wttba_activate' );
