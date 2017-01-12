<?php
/**
 * Plugin Name:     CSL Feed Importer
 * Plugin URI:      https://campusinsiders.com
 * Description:     Imports Articles as WordPress Posts from CSL on a user defined interval.
 * Author:          Christian Chung <christian@liftux.com>
 * Author URI:      https://liftux.com
 * Text Domain:     csl-feed-importer
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         CSL_Feed_Importer
 */

namespace Lift\Campus_Insiders\CSL_Feed_Importer;

// Require the importer.
require_once( plugin_dir_path( __FILE__ ) . 'src/class-csl-feed-importer.php' );
require_once( plugin_dir_path( __FILE__ ) . 'src/class-csl-feed-item-importer.php' );

// Require the scheduler.
require_once( plugin_dir_path( __FILE__ ) . 'src/class-csl-feed-import-scheduler.php' );

// Require the options page if we're in the admin.
if ( is_admin() ) {
	require_once( plugin_dir_path( __FILE__ ) . 'src/csl-feed-options.php' );
}

/**
 * Activate
 *
 * Schedules the importer cron job on plugin activation
 *
 * @return void
 */
function activate() {
	$scheduler = new CSL_Feed_Import_Scheduler;
	$scheduler->schedule_next();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Deactivate
 *
 * Removes the cron jobs spawned by this plugin on deactivation
 *
 * @return void
 */
function deactivate() {
	$scheduler = new CSL_Feed_Import_Scheduler;
	$scheduler->clear();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

/**
 * Run
 *
 * Kicks of the parts of the plugin that run always. All this does is add the actions
 * and filters for cron, and options.
 *
 * @return void
 */
function run() {
	$scheduler = new CSL_Feed_Import_Scheduler;
	$scheduler->setup();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\run' );
