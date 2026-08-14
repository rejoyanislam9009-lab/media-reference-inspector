<?php
/**
 * Plugin Name:       Media Reference Inspector
 * Description:       Find where a Media Library item is referenced in standard WordPress content before you replace or remove it.
 * Version:           1.0.2
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            rejoyan9009
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       media-reference-inspector
 *
 * @package MediaReferenceInspector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MEDIAREFINSPECTOR_VERSION', '1.0.2' );
define( 'MEDIAREFINSPECTOR_FILE', __FILE__ );
define( 'MEDIAREFINSPECTOR_PATH', plugin_dir_path( __FILE__ ) );

require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-scanner.php';
require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-plugin.php';

/**
 * Boots the plugin after all plugins are loaded.
 *
 * @return void
 */
function mediarefinspector_run() {
	$plugin = new MediaRefInspector_Plugin( new MediaRefInspector_Scanner() );
	$plugin->register();
}
add_action( 'plugins_loaded', 'mediarefinspector_run' );
