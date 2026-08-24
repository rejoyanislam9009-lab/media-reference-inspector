<?php
/**
 * Plugin Name:       Media Reference Inspector
 * Description:       Find where a Media Library item is referenced in standard WordPress content before you replace or remove it.
 * Version:           2.4.0
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

define( 'MEDIAREFINSPECTOR_VERSION', '2.4.0' );
define( 'MEDIAREFINSPECTOR_FILE', __FILE__ );
define( 'MEDIAREFINSPECTOR_PATH', plugin_dir_path( __FILE__ ) );

require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-scanner.php';
require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-enhanced-scanner.php';
require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-integration-scanner.php';
require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-advanced-scanner.php';
require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-insights-scanner.php';
require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-extended-scanner.php';
require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-audit-service.php';
require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-site-audit-service.php';
require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-update-notice.php';
require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-plugin.php';

/**
 * Boots the plugin after all plugins are loaded.
 *
 * @return void
 */
function mediarefinspector_run() {
	$plugin = new MediaRefInspector_Plugin( new MediaRefInspector_Extended_Scanner() );
	$plugin->register();
	MediaRefInspector_Update_Notice::register();
}
add_action( 'plugins_loaded', 'mediarefinspector_run' );
