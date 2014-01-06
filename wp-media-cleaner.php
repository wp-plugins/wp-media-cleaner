<?php
/*
Plugin Name: WP Media Cleaner
Plugin URI: http://wordpress.org/plugins/wp-media-cleaner/
Description: Clean your Media Library and Uploads Folder.
Version: 1.7.0
Author: Jordy Meow
Author URI: http://www.meow.fr

Dual licensed under the MIT and GPL licenses:
http://www.opensource.org/licenses/mit-license.php
http://www.gnu.org/licenses/gpl.html

Originally developed for two of my websites: 
- Totoro Times (http://www.totorotimes.com) 
- Haikyo (http://www.haikyo.org)
*/

add_action( 'admin_menu', 'wpmc_admin_menu' );
add_action( 'admin_enqueue_scripts', 'wpmc_wp_enqueue_scripts' );
add_action( 'wp_ajax_wpmc_scan', 'wpmc_wp_ajax_wpmc_scan' );
add_action( 'wp_ajax_wpmc_get_all_issues', 'wpmc_wp_ajax_wpmc_get_all_issues' );
add_action( 'wp_ajax_wpmc_get_all_deleted', 'wpmc_wp_ajax_wpmc_get_all_deleted' );
add_action( 'wp_ajax_wpmc_scan_do', 'wpmc_wp_ajax_wpmc_scan_do' );
add_action( 'wp_ajax_wpmc_delete_do', 'wpmc_wp_ajax_wpmc_delete_do' );
add_action( 'wp_ajax_wpmc_recover_do', 'wpmc_wp_ajax_wpmc_recover_do' );

register_activation_hook( __FILE__, 'wpmc_activate' );
register_uninstall_hook( __FILE__, 'wpmc_uninstall' );

require( 'jordy_meow_footer.php' );
require( 'wpmc_settings.php' );

/**
 *
 * ASYNCHRONOUS AJAX FUNCTIONS
 *
 */

function wpmc_wp_ajax_wpmc_delete_do () {
	ob_start();
	$data = $_POST['data'];
	$success = 0;
	foreach ( $data as $piece ) {
		$success += ( wpmc_delete( $piece ) ? 1 : 0 );
	}
	ob_end_clean();
	echo json_encode( 
		array(
			'success' => true,
			'result' => array( 'data' => $data, 'success' => $success ),
			'message' => __( "Status unknown.", 'wp-media-cleaner' )
		)
	);
	die();
}

function wpmc_wp_ajax_wpmc_recover_do () {
	ob_start();
	$data = $_POST['data'];
	$success = 0;
	foreach ( $data as $piece ) {
		$success +=  ( wpmc_recover( $piece ) ? 1 : 0 );
	}
	ob_end_clean();
	echo json_encode( 
		array(
			'success' => true,
			'result' => array( 'data' => $data, 'success' => $success ),
			'message' => __( "Status unknown.", 'wp-media-cleaner' )
		)
	);
	die();
}

function wpmc_wp_ajax_wpmc_scan_do () {
	ob_start();
	$type = $_POST['type'];
	$data = $_POST['data'];
	$success = 0;
	ob_end_clean();
	foreach ( $data as $piece ) {
		if ( $type == 'file' )
			$success +=  ( wpmc_check_file( $piece ) ? 1 : 0 );
		else if ( $type == 'media' )
			$success += ( wpmc_check_media( $piece ) ? 1 : 0 );
	}
	echo json_encode( 
		array(
			'success' => true,
			'result' => array( 'type' => $type, 'data' => $data, 'success' => $success ),
			'message' => __( "Items checked.", 'wp-media-cleaner' )
		)
	);
	die();
}

function wpmc_wp_ajax_wpmc_get_all_deleted () {
	global $wpdb;
	$table_name = $wpdb->prefix . "wpmcleaner";
	$ids = $wpdb->get_col( "SELECT id FROM $table_name WHERE ignored = 0 AND deleted = 1" );
	echo json_encode( 
		array(
			'results' => array( 'ids' => $ids ),
			'success' => true,
			'message' => __( "List generated.", 'wp-media-cleaner' )
		)
	);
	die;
}

