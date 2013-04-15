<?php

add_action( 'admin_init', 'wpmc_admin_init' );

/**
 *
 * SETTINGS PAGE
 *
 */

function wpmc_settings_page() {
    $settings_api = wpmc_WeDevs_Settings_API::getInstance();
	echo '<div class="wrap">';
    jordy_meow_donation();
	echo "<div id='icon-options-general' class='icon32'><br></div><h2>WP Media Cleaner</h2>";

    //settings_errors();
    $settings_api->show_navigation();
    $settings_api->show_forms();
    echo '</div>';
	jordy_meow_footer();
}

function wpmc_getoption( $option, $section, $default = '' ) {
	$options = get_option( $section );
	if ( isset( $options[$option] ) )
		return $options[$option];
	return $default;
}

function wpmc_admin_init() {

	require( 'wpmc_class.settings-api.php' );
	
	$sections = array(
        array(
            'id' => 'wpmc_basics',
            'title' => __( 'Basics', 'wp-media-cleaner' )
        )
    );
	
	$fields = array(
        'wpmc_basics' => array(
			array(
                'name' => 'scan_media',
                'label' => __( 'Scan Media', 'wp-media-cleaner' ),
                'desc' => __( 'The Media Library will be scanned.', 'wp-media-cleaner' ),
                'type' => 'checkbox',
                'default' => false
            ),
			array(
                'name' => 'scan_files',
                'label' => __( 'Scan Files', 'wp-media-cleaner' ),
                'desc' => __( 'The Uploads folder will be scanned.', 'wp-media-cleaner' ),
                'type' => 'checkbox',
                'default' => false
            )
        )
    );
	$settings_api = wpmc_WeDevs_Settings_API::getInstance();
    $settings_api->set_sections( $sections );
    $settings_api->set_fields( $fields );
    $settings_api->admin_init();
}

?>
