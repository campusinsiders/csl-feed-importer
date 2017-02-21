<?php
/**
 * CSL Feed Options
 *
 * Creates an options page where users can set the interval of imports and the authors to
 * import the posts as.
 *
 * @package  CSL_Feed_Importer
 */

namespace Lift\Campus_Insiders\CSL_Feed_Importer;

/**
 * CSL Feed Options
 *
 * Sets up the Options available on the CSL Feed Options Page
 *
 * @return void
 */
function csl_feed_options() {
	$next_run_time = wp_next_scheduled( 'csl_feed_import' );
	if ( ! $next_run_time ) {
		$next_message = ' | Will Not Run Again';
	} else {
		$next_message = ' | Next Run in ' . human_time_diff( time(), $next_run_time );
	}

	$last_run_time = get_option( 'csl_feed_last_run' );
	if ( ! $last_run_time ) {
		$last_message = ' | Has Never Run';
	} else {
		$last_message = ' | Last Run was ' . human_time_diff( time(), $last_run_time ) . ' ago';
	}

	$fields = new \Fieldmanager_Group( array(
		'name' => 'csl_feed_import_options',
		'children' => array(
			'interval' => new \Fieldmanager_TextField( 'Interval ( Hours )' . $next_message . $last_message ),
			'author' => new \Fieldmanager_Select( 'Default Author of Imported Posts', array(
				'datasource' => new \Fieldmanager_Datasource_User,
			) ),
			'post_status' => new \Fieldmanager_Select( 'Default Post Status of Imported Posts', array(
				'options' => array(
					'publish' => 'Published',
					'draft' => 'Draft',
					'pending' => 'Pending Review',
				),
			) ),
			'default_media' => new \Fieldmanager_Media( 'Default Featured Image', array(
				'button_label' => 'Add Featured Image',
				'modal_title' => 'Select Featured Image',
				'modal_button_label' => 'Use Image as Featured Image',
				'preview_size' => 'icon',
			) ),
		),
	) );
	$fields->activate_submenu_page();
}

/*
 * Load the Submenu Page and Options
 */
add_action( 'plugins_loaded', function() {
	// Ensure Fieldmanager is Activated.
	if ( ! function_exists( 'fm_register_submenu_page' ) ) {
		return;
	}

	// Hook up our fields.
	add_action( 'fm_submenu_csl_feed_import_options', __NAMESPACE__ . '\\csl_feed_options' );

	// Register the Submenu Page.
	\fm_register_submenu_page( 'csl_feed_import_options', 'options-general.php', 'CSL Feed Options' );
});