function wpmc_wp_ajax_wpmc_get_all_issues () {
	global $wpdb;
	$isTrash = ( isset( $_POST['isTrash'] ) && $_POST['isTrash'] == 1 ) ? true : false;
	$table_name = $wpdb->prefix . "wpmcleaner";
	if ( $isTrash )
		$ids = $wpdb->get_col( "SELECT id FROM $table_name WHERE ignored = 0 AND deleted = 1" );
	else
		$ids = $wpdb->get_col( "SELECT id FROM $table_name WHERE ignored = 0 AND deleted = 0" );
	echo json_encode( 
		array(
			'results' => array( 'ids' => $ids ),
			'success' => true,
			'message' => __( "List generated.", 'wp-media-cleaner' )
		)
	);
	die;
}

function wpmc_wp_ajax_wpmc_scan () {
	global $wpdb;
	$library = $_POST['library'];
	$upload = $_POST['upload'];
	$upload_folder = wp_upload_dir();

	if ( wpmc_getoption( 'scan_files', 'wpmc_basics' ) )
		$files = wpmc_list_uploaded_files( $upload_folder['basedir'], $upload_folder['basedir'] );
	else
		$files = array();

	if ( wpmc_getoption( 'scan_media', 'wpmc_basics' ) )
		$medias = $wpdb->get_col( "SELECT p.ID FROM $wpdb->posts p WHERE post_status = 'inherit' AND post_type = 'attachment'" );
	else
		$medias = array();

	echo json_encode( 
		array(
			'results' => array( 'files' => $files, 'medias' => $medias ),
			'success' => true,
			'message' => __( "Lists generated.", 'wp-media-cleaner' )
		)
	);
	die();
}

/**
 *
 * HELPERS
 *
 */

function wpmc_trashdir() {
	$upload_folder = wp_upload_dir();
	return trailingslashit( $upload_folder['basedir'] ) . 'wpmc-trash';
}

/**
 *
 * DELETE / SCANNING / RESET
 *
 */

function wpmc_recover( $id ) {
	global $wpdb;
	$table_name = $wpdb->prefix . "wpmcleaner";
	$issue = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ), OBJECT );
	$issue->path = stripslashes( $issue->path );

	if ( $issue->type != 0 )
		die( "Wrong type! Recover only works with type 0." );

	$basedir = wp_upload_dir();
	$originalPath = trailingslashit( $basedir['basedir'] ) . $issue->path;
	$trashPath = trailingslashit( wpmc_trashdir() ) . $issue->path;
	$path_parts = pathinfo( $originalPath );
	if ( !file_exists( $path_parts['dirname'] ) && !wp_mkdir_p( $path_parts['dirname'] ) ) {
		die('Failed to create folder.');
	}
	if ( !rename( $trashPath, $originalPath ) ) {
		die('Failed to move the file.');
	}
	$wpdb->query( $wpdb->prepare( "UPDATE $table_name SET deleted = 0 WHERE id = %d", $id ) );
	return true;	
}

function wpmc_trash_file( $fileIssuePath ) {
	global $wpdb;
	$basedir = wp_upload_dir();
	$originalPath = trailingslashit( $basedir['basedir'] ) . $fileIssuePath;
	$trashPath = trailingslashit( wpmc_trashdir() ) . $fileIssuePath;
	$path_parts = pathinfo( $trashPath );

	try {
		if ( !file_exists( $path_parts['dirname'] ) && !wp_mkdir_p( $path_parts['dirname'] ) ) {
			return false;
		}
		// Rename the file (move). 'is_dir' is just there for security (no way we should move a whole directory)
		if ( is_dir( $originalPath ) || !rename( $originalPath, $trashPath ) ) {
			return false;
		}
	}
	catch (Exception $e) {
		return false;
	}
	return true;
}

