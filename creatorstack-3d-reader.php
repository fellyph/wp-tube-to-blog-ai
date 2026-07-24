<?php
/**
 * Plugin Name:       CreatorStack 3D Reader
 * Plugin URI:        https://github.com/fellyph/creatorstack-ai
 * Description:       Renders posts through the experimental HTML-in-Canvas API with a WebGL page-tear transition when navigating between posts.
 * Version:           0.1.0
 * Requires at least: 6.4
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

define( 'WTTBA_R3D_VERSION', '0.1.0' );
define( 'WTTBA_R3D_PLUGIN_FILE', __FILE__ );
define( 'WTTBA_R3D_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WTTBA_R3D_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WTTBA_R3D_PLUGIN_DIR . 'includes/class-reader-3d.php';

add_action(
	'plugins_loaded',
	static function () {
		new \WTTBA\Reader_3D();
	}
);
