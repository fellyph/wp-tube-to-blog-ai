<?php
/**
 * Plugin Name:       WP Tube-to-Blog AI
 * Plugin URI:        https://github.com/user/wp-tube-to-blog-ai
 * Description:       Convert YouTube videos into WordPress blog post drafts using the WordPress AI Client.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Starter
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-tube-to-blog-ai
 * Domain Path:       /languages
 *
 * @package WP_Tube_To_Blog_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WTTBA_VERSION', '1.0.0' );
define( 'WTTBA_PLUGIN_FILE', __FILE__ );
define( 'WTTBA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WTTBA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload Composer dependencies.
if ( file_exists( WTTBA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once WTTBA_PLUGIN_DIR . 'vendor/autoload.php';
}

// Load plugin classes.
require_once WTTBA_PLUGIN_DIR . 'includes/class-plugin.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-settings.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-youtube-api.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-transcript-fetcher.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-post-generator.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-rest-controller.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-dashboard-widget.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-admin-videos-page.php';

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