function wpmc_delete( $id ) {
	global $wpdb;
	$table_name = $wpdb->prefix . "wpmcleaner";
	$issue = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ), OBJECT );
	$issue->path = stripslashes( $issue->path );

	print_r($issue);

	// Empty trash
	if ( $issue->deleted == 1 ) {
		$trashPath = trailingslashit( wpmc_trashdir() ) . $issue->path;
		if ( !unlink( $trashPath ) ) {
			die('Failed to delete the file.');
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE id = %d", $id ) );
		return true;
	}

	// Remove file
	if ( $issue->type == 0 ) {
		if ( wpmc_trash_file( $issue->path ) )
			$wpdb->query( $wpdb->prepare( "UPDATE $table_name SET deleted = 1 WHERE id = %d", $id ) );
		return true;
	}

	// Remove DB media
	if ( $issue->type == 1 ) {

		// There is no trash for WP attachment for now. Let's copy
		// the images to the trash so that it can be recovered.
		$fullpath = get_attached_file( $issue->postId );
		$mainfile = wpmc_clean_uploaded_filename( $fullpath );
		$baseUp = pathinfo( $mainfile );
		$baseUp = $baseUp['dirname'];
		$size = filesize ($fullpath);
		$file = wpmc_clean_uploaded_filename( $fullpath );
		if ( wpmc_trash_file( $file ) ) {
			$table_name = $wpdb->prefix . "wpmcleaner";
			$wpdb->insert( $table_name, 
				array( 
					'time' => current_time('mysql'),
					'type' => 0,
					'deleted' => 1,
					'path' => $file,
					'size' => $size,
					'issue' => $issue->issue
				) 
			);
		}

		// If images, check the other files as well
		$meta = wp_get_attachment_metadata( $issue->postId );
		$isImage = isset( $meta, $meta['width'], $meta['height'] );
		$sizes = wpmc_get_image_sizes();
		if ( $isImage && isset( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $name => $attr ) {
				if  ( isset( $attr['file'] ) ) {
					$filepath = wp_upload_dir();
					$filepath = $filepath['basedir'];
					$filepath = trailingslashit( $filepath ) . trailingslashit( $baseUp ) . $attr['file'];
					$file = wpmc_clean_uploaded_filename( $filepath );
					$size = filesize ($filepath);

					// For each file of this media successfully trashed, we should
					// create a new entry in the WP Cleaner database to give the
					// user the chance to restore it.
					if ( wpmc_trash_file( $file ) ) {
						$table_name = $wpdb->prefix . "wpmcleaner";
						$wpdb->insert( $table_name, 
							array( 
								'time' => current_time('mysql'),
								'type' => 0,
								'deleted' => 1,
								'path' => $file,
								'size' => $size,
								'issue' => $issue->issue
							) 
						);
					}

				}
			}
		}

		// Delete the Media in the WP DB
		wp_delete_attachment( $issue->postId, false );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE id = %d", $id ) );
		return true;
	}

	return false;
}

/**
 *
 * SCANNING / RESET
 *
 */

$wpmc_exclude_dir = array( ".", "..", "wpmc-trash", ".htaccess", "ptetmp", "profiles" );

function wpmc_list_uploaded_files( $basedir, $dir ) {
	wpmc_reset_issues();
	global $wpmc_exclude_dir;
	$result = array();
	$files = scandir( $dir );
	$files = array_diff( $files, $wpmc_exclude_dir );
	foreach( $files as $file ) {
		$fullpath = trailingslashit( $dir ) . $file;
		if ( is_dir( $fullpath ) ) {
			$sub_files = wpmc_list_uploaded_files( $basedir, $fullpath );
			$result = array_merge( $result, $sub_files );
			continue;
		}
		array_push( $result, wpmc_clean_uploaded_filename( $fullpath  ) );
	}
	return $result;
}

function wpmc_check_db_has_featured( $file ) {
	global $wpdb;
	$mediaCount = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->postmeta AS pm INNER JOIN $wpdb->posts AS p ON pm.meta_value = p.ID WHERE p.guid LIKE %s", '%' . $file . '%' ) );
	return ( $mediaCount > 0 );
}

