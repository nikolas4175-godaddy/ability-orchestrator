<?php
/**
 * Plugin Name:       Baton
 * Description:       Create and run admin workflows from registered WordPress Abilities API abilities.
 * Version:           0.3.1
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Nik McLaughlin
 * Text Domain:       baton
 *
 * @package Baton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BATON_VERSION', '0.3.1' );
define( 'BATON_FILE', __FILE__ );
define( 'BATON_DIR', plugin_dir_path( __FILE__ ) );
define( 'BATON_URL', plugin_dir_url( __FILE__ ) );

require_once BATON_DIR . 'includes/class-legacy-cleanup.php';
require_once BATON_DIR . 'includes/class-ability-catalog.php';
require_once BATON_DIR . 'includes/class-input-mapper.php';
require_once BATON_DIR . 'includes/class-workflow-cpt.php';
require_once BATON_DIR . 'includes/class-workflow-runner.php';
require_once BATON_DIR . 'includes/class-workflow-abilities.php';
require_once BATON_DIR . 'includes/admin/class-workflow-list-table.php';
require_once BATON_DIR . 'includes/admin/class-admin.php';
require_once BATON_DIR . 'includes/class-plugin.php';

Baton_Plugin::instance();
