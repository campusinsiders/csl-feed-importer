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
	$fields = new \Fieldmanager_Group( array(
		'name' => 'csl_feed_import_options',
		'children' => array(
			'interval' => new \Fieldmanager_TextField( 'Interval to Run Importer ( Hours )' ),
			'author' => new \Fieldmanager_Select( 'Default Author of Imported Posts', array(
				'datasource' => new \Fieldmanager_Datasource_User,
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