function wpmc_check_db_has_meta( $file ) {
	global $wpdb;
	$mediaCount = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key != '_wp_attached_file' AND meta_value LIKE %s", '%' . $file . '%' ) );
	return ( $mediaCount > 0 );
}

function wpmc_check_db_has_content( $file ) {
	global $wpdb;
	$mediaCount = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_content LIKE %s", '%' . $file . '%' ) );
	return ( $mediaCount > 0 );
}

// Return true if the files is referenced, false if it is not.
function wpmc_check_file( $path ) {
	global $wpdb;
	$filepath = wp_upload_dir();
	$filepath = $filepath['basedir'];
	$filepath = trailingslashit( $filepath ) . stripslashes( $path );

	// Retina support
	if ( strpos( $path, '@2x.' ) !== false ) {
		$originalfile = str_replace( '@2x.', '.', $filepath );
		if ( file_exists( $originalfile ) )
			return true;
		else {
			$table_name = $wpdb->prefix . "wpmcleaner";
			$wpdb->insert( $table_name, 
				array( 
					'time' => current_time('mysql'),
					'type' => 0,
					'path' => $path,
					'size' => filesize ($filepath),
					'issue' => 'ORPHAN_RETINA'
				) 
			);
			return false;
		}
	}

	$issue = "NONE";
	$path_parts = pathinfo( $path );
	
	if ( !wpmc_check_db_has_meta( $path_parts['basename'] ) )
		$issue = "NO_MEDIA";
	else if ( !wpmc_check_db_has_featured( $path_parts['basename'] ) && wpmc_check_db_has_content( $path_parts['basename'] ) )
		$issue = "NO_POST";
	else
		return true;

	$table_name = $wpdb->prefix . "wpmcleaner";
	$filesize = file_exists( $filepath ) ? filesize ($filepath) : 0;
	$wpdb->insert( $table_name, 
		array( 
			'time' => current_time('mysql'),
			'type' => 0,
			'path' => $path,
			'size' => $filesize,
			'issue' => $issue
		) 
	);
	return false;
}

function wpmc_get_image_sizes() {
	$sizes = array();
	global $_wp_additional_image_sizes;
	foreach ( get_intermediate_image_sizes() as $s ) {
		$crop = false;
		if ( isset( $_wp_additional_image_sizes[$s] ) ) {
			$width = intval( $_wp_additional_image_sizes[$s]['width'] );
			$height = intval( $_wp_additional_image_sizes[$s]['height'] );
			$crop = $_wp_additional_image_sizes[$s]['crop'];
		} else {
			$width = get_option( $s.'_size_w' );
			$height = get_option( $s.'_size_h' );
			$crop = get_option( $s.'_crop' );
		}
		$sizes[$s] = array( 'width' => $width, 'height' => $height, 'crop' => $crop );
	}
	return $sizes;
}


// From a fullpath to the shortened and cleaned path (for example '2013/02/file.png')
function wpmc_clean_uploaded_filename( $fullpath ) {
	$upload_folder = wp_upload_dir();
	$basedir = $upload_folder['basedir'];
	$file = str_replace( $basedir, '', $fullpath );
	$file = trim( $file,  "/" );
	return $file;
}

