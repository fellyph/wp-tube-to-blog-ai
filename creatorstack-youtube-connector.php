<?php
/**
 * Plugin Name:       CreatorStack AI YouTube Connector
 * Plugin URI:        https://github.com/fellyph/creatorstack-ai
 * Description:       Registers the YouTube Data API connector used by CreatorStack AI.
 * Version:           1.0.0
 * Requires at least: 7.0-beta
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

define( 'WTTBA_YOUTUBE_CONNECTOR_PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-youtube-connector.php';

register_activation_hook( __FILE__, array( \WTTBA\YouTube_Connector::class, 'migrate_legacy_api_key' ) );

new \WTTBA\YouTube_Connector();
