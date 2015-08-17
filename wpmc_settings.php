<?php

add_action( 'admin_init', 'wpmc_admin_init' );

/**
 *
 * SETTINGS PAGE
 *
 */

function wpmc_settings_page() {
    global $wpmc_settings_api;
	echo '<div class="wrap">';
    jordy_meow_donation();
	echo "<div id='icon-options-general' class='icon32'><br></div><h2>WP Media Cleaner";
    by_jordy_meow();
    echo "</h2>";
    $wpmc_settings_api->show_navigation();
    $wpmc_settings_api->show_forms();
    echo '</div>';
	jordy_meow_footer();
}

function wpmc_getoption( $option, $section, $default = '' ) {
	$options = get_option( $section );
	if ( isset( $options[$option] ) ) {
        if ( $options[$option] == "off" ) {
            return false;
        }
        if ( $options[$option] == "on" ) {
            return true;
        }
		return $options[$option];
    }
	return $default;
}

function wpmc_admin_init() {
  if ( isset( $_POST ) && isset( $_POST['wpmc_pro'] ) )
      wpmc_validate_pro( $_POST['wpmc_pro']['subscr_id'] );
  $pro_status = get_option( 'wpmc_pro_status', "Not Pro." );
  require( 'wpmc_class.settings-api.php' );
  global $wpmc_settings_api;
  $wpmc_settings_api = new WeDevs_Settings_API;
	$sections = array(
        array(
            'id' => 'wpmc_basics',
            'title' => __( 'Basics', 'wp-media-cleaner' )
        ),
        array(
            'id' => 'wpmc_pro',
            'title' => __( 'Pro', 'wp-media-cleaner' )
        )
    );

    $fields = array(
        'wpmc_basics' => array(
            array(
                'name' => 'scan_media',
                'label' => __( 'Scan Media', 'wp-media-cleaner' ),
                'desc' => __( 'The Media Library will be scanned.<br /><small>The medias from Media Library which seem not being used in your WordPress will be marked as to be deleted.</small>', 'wp-media-cleaner' ),
                'type' => 'checkbox',
                'default' => false
            ),
            array(
                'name' => 'scan_files',
                'label' => __( 'Scan Files', 'wp-media-cleaner' ),
                'desc' => __( 'The Uploads folder will be scanned.<br /><small>The files in your /uploads folder that don\'t seem being used in your WordPress will be marked as to be deleted. <b>If they are found in your Media Library, they will be considered as fine.</b></small>', 'wp-media-cleaner' ),
                'type' => 'checkbox',
                'default' => false
            ),
            array(
                'name' => 'scan_non_ascii',
                'label' => __( 'UTF-8 Support', 'wp-media-cleaner' ),
                'desc' => __( 'The filenames in UTF-8 will not be skipped.<br /><small>PHP does not always work well with UTF-8 on all systems (Windows?). If the scanning suddenly stops, this might be the cause.</small>', 'wp-media-cleaner' ),
                'type' => 'checkbox',
                'default' => false
            ),
            array(
                'name' => 'hide_thumbnails',
                'label' => __( 'Hide Thumbnails', 'wp-media-cleaner' ),
                'desc' => __( 'Hide the thumbnails column.<br /><small>Useful if your WordPress if filled-up with huge images.</small>', 'wp-media-cleaner' ),
                'type' => 'checkbox',
                'default' => false
            )
        ),
        'wpmc_pro' => array(
            array(
                'name' => 'pro',
                'label' => '',
                'desc' => __( sprintf( 'Status: %s<br /><br />', $pro_status ), 'wp-media-cleaner' ),
                'type' => 'html'
            ),
            array(
                'name' => 'subscr_id',
                'label' => __( 'Serial', 'wp-media-cleaner' ),
                'desc' => __( '<br />Enter your serial or subscription ID here. If you don\'t have one yet, get one <a target="_blank" href="http://apps.meow.fr/wp-media-cleaner/">right here</a>.', 'wp-media-cleaner' ),
                'type' => 'text',
                'default' => ""
            ),
        )
    );
    $wpmc_settings_api->set_sections( $sections );
    $wpmc_settings_api->set_fields( $fields );
    $wpmc_settings_api->admin_init();

}

?>