function wpmc_check_media( $attachmentId ) {
	
	// Is it an image? 
	$meta = wp_get_attachment_metadata( $attachmentId );
	$isImage = isset( $meta, $meta['width'], $meta['height'] );

	// Get the main file
	global $wpdb;
	$fullpath = get_attached_file( $attachmentId );
	$mainfile = wpmc_clean_uploaded_filename( $fullpath );
	$baseUp = pathinfo( $mainfile );
	$baseUp = $baseUp['dirname'];
	$size = 0;
	if ( file_exists( $fullpath ) ) {
		$size = filesize( $fullpath );
	}

	if ( wpmc_check_db_has_content( $mainfile ) || wpmc_check_db_has_featured( $mainfile ) )
		return true;

	// If images, check the other files as well
	$countfiles = 0;
	$sizes = wpmc_get_image_sizes();
	if ( $isImage && isset( $meta['sizes'] ) ) {
		foreach ( $meta['sizes'] as $name => $attr ) {
			if  ( isset( $attr['file'] ) ) {
				$filepath = wp_upload_dir();
				$filepath = $filepath['basedir'];
				$filepath = trailingslashit( $filepath ) . trailingslashit( $baseUp ) . $attr['file'];
				if ( file_exists( $filepath ) ) {
					$size += filesize( $filepath );
				}
				$file = wpmc_clean_uploaded_filename( $attr['file'] );
				$countfiles++;
				if ( wpmc_check_db_has_content( $file ) || wpmc_check_db_has_featured( $file ) )
					return true;
			}
		}
	}
	$table_name = $wpdb->prefix . "wpmcleaner";
	$wpdb->insert( $table_name, 
		array( 
			'time' => current_time('mysql'),
			'type' => 1,
			'size' => $size,
			'path' => $mainfile . ($countfiles > 0 ? (" (+ " . $countfiles . " files)") : ""),
			'postId' => $attachmentId,
			'issue' => 'NO_POST'
			) 
		);
	return false;
}

// Delete all issues
function wpmc_reset_issues( $includingIgnored = false ) {
	global $wpdb;
	$table_name = $wpdb->prefix . "wpmcleaner";
	if ( $includingIgnored ) {
		$wpdb->query( "DELETE FROM $table_name WHERE deleted = 0" );
	}
	else {
		$wpdb->query( "DELETE FROM $table_name WHERE ignored = 0 AND deleted = 0" );
	}
}

/**
 *
 * DASHBOARD
 *
 */

function echo_issue( $issue ) {
	if ( $issue == 'NO_POST' ) {
		_e( "Not used.", 'wp-media-cleaner' );
	}
	else if ( $issue == 'NO_MEDIA' ) {
		_e( "Media (DB entry) not found.", 'wp-media-cleaner' );
	}
	else if ( $issue == 'ORPHAN_RETINA' )
		_e( "Orphan retina.", 'wp-media-cleaner' );	
	else {
		echo $issue;
	}
}

