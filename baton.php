<?php
/**
 * Plugin Name:       Baton
 * Description:       Create and run admin workflows from registered WordPress Abilities API abilities.
 * Version:           0.4.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Nik McLaughlin
 * Plugin URI:        https://github.com/nikolas4175-godaddy/baton
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       baton
 * Domain Path:       /languages
 *
 * @package Baton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BATON_VERSION', '0.4.0' );
define( 'BATON_FILE', __FILE__ );
define( 'BATON_DIR', plugin_dir_path( __FILE__ ) );
define( 'BATON_URL', plugin_dir_url( __FILE__ ) );

require_once BATON_DIR . 'includes/class-input-sanitizer.php';
require_once BATON_DIR . 'includes/class-schema-paths.php';
require_once BATON_DIR . 'includes/class-ability-catalog.php';
require_once BATON_DIR . 'includes/class-input-mapper.php';
require_once BATON_DIR . 'includes/class-workflow-cpt.php';
require_once BATON_DIR . 'includes/class-workflow-runner.php';
require_once BATON_DIR . 'includes/class-workflow-abilities.php';
require_once BATON_DIR . 'includes/admin/class-workflow-list-table.php';
require_once BATON_DIR . 'includes/admin/class-admin.php';
require_once BATON_DIR . 'includes/class-plugin.php';

register_activation_hook( BATON_FILE, array( 'Baton_Plugin', 'activate' ) );
register_deactivation_hook( BATON_FILE, array( 'Baton_Plugin', 'deactivate' ) );

Baton_Plugin::instance();
