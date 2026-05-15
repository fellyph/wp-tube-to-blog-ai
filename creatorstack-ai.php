<?php
/**
 * Plugin Name:       CreatorStack AI
 * Plugin URI:        https://github.com/fellyph/creatorstack-ai
 * Description:       AI toolkit for creator workflows: turn videos and audio into posts, generate audio from posts, and expand content across channels.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * Author:            Fellyph Cintra
 * Author URI:        https://github.com/fellyph
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       creatorstack-ai
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

/**
 * Migrate older active plugin basenames to the canonical bootstrap file.
 *
 * This preserves compatibility for installs that were activated under the
 * historical wp-tube-to-blog-ai.php entry point while keeping creatorstack-ai.php
 * as the current plugin file.
 */
function wttba_migrate_active_plugin_basename(): void {
	$canonical_basename = plugin_basename( __FILE__ );
	$legacy_basenames   = array_unique(
		array_filter(
			array(
				plugin_basename( __DIR__ . '/wp-tube-to-blog-ai.php' ),
				'wp-tube-to-blog-ai/wp-tube-to-blog-ai.php',
				'youtube-to-post/wp-tube-to-blog-ai.php',
			)
		)
	);

	if ( ! in_array( $canonical_basename, $legacy_basenames, true ) ) {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$updated        = false;

		foreach ( $active_plugins as $index => $plugin ) {
			if ( in_array( $plugin, $legacy_basenames, true ) ) {
				$active_plugins[ $index ] = $canonical_basename;
				$updated = true;
			}
		}

		if ( $updated ) {
			update_option( 'active_plugins', array_values( array_unique( $active_plugins ) ) );
		}
	}

	if ( is_multisite() ) {
		$network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
		$updated         = false;

		foreach ( $legacy_basenames as $legacy_basename ) {
			if ( isset( $network_plugins[ $legacy_basename ] ) ) {
				$activated_at = $network_plugins[ $legacy_basename ];
				unset( $network_plugins[ $legacy_basename ] );
				$network_plugins[ $canonical_basename ] = $activated_at;
				$updated = true;
			}
		}

		if ( $updated ) {
			update_site_option( 'active_sitewide_plugins', $network_plugins );
		}
	}
}
wttba_migrate_active_plugin_basename();

// Load plugin classes.
require_once WTTBA_PLUGIN_DIR . 'includes/class-plugin.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-admin-navigation.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-settings.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-ai-provider-status.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-generation-logger.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-content-generator.php';
require_once WTTBA_PLUGIN_DIR . 'includes/class-youtube-connector.php';
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