function wpmc_screen() {
	?>
	<div class='wrap'>
		<?php jordy_meow_donation(); ?>
		<div id="icon-upload" class="icon32"><br></div>
		<h2>WP Media Cleaner</h2>

		<?php
			$scan_files = wpmc_getoption( 'scan_files', 'wpmc_basics' );
			$scan_media = wpmc_getoption( 'scan_media', 'wpmc_basics' );

			echo "<p>";
			echo "Deleted files will be moved to the 'uploads/wpmc-trash' directory. Please backup your database and files.";
			if ( !$scan_files && !$scan_media ) {
				_e( "<br />Scan is not enabled for either the files or the medias. Please check Settings > WP Media Cleaner.", 'wp-media-cleaner' ); 
			}
			if ( $scan_media ) {
				_e( "<br />If you delete an item of the type MEDIA, the database entry for it (Media Library) will be <u>deleted permanently</u>.", 'wp-media-cleaner' ); 
			}
			echo "</p>";

		?>

		<?php 
			global $wpdb;
			$posts_per_page = 15; 
			$view = isset ( $_GET[ 'view' ] ) ? $_GET[ 'view' ] : "issues";
			$paged = isset ( $_GET[ 'paged' ] ) ? $_GET[ 'paged' ] : 1;
			$reset = isset ( $_GET[ 'reset' ] ) ? $_GET[ 'reset' ] : 0;
			if ( $reset ) {
				wpmc_reset();
			}
			$s = isset ( $_GET[ 's' ] ) ? $_GET[ 's' ] : null;
			$table_name = $wpdb->prefix . "wpmcleaner";
			$issues_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE ignored = 0 AND deleted = 0" );
			$total_size = $wpdb->get_var( "SELECT SUM(size) FROM $table_name WHERE ignored = 0 AND deleted = 0" );
			$trash_total_size = $wpdb->get_var( "SELECT SUM(size) FROM $table_name WHERE ignored = 0 AND deleted = 1" );
			$ignored_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE ignored = 1" );
			$deleted_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE deleted = 1" );

			if ( $view == 'deleted' ) {
				$items_count = $deleted_count;
				$items = $wpdb->get_results( $wpdb->prepare( "SELECT id, type, postId, path, size, ignored, deleted, issue 
					FROM $table_name WHERE ignored = 0 AND deleted = 1 AND path LIKE %s 
					ORDER BY time 
					DESC LIMIT %d, %d", '%' . $s . '%', ( $paged - 1 ) * $posts_per_page, $posts_per_page ), OBJECT );
			}
			else if ( $view == 'ignored' ) {
				$items_count = $ignored_count;
				$items = $wpdb->get_results( $wpdb->prepare( "SELECT id, type, postId, path, size, ignored, deleted, issue 
					FROM $table_name 
					WHERE ignored = 1 AND deleted = 0 AND path LIKE %s 
					ORDER BY time 
					DESC LIMIT %d, %d", '%' . $s . '%', ( $paged - 1 ) * $posts_per_page, $posts_per_page ), OBJECT );
			}
			else {
				$items_count = $issues_count;
				$items = $wpdb->get_results( $wpdb->prepare( "SELECT id, type, postId, path, size, ignored, deleted, issue 
					FROM $table_name 
					WHERE ignored = 0 AND deleted = 0  AND path LIKE %s
					ORDER BY time 
					DESC LIMIT %d, %d", '%' . $s . '%', ( $paged - 1 ) * $posts_per_page, $posts_per_page ), OBJECT );
			}
		?>

		<style>
			#wpmc-pages {
				float: right;
				position: relative;
				top: 12px;
			}


			#wpmc-pages a {
				text-decoration: none;
				border: 1px solid black;
				padding: 2px 5px;
				border-radius: 4px;
				background: #E9E9E9;
				color: lightslategrey;
				border-color: #BEBEBE;
			}

			#wpmc-pages .current {
				font-weight: bold;
			}
		</style>

		<div style='margin-top: 12px; background: #EEE; padding: 5px; border-radius: 4px; height: 24px; box-shadow: 0px 0px 3px #575757;'>
			
			<!-- SCAN -->
			<?php if ( $view != 'deleted' ) { ?>
				<a id='wpmc_scan' onclick='wpmc_scan()' class='button-primary' style='float: left;'><img style='position: relative; top: 3px; left: -2px; margin-right: 3px; width: 16px; height: 16px;' src='<?php echo trailingslashit( WP_PLUGIN_URL ) . trailingslashit( 'wp-media-cleaner/img'); ?>search.png' /><?php _e("Scan", 'wp-media-cleaner'); ?></a>
			<?php } ?>

			<!-- DELETE SELECTED -->		
			<a id='wpmc_delete' onclick='wpmc_delete()' class='button' style='float: left; margin-left: 5px;'><img style='position: relative; top: 3px; left: -2px; margin-right: 3px; width: 16px; height: 16px;' src='<?php echo trailingslashit( WP_PLUGIN_URL ) . trailingslashit( 'wp-media-cleaner/img'); ?>delete.png' /><?php _e("Delete", 'wp-media-cleaner'); ?></a>
			<?php if ( $view == 'deleted' ) { ?>
				<a id='wpmc_recover' onclick='wpmc_recover()' class='button-secondary' style='float: left; margin-left: 5px;'><img style='position: relative; top: 3px; left: -2px; margin-right: 3px; width: 16px; height: 16px;' src='<?php echo trailingslashit( WP_PLUGIN_URL ) . trailingslashit( 'wp-media-cleaner/img'); ?>pills.png' /><?php _e("Recover", 'wp-media-cleaner'); ?></a>
			<?php } ?>

			<!-- RESET -->
			<?php if ( $view != 'deleted' ) { ?>
				<a id='wpmc_reset' href='?page=wp-media-cleaner&reset=1' class='button-primary' style='float: right; margin-left: 5px;'><img style='position: relative; top: 3px; left: -2px; margin-right: 3px; width: 16px; height: 16px;' src='<?php echo trailingslashit( WP_PLUGIN_URL ) . trailingslashit( 'wp-media-cleaner/img'); ?>burn.png' /><?php _e("Reset", 'wp-media-cleaner'); ?></a>
			<?php } ?>

			<!-- DELETE ALL -->
			<?php if ( $view == 'deleted' ) { ?>
				<a id='wpmc_recover_all' onclick='wpmc_recover_all()' class='button-primary' style='float: right; margin-left: 5px;'><img style='position: relative; top: 3px; left: -2px; margin-right: 3px; width: 16px; height: 16px;' src='<?php echo trailingslashit( WP_PLUGIN_URL ) . trailingslashit( 'wp-media-cleaner/img'); ?>pills.png' /><?php _e("Recover all", 'wp-media-cleaner'); ?></a>
				<a id='wpmc_delete_all' onclick='wpmc_delete_all(true)' class='button button-red' style='float: right; margin-left: 5px;'><img style='position: relative; top: 3px; left: -2px; margin-right: 3px; width: 16px; height: 16px;' src='<?php echo trailingslashit( WP_PLUGIN_URL ) . trailingslashit( 'wp-media-cleaner/img'); ?>trash.png' /><?php _e("Empty trash", 'wp-media-cleaner'); ?></a>				
			<?php } else { ?>
				<a id='wpmc_delete_all' onclick='wpmc_delete_all()' class='button button-red' style='float: right; margin-left: 5px;'><img style='position: relative; top: 3px; left: -2px; margin-right: 3px; width: 16px; height: 16px;' src='<?php echo trailingslashit( WP_PLUGIN_URL ) . trailingslashit( 'wp-media-cleaner/img'); ?>delete.png' /><?php _e("Delete all", 'wp-media-cleaner'); ?></a>
			<?php } ?>

			<form id="posts-filter" action="upload.php" method="get" style='float: right;'>
				<p class="search-box" style='margin-left: 5px; float: left;'>
					<input type="search" name="s" value="<?php echo $s ? $s : ""; ?>">
					<input type="hidden" name="page" value="wp-media-cleaner">
					<input type="hidden" name="view" value="<?php echo $view; ?>">
					<input type="hidden" name="paged" value="<?php echo $paged; ?>">
					<input type="submit" class="button" value="Search"><span style='border-right: #A2A2A2 solid 1px; margin-left: 5px; margin-right: 3px;'>&nbsp;</span>
				</p>
			</form>

			<!-- PROGRESS -->
			<span style='margin-left: 12px; font-size: 15px; top: 5px; position: relative; color: #747474;' id='wpmc_progression'></span>	

		</div>

		<p>There are <b><?php echo $issues_count ?> issue(s)</b> with your files, accounting for <b><?php echo number_format( $total_size / 1000000, 2 )  ?> MB</b>. Your trash contains <b><?php echo number_format( $trash_total_size / 1000000, 2 )  ?> MB.</b></p>

		<div id='wpmc-pages'>
		<?php
		echo paginate_links(array(  
			'base' => '?page=wp-media-cleaner&s=' . urlencode($s) . '&view=' . $view . '%_%',
			'current' => $paged,
			'format' => '&paged=%#%',
			'total' => ceil( $items_count / $posts_per_page ),
			'prev_next' => false
		));
		?>
		</div>

		<ul class="subsubsub">
			<li class="all"><a <?php if ( $view == 'issues' ) echo "class='current'"; ?> href='?page=wp-media-cleaner&s=<?php echo $s; ?>&view=issues'><?php _e( "Issues", 'wp-media-cleaner' ); ?></a><span class="count">(<?php echo $issues_count; ?>)</span></li> |
			<!--li class="all"><a <?php if ( $view == 'ignored' ) echo "class='current'"; ?> href='?page=wp-media-cleaner&s=<?php echo $s; ?>&view=ignored'><?php _e( "Ignored", 'wp-media-cleaner' ); ?></a><span class="count">(<?php echo $ignored_count; ?>)</span></li-->
			<li class="all"><a <?php if ( $view == 'deleted' ) echo "class='current'"; ?> href='?page=wp-media-cleaner&s=<?php echo $s; ?>&view=deleted'><?php _e( "Trash", 'wp-media-cleaner' ); ?></a><span class="count">(<?php echo $deleted_count; ?>)</span></li>
		</ul>

		<table id='wpmc-table' class='wp-list-table widefat fixed media'>

			<thead>
				<tr>
					<th scope="col" id="cb" class="manage-column column-cb check-column"><input id="wpmc-cb-select-all" type="checkbox"></th>
					<th style='width: 50px;'>Type</th>
					<th style='width: 80px;'>Origin</th>
					<th>Path</th>
					<th style='width: 220px;'>Issue</th>
					<th style='width: 80px; text-align: right;'>Size</th>
				</tr>
			</thead>

			<tbody>
				<?php foreach ( $items as $issue ) { ?>
				<tr>
					<td><input type="checkbox" name="id" value="<?php echo $issue->id ?>"></td>
					<td><?php echo $issue->type == 0 ? 'FILE' : 'MEDIA'; ?></td>
					<td><?php echo $issue->type == 0 ? 'Filesystem' : ("<a href='media.php?attachment_id=" . $issue->postId . "&action=edit'>ID " . $issue->postId . "</a>"); ?></td>
					<td><?php echo stripslashes( $issue->path ); ?></td>
					<td><?php echo_issue( $issue->issue ); ?></td>
					<td style='text-align: right;'><?php echo number_format( $issue->size / 1000, 2 ); ?> KO</td>
				</tr>
				<?php } ?>
			</tbody>

			<tfoot>
				<tr><th></th><th>Type</th><th>Origin</th><th>Path</th><th>Issue</th><th style='width: 80px; text-align: right;'>Size</th></tr>
			</tfoot>

		</table>
	</wrap>

	<?php
	jordy_meow_footer();
}

/**
 *
 * PLUGIN INSTALL / UNINSTALL / SCRIPTS
 *
 */

function wpmc_admin_menu() {
	load_plugin_textdomain( 'wp-media-cleaner', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	add_media_page( 'WP Media Cleaner', 'Clean', 'manage_options', 'wp-media-cleaner', 'wpmc_screen' );
	add_options_page( 'WP Media Cleaner', 'WP Media Cleaner', 'manage_options', 'wpmc_settings', 'wpmc_settings_page' );
}

function wpmc_reset () {
	wpmc_uninstall();
	wpmc_activate();
}

function wpmc_activate () {
	global $wpdb;
	$table_name = $wpdb->prefix . "wpmcleaner"; 
	$sql = "CREATE TABLE $table_name (
		id BIGINT(20) NOT NULL AUTO_INCREMENT,
		time DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
		type TINYINT(1) NOT NULL,
		postId BIGINT(20) NULL,
		path TINYTEXT NULL,
		size INT(9) NULL,
		ignored BIT NOT NULL,
		deleted BIT NOT NULL,
		issue TINYTEXT NOT NULL,
		UNIQUE KEY id (id)
	);";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);

	$upload_folder = wp_upload_dir();
	$basedir = $upload_folder['basedir'];
	if ( !is_writable( $basedir ) ) {
		echo '<div class="error"><p>' . __( 'The directory for uploads is not writable. WP Media Cleaner will only be able to scan.', 'wp-media-cleaner' ) . '</p></div>';
	}

}

function wpmc_uninstall () {
	global $wpdb;
	$table_name = $wpdb->prefix . "wpmcleaner"; 
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
}

function wpmc_wp_enqueue_scripts () {
	wp_enqueue_style( 'wp-media-cleaner-css', plugins_url( '/wp-media-cleaner.css', __FILE__ ) );
	wp_enqueue_script( 'wp-media-cleaner', plugins_url( '/wp-media-cleaner.js', __FILE__ ), array( 'jquery' ), "1.0.0", true );
}
