<?php
/*
Plugin Name: WP Media Cleaner
Plugin URI: http://www.meow.fr
Description: Clean your Media Library and Uploads Folder.
Version: 2.6.0
Author: Jordy Meow
Author URI: http://www.meow.fr

Dual licensed under the MIT and GPL licenses:
http://www.opensource.org/licenses/mit-license.php
http://www.gnu.org/licenses/gpl.html

Big thanks to Matt (http://www.twistedtek.net/) for all the
enhancements he made to the plugin.

Originally developed for two of my websites:
- Totoro Times (http://www.totorotimes.com)
- Haikyo (http://www.haikyo.org)
*/

add_action( 'admin_menu', 'wpmc_admin_menu' );
add_action( 'admin_enqueue_scripts', 'wpmc_wp_enqueue_scripts' );
add_action( 'admin_print_scripts', 'wpmc_admin_inline_js' );
add_action( 'wp_ajax_wpmc_scan', 'wpmc_wp_ajax_wpmc_scan' );
add_action( 'wp_ajax_wpmc_get_all_issues', 'wpmc_wp_ajax_wpmc_get_all_issues' );
add_action( 'wp_ajax_wpmc_get_all_deleted', 'wpmc_wp_ajax_wpmc_get_all_deleted' );
add_action( 'wp_ajax_wpmc_scan_do', 'wpmc_wp_ajax_wpmc_scan_do' );
add_action( 'wp_ajax_wpmc_delete_do', 'wpmc_wp_ajax_wpmc_delete_do' );
add_action( 'wp_ajax_wpmc_ignore_do', 'wpmc_wp_ajax_wpmc_ignore_do' );
add_action( 'wp_ajax_wpmc_recover_do', 'wpmc_wp_ajax_wpmc_recover_do' );

register_activation_hook( __FILE__, 'wpmc_activate' );
register_uninstall_hook( __FILE__, 'wpmc_uninstall' );

require( 'meow_footer.php' );
require( 'wpmc_settings.php' );

global $wpmc_debug;
$wpmc_debug = FALSE;
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

function wpmc_wp_ajax_wpmc_ignore_do () {
	ob_start();
	$data = $_POST['data'];
	$success = 0;
	foreach ( $data as $piece ) {
		$success += ( wpmc_ignore( $piece ) ? 1 : 0 );
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
	global $wpmc_debug;
	wpmc_check_db();
	ob_start();
	$type = $_POST['type'];
	$data = $_POST['data'];
	$success = 0;
	ob_end_clean();
	foreach ( $data as $piece ) {
		if ( $type == 'file' ) {
			if ($wpmc_debug) error_log("check FILE {$piece}");
			$success += ( wpmc_check_file( $piece ) ? 1 : 0 );
		} elseif ( $type == 'media' ) {
			if ($wpmc_debug) error_log("check MEDIA {$piece}");
			$success += ( wpmc_check_media( $piece ) ? 1 : 0 );
		}
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

function wpmc_get_original_images( $force = false ) {
	global $wpmc_debug;
	if ( $force ) {
		delete_transient( "wpmc_original_images" );
		$original_images = null;
	}
	else {
		$original_images = get_transient( "wpmc_original_images" );
	}
	if ( !$original_images ) {
		global $wpdb;
		$original_images = array();
		$posts = $wpdb->get_col( "SELECT id FROM $wpdb->posts WHERE post_type = 'attachment'" );
		foreach( $posts as $post_id ) {
			$source_path = str_replace('\\', '/', get_post_meta( $post_id, '_wp_attached_file', true ) );
			$prepend_path = '';
			$source_parts = explode('/', $source_path);
			if ( is_array( $source_parts ) && !empty( $source_parts ) ) {
				array_pop( $source_parts ); $prepend_path = implode( '/', $source_parts ) . '/';
			}

			$backup_sizes = get_post_meta( $post_id, '_wp_attachment_backup_sizes', true );
			if ( is_array( $backup_sizes ) && !empty( $backup_sizes ) ) {
				foreach( $backup_sizes as $key => $data) {
					if ( !empty($data['file'] ) ) {
						$original_images[] = $prepend_path.$data['file'];
					}
				}
			}
		}
		if ( $wpmc_debug ) {
			error_log( 'Original backup images: ' );
			error_log( var_export( $original_images, true ) );
		}
		wp_reset_postdata();
		set_transient( "wpmc_original_images", $original_images, 60 * 60 * 2 );
	}
	return $original_images;
}

function wpmc_get_galleries_images( $force = false ) {
	if ( $force ) {
		delete_transient( "wpmc_galleries_images" );
		$galleries_images = null;
	}
	else {
		$galleries_images = get_transient("wpmc_galleries_images");
	}
	if ( !$galleries_images ) {
		global $wpdb;
		$galleries_images = array();
		$posts = $wpdb->get_col( "SELECT id FROM $wpdb->posts WHERE post_type != 'attachment' AND post_status != 'inherit'" );
		foreach( $posts as $post ) {
			$galleries = get_post_galleries_images( $post );
			foreach( $galleries as $gallery ) {
				foreach( $gallery as $image ) {
					array_push( $galleries_images, $image );
				}
			}
		}

		$post_galleries = get_posts( array(
			'tax_query' => array(
				array(
				  'taxonomy' => 'post_format',
				  'field'    => 'slug',
				  'terms'    => array( 'post-format-gallery' ),
				  'operator' => 'IN'
				)
			)
		) );

		foreach( (array) $post_galleries as $gallery_post ) {
			$arrImages = get_children( 'post_type=attachment&post_mime_type=image&post_parent=' . $gallery_post->ID );
			if ( $arrImages ) {
				foreach( (array) $arrImages as $image_post ) {
					array_push( $galleries_images, $image_post->guid );
				}
			}
		}
		wp_reset_postdata();

		set_transient( "wpmc_galleries_images", $galleries_images, 60 * 60 * 2 );
	}
	return $galleries_images;
}

function wpmc_wp_ajax_wpmc_scan() {
	global $wpdb;
	$medias = $_POST['medias'] == 'true';
	$uploads = $_POST['uploads'] == 'true';
	$path = isset( $_POST['path'] ) ? $_POST['path'] : null;
	$upload_folder = wp_upload_dir();

	if ( $medias || ( $uploads && empty( $path ) ) ) {
		// Reset and prepare all the Attachment IDs of all the galleries
		wpmc_reset_issues();
		delete_transient( 'wpmc_posts_with_shortcode' );
		wpmc_get_galleries_images( true );
		wpmc_get_original_images ( true );
	}

	if ( $uploads ) {
		$files = wpmc_list_uploaded_files( $path ? ( trailingslashit( $upload_folder['basedir'] ) . $path ) : $upload_folder['basedir'] );
		echo json_encode(
			array(
				'results' => $files, 'success' => true, 'message' => __( "Files retrieved.", 'wp-media-cleaner' )
			)
		);
		die();
	}

	if ( $medias ) {
		// Prevent double scanning by removing filesystem entries that we have DB entries for
		$results = $wpdb->get_col( "SELECT p.ID FROM $wpdb->posts p WHERE p.post_status = 'inherit' AND p.post_type = 'attachment'" );
		echo json_encode(
			array( 'results' => $results, 'success' => true, 'message' => __( "Medias retrieved.", 'wp-media-cleaner' ) )
		);
		die();
	}

	// No task.
	echo json_encode( array( 'success' => false, 'message' => __( "No task.", 'wp-media-cleaner' ) ) );
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
	wpmc_clean_dir( dirname( $originalPath ) );
	return true;
}

function wpmc_ignore( $id ) {
	global $wpdb;
	$table_name = $wpdb->prefix . "wpmcleaner";
	$wpdb->query( $wpdb->prepare( "UPDATE $table_name SET ignored = 1 WHERE id = %d", $id ) );
	return true;
}

function wpmc_endsWith( $haystack, $needle )
{
  $length = strlen( $needle );
  if ( $length == 0 )
    return true;
  return ( substr( $haystack, -$length ) === $needle );
}

function wpmc_clean_dir( $dir ) {
	if ( !file_exists( $dir ) )
		return;
	else if ( wpmc_endsWith( $dir, 'uploads' ) )
		return;
	$found = array_diff( scandir( $dir ), array( '.', '..' ) );
	if ( count( $found ) < 1 ) {
		if ( rmdir( $dir ) ) {
			wpmc_clean_dir( dirname( $dir ) );
		}
	}
}

function wpmc_delete( $id ) {
	global $wpdb;
	$table_name = $wpdb->prefix . "wpmcleaner";
	$issue = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ), OBJECT );
	$regex = "^(.*)(\\s\\(\\+.*)$";
	$issue->path = preg_replace('/'.$regex.'/i', '$1', $issue->path); // remove " (+ 6 files)" from path

	//make sure there isn't a media DB entry
	if ( $issue->type == 0 ) {
		$attachmentid = wpmc_find_attachment_id_by_file( $issue->path );
		if ( $attachmentid ) {
			error_log( "File Cleaner: Issue listed as filesystem but media db exists (#{$attachmentid})." );
		}
	}

	// Empty trash
	if ( $issue->deleted == 1 ) {
		$trashPath = trailingslashit( wpmc_trashdir() ) . $issue->path;
		if ( !unlink( $trashPath ) )
			error_log( 'Failed to delete the file.' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE id = %d", $id ) );
		wpmc_clean_dir( dirname( $trashPath ) );
		return true;
	}

	// Remove file
	if ( get_transient( "wpmc_deleted_one" ) )
		return false;
	else if ( !wpmc_is_pro() )
		set_transient( "wpmc_deleted_one", true, 4 );

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

$wpmc_exclude_dir = array( ".", "..", "wpmc-trash", ".htaccess", "ptetmp", "profiles", "sites" );

function wpmc_list_uploaded_files( $dir ) {
	global $wpmc_exclude_dir;
	$result = array();
	$files = scandir( $dir );
	$files = array_diff( $files, $wpmc_exclude_dir );
	foreach( $files as $file ) {
		$fullpath = trailingslashit( $dir ) . $file;
		if ( !wpmc_getoption( 'scan_non_ascii', 'wpmc_basics', false ) ) {
			if ( mb_detect_encoding( $file, 'ASCII', true ) === false )
				continue;
		}
		array_push( $result, array( 'path' => wpmc_clean_uploaded_filename( $fullpath ), 'type' => is_dir( $fullpath ) ? 'dir' : 'file' ) );
	}
	return $result;
}

function wpmc_check_is_ignore( $file ) {
	global $wpdb, $wpmc_debug;
	$wp_4dot0_plus = version_compare( get_bloginfo('version'), '4.0', '>=' );
	$table_name = $wpdb->prefix . "wpmcleaner";
	if ( $wp_4dot0_plus ) {
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE deleted = 0 AND path LIKE '%".  esc_sql( $wpdb->esc_like( $file ) ) . "%'" );
	} else {
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE deleted = 0 AND path LIKE '%". like_escape( esc_sql( $file ) ) ."%'" );
	}
	if ($wpmc_debug && $count > 0) error_log("{$file} found in IGNORE");
	return ($count > 0);
}

function wpmc_check_db_has_background_or_header( $file ) {
	global $wpmc_debug;
	if ( current_theme_supports( 'custom-header' ) ) {
		$custom_header = get_custom_header();
		if ( $custom_header && $custom_header->url ) {
			if ( strpos( $custom_header->url, $file ) !== false )
				if ($wpmc_debug) error_log("{$file} found in header");
				return true;
		}
	}

	if ( current_theme_supports( 'custom-background' ) ) {
		$custom_background = get_theme_mod('background_image');
		if ( $custom_background ) {
			if ( strpos( $custom_background, $file ) !== false )
				if ($wpmc_debug) error_log("{$file} found in background");
				return true;
		}
	}

	return false;
}

function wpmc_check_in_gallery( $file ) {
	global $wpmc_debug;
	$file = wpmc_clean_uploaded_filename($file);

	$uploads = wp_upload_dir();
	$parsedURL = parse_url($uploads['baseurl']);
	$regex_match_file = '('.preg_quote($file).')';
	$regex = addcslashes('(?:(?:http(?:s)?\\:)?//'.preg_quote($parsedURL['host']).')?'.preg_quote($parsedURL['path']).'/'.$regex_match_file, '/');

	$images = wpmc_get_galleries_images();
	foreach ( $images as $image ) {
		$found = preg_match('/'.$regex.'/i', $image);
		if ($wpmc_debug && $found) error_log("{$file} found in GALLERY");
		if ($found) return true;
	}
	return false;
}

function wpmc_check_is_original_backup( $file ) {
	global $wpmc_debug;
	$file = wpmc_clean_uploaded_filename($file);
	$original_images = wpmc_get_original_images();
	if (in_array($file, $original_images)) {
		if ($wpmc_debug) error_log("{$file} found as ORIGINAL BACKUP");
		return TRUE;
	}
	return false;
}

function wpmc_check_db_has_meta( $file, $attachment_id=0 ) {
	global $wpdb, $wpmc_debug;
	$uploads = wp_upload_dir();
	$parsedURL = parse_url($uploads['baseurl']);
	$file = wpmc_clean_uploaded_filename($file);
	$regex_match_file = '('.preg_quote($file).')';
	$regex = addcslashes('(?:(?:(?:http(?:s)?\\:)?//'.preg_quote($parsedURL['host']).')?(?:'.preg_quote($parsedURL['path']).'/)|^)'.$regex_match_file, '/');
	$regex_mysql = str_replace('(?:', '(', $regex);

	if ($attachment_id > 0) {
		$mediaCount = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE post_id != %d AND meta_key != '_wp_attached_file' AND (meta_value REGEXP %s OR meta_value = %d)", $attachment_id, $regex_mysql, $attachment_id )
			);
	} else {
		$mediaCount = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key != '_wp_attached_file' AND meta_value REGEXP %s", $regex_mysql )
			);
	}
	if ( $wpmc_debug && $mediaCount > 0 ) error_log("{$file} found in POSTMETA");
	return ( $mediaCount > 0 );
}


function wpmc_check_db_has_content( $file ) {
	global $wpdb, $wpmc_debug;
	$wp_4dot0_plus = version_compare( get_bloginfo('version'), '4.0', '>=' );

	$file = wpmc_clean_uploaded_filename($file);

	$uploads = wp_upload_dir();
	$parsedURL = parse_url($uploads['baseurl']);
	$regex_match_file = '('.preg_quote($file).')';
	$regex = addcslashes('=[\'"](?:(?:http(?:s)?\\:)?//'.preg_quote($parsedURL['host']).')?'.preg_quote($parsedURL['path']).'/'.$regex_match_file.'(?:\\?[^\'"]*)*[\'"]', '/');
	$regex_mysql = str_replace('(?:', '(', $regex);
	$sql = $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type <> 'revision' AND post_type <> 'attachment' AND post_content REGEXP %s", $regex_mysql );
	$mediaCount = $wpdb->get_var( $sql );
	if ( $wpmc_debug && $mediaCount > 0 )
		error_log("{$file} found in POST_CONTENT");
	if ( $mediaCount > 0 )
		return true;

	// Shortcode support is false by defaul
	$shortcode_support = wpmc_getoption( 'shortcode', 'wpmc_basics', false );
	if ( !$shortcode_support )
		return false;

	// Shortcode analysis
	global $shortcode_tags;
	$active_tags = array_keys( $shortcode_tags );
	if ( !empty( $active_tags ) ) {
		$post_contents = get_transient( 'wpmc_posts_with_shortcode' );
		if ( $post_contents === false ) {
			$post_contents = array();
			$query = array();
			$query[] = "SELECT `ID`, `post_content` FROM {$wpdb->posts}";
			$query[] = "WHERE post_type <> 'revision' AND post_type <> 'attachment'";
			$sub_query = array();
			if ( $wp_4dot0_plus ) {
				foreach ( $active_tags as $tag ) {
					$sub_query[] = "`post_content` LIKE '%[" .  esc_sql( $wpdb->esc_like( $tag ) ) . "%'";
				}
			}
			else {
				foreach ( $active_tags as $tag ) {
					$sub_query[] = "`post_content` LIKE '%[" . like_escape( esc_sql( $tag ) ) . "%'";
				}
			}
			$query[] = "AND (".implode(" OR ", $sub_query).")";
			$sql = join(' ', $query);
			$results = $wpdb->get_results( $sql );
			foreach ( $results as $key => $data ) {
				$post_contents['post_'.$data->ID] = do_shortcode( $data->post_content );
			}
			global $wp_registered_widgets;
			$active_widgets = get_option( 'sidebars_widgets' );
			foreach ( $active_widgets as $sidebar_name => $sidebar_widgets ) {
				if ( $sidebar_name != 'wp_inactive_widgets' && !empty( $sidebar_widgets ) && is_array( $sidebar_widgets ) ) {
					$i = 0;
					foreach ( $sidebar_widgets as $widget_instance ) {
						$widget_class = $wp_registered_widgets[$widget_instance]['callback'][0]->option_name;
						$instance_id = $wp_registered_widgets[$widget_instance]['params'][0]['number'];
						$widget_data = get_option($widget_class);
						if ( !empty( $widget_data[$instance_id]['text'] ) ) {
							$post_contents['widget_'.$i] = do_shortcode( $widget_data[$instance_id]['text'] );
						}
						$i++;
					}
				}
			}

			if ( !empty( $post_contents ) ) {
				set_transient( 'wpmc_posts_with_shortcode', $post_contents, 2 * 60 * 60 );
			}
		}

		if ( !empty( $post_contents ) ) {
			foreach ( $post_contents as $key => $content ) {
				$found = preg_match( '/' . $regex . '/i', $content );
				if ( $wpmc_debug && $found )
					error_log( "File Cleaner: {$file} found in {$key} SHORTCODE/WIDGET" );
				if ( $found )
					return true;
			}
		}
	}
	return false;
}

function wpmc_find_attachment_id_by_file ($file) {
	global $wpdb, $wpmc_debug;
	$postmeta_table_name = $wpdb->prefix . 'postmeta';
	$file = wpmc_clean_uploaded_filename($file);
	$sql = $wpdb->prepare( "SELECT post_id FROM {$postmeta_table_name} WHERE meta_key = '_wp_attached_file' AND meta_value = %s", $file );
	$ret = $wpdb->get_var( $sql );
	if ($wpmc_debug && empty($ret)) error_log('Media DB not found: '.$sql);
	return $ret;
}

// Return true if the files is referenced, false if it is not.
function wpmc_check_file( $path ) {
	global $wpdb, $wpmc_debug;
	$path = stripslashes( $path );
	$filepath = wp_upload_dir();
	$filepath = $filepath['basedir'];
	$filepath = trailingslashit( $filepath ) . $path;

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
	if (wpmc_check_is_ignore( $path )) return true;

	$attachment_id = wpmc_find_attachment_id_by_file( $path );
	$source_path = $path;
	$source_filepath = $filepath;
	if ( in_array( $path_parts['extension'], array('jpg','jpeg','jpe','gif','png','bmp','tif','tiff','ico' ), false ) ) {
		$image_size_regex = "/([_-]\\d+x\\d+(?=\\.[a-z]{3,4}$))/i";
		$potential_filepath = preg_replace( $image_size_regex, '', $filepath );
		// After the dimensions (image size) has been removed, check if the base file
		// exists. If it does, it means this is really an image size and not an independent
		// file that looks like an image size.
		if ( file_exists( $potential_filepath ) ) {
			$source_path = preg_replace( $image_size_regex, '', $path );
			$source_filepath = $potential_filepath;
		}
	}
	$attachment_id_src = wpmc_find_attachment_id_by_file( $source_path );
	if ( empty( $attachment_id_src ) || !file_exists( $source_filepath ) ) {
		if ( wpmc_check_is_original_backup( $path ) ) {
			return TRUE;
		} else {
			$issue = "NO_MEDIA";
		}
	} elseif ( $source_path != $path ) {
		//this is an alternate thumbnail and the source is in the database
		//we would only delete this when the source gets deleted
		//will be checked when the source is scanned
		return true;
	// } elseif ( wpmc_check_in_gallery( $path )
	// 	|| wpmc_check_db_has_background_or_header( $path )
	// 	|| wpmc_check_db_has_meta( $path, $attachment_id  )
	// 	|| wpmc_check_db_has_content( $path )
	// 	) {
	// 	return true;
	} else {
		//$issue = "NO_CONTENT";
		return true;
	}

	//check for different image sizes
	$path_parts = pathinfo( $filepath );
	foreach (glob(trailingslashit($path_parts['dirname']).$path_parts['filename'].'*.'.$path_parts['extension']) as $thumbnail) {
		$thumbnail = wpmc_clean_uploaded_filename($thumbnail);
		$thumbnail_path_parts = pathinfo($thumbnail);
		if ($thumbnail_path_parts['filename'] != $path_parts['filename']) {
			if ($wpmc_debug) error_log("checking THUMBNAIL-IMAGE ".$thumbnail);
			if ( wpmc_check_in_gallery( $thumbnail )
				|| wpmc_check_db_has_background_or_header( $thumbnail )
				|| wpmc_check_db_has_meta( $thumbnail, $attachment_id )
				|| wpmc_check_db_has_content( $thumbnail )
				) {
				return true;
			}
		}
	}

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
	global $wpdb, $wpmc_debug;
	$fullpath = get_attached_file( $attachmentId );
	$mainfile = wpmc_clean_uploaded_filename( $fullpath );
	$baseUp = pathinfo( $mainfile );
	$baseUp = $baseUp['dirname'];
	$size = 0;
	$countfiles = 0;
	$issue = 'NO_CONTENT';
	if ( file_exists( $fullpath ) ) {
		$size = filesize( $fullpath );

		if ( wpmc_check_is_ignore( $mainfile )
			|| wpmc_check_in_gallery( $mainfile )
			|| wpmc_check_db_has_background_or_header( $mainfile )
			|| wpmc_check_db_has_meta( $mainfile, $attachmentId )
			|| wpmc_check_db_has_content( $mainfile ) )
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
					if ($wpmc_debug) error_log("checking MEDIA-IMAGE {$filepath}");
					if ( wpmc_check_in_gallery( $filepath )
						|| wpmc_check_db_has_background_or_header( $filepath )
						|| wpmc_check_db_has_meta( $filepath, $attachmentId )
						|| wpmc_check_db_has_content( $filepath ) )
						return true;
				}
			}
		}
	} else {
		$issue = 'ORPHAN_MEDIA';
	}

	$table_name = $wpdb->prefix . "wpmcleaner";
	$wpdb->insert( $table_name,
		array(
			'time' => current_time('mysql'),
			'type' => 1,
			'size' => $size,
			'path' => $mainfile . ( $countfiles > 0 ? ( " (+ " . $countfiles . " files)" ) : "" ),
			'postId' => $attachmentId,
			'issue' => $issue
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

function wpmc_admin_inline_js() {
	echo "<script type='text/javascript'>\n";
	echo 'var wpmc_cfg = { isPro: ' . ( wpmc_is_pro()  ? '1' : '0') . ', scanFiles: ' . ( wpmc_getoption( 'scan_files', 'wpmc_basics', false ) ? '1' : '0' ) . ', scanMedia: ' . ( wpmc_getoption( 'scan_media', 'wpmc_basics', false ) ? '1' : '0' ) . ' };';
	echo "\n</script>";
}

function echo_issue( $issue ) {
	if ( $issue == 'NO_CONTENT' ) {
		_e( "Seems not in use.", 'wp-media-cleaner' );
	}
	else if ( $issue == 'NO_MEDIA' ) {
		_e( "Not in Media Library.", 'wp-media-cleaner' );
	}
	else if ( $issue == 'ORPHAN_RETINA' ) {
		_e( "Orphan retina.", 'wp-media-cleaner' );
	}
	else if ( $issue == 'ORPHAN_MEDIA' ) {
		_e( "File not found.", 'wp-media-cleaner' );
	}
	else {
		echo $issue;
	}
}

function wpmc_screen() {
	?>
	<div class='wrap'>
		<?php jordy_meow_donation(); ?>
		<h2>WP Media Cleaner <?php by_jordy_meow(); ?></h2>

		<?php
			global $wpdb;
			$posts_per_page = 15;
			$view = isset ( $_GET[ 'view' ] ) ? sanitize_text_field( $_GET[ 'view' ] ) : "issues";
			$paged = isset ( $_GET[ 'paged' ] ) ? sanitize_text_field( $_GET[ 'paged' ] ) : 1;
			$reset = isset ( $_GET[ 'reset' ] ) ? $_GET[ 'reset' ] : 0;
			if ( $reset ) {
				wpmc_reset();
			}
			$s = isset ( $_GET[ 's' ] ) ? sanitize_text_field( $_GET[ 's' ] ) : null;
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

		<div style='margin-top: 0px; background: #FFF; padding: 5px; border-radius: 4px; height: 28px; box-shadow: 0px 0px 6px #C2C2C2;'>

			<!-- SCAN -->
			<?php if ( $view != 'deleted' ) { ?>
				<a id='wpmc_scan' onclick='wpmc_scan()' class='button-primary' style='float: left;'><img style='position: relative; top: 3px; left: -2px; margin-right: 3px; width: 16px; height: 16px;' src='<?php echo trailingslashit( WP_PLUGIN_URL ) . trailingslashit( 'wp-media-cleaner/img'); ?>search.png' /><?php _e("Scan", 'wp-media-cleaner'); ?></a>
			<?php } ?>

			<!-- DELETE SELECTED -->
			<a id='wpmc_delete' onclick='wpmc_delete()' class='button' style='float: left; margin-left: 5px;'><img style='position: relative; top: 3px; left: -2px; margin-right: 3px; width: 16px; height: 16px;' src='<?php echo trailingslashit( WP_PLUGIN_URL ) . trailingslashit( 'wp-media-cleaner/img'); ?>delete.png' /><?php _e("Delete", 'wp-media-cleaner'); ?></a>
			<?php if ( $view == 'deleted' ) { ?>
				<a id='wpmc_recover' onclick='wpmc_recover()' class='button-secondary' style='float: left; margin-left: 5px;'><img style='position: relative; top: 3px; left: -2px; margin-right: 3px; width: 16px; height: 16px;' src='<?php echo trailingslashit( WP_PLUGIN_URL ) . trailingslashit( 'wp-media-cleaner/img'); ?>pills.png' /><?php _e("Recover", 'wp-media-cleaner'); ?></a>
			<?php } ?>

			<!-- IGNORE SELECTED -->
			<a id='wpmc_ignore' onclick='wpmc_ignore()' class='button' style='float: left; margin-left: 5px;'><img style='position: relative; top: 3px; left: -2px; margin-right: 3px; width: 16px; height: 16px;' src='<?php echo trailingslashit( WP_PLUGIN_URL ) . trailingslashit( 'wp-media-cleaner/img'); ?>tick.png' /><?php _e("Ignore", 'wp-media-cleaner'); ?></a>

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
					<input type="search" name="s" style="width: 120px;" value="<?php echo $s ? $s : ""; ?>">
					<input type="hidden" name="page" value="wp-media-cleaner">
					<input type="hidden" name="view" value="<?php echo $view; ?>">
					<input type="hidden" name="paged" value="<?php echo $paged; ?>">
					<input type="submit" class="button" value="<?php _e( 'Search', 'wp-media-cleaner' ) ?>"><span style='border-right: #A2A2A2 solid 1px; margin-left: 5px; margin-right: 3px;'>&nbsp;</span>
				</p>
			</form>

			<!-- PROGRESS -->
			<span style='margin-left: 12px; font-size: 15px; top: 5px; position: relative; color: #747474;' id='wpmc_progression'></span>

		</div>

		<p>
			<?php
				$scan_files = wpmc_getoption( 'scan_files', 'wpmc_basics', false );
				$scan_media = wpmc_getoption( 'scan_media', 'wpmc_basics', false );

				echo "<p>";
				_e( "<b>Backup your database and files before delete! This is important.</b> The files deleted by the plugin will be moved to the 'uploads/wpmc-trash' directory and you can trash them later. I am trying my best to make this plugin safe but since it deletes files on a very dynamic system (= WordPress), it is impossible to predict every single situation. Be careful.", 'wp-media-cleaner' );
				if ( $scan_media )
					_e( " If you delete an item of the type MEDIA, the database entry for it (Media Library) will be deleted permanently.", 'wp-media-cleaner' );
				echo "</p>";

				if ( !$scan_files && !$scan_media ) {
					echo "<div class=\"error\"><p>";
					_e( "Scan is not enabled for neither the files or for the medias. The plugin is basically disabled. <b>Please check Settings > Media Cleaner</b>.", 'wp-media-cleaner' );
					echo "</p></div>";
				}
				else if ( !wpmc_is_pro() ) {
					echo "<div class=\"updated\"><p>";
					_e( "<b>This version is not Pro.</b> It will only let you delete the files <u><b>one by one</b></u>, for your safety. <b>If the plugin works perfectly for deleting the files one by one then you can <a href='http://apps.meow.fr/wp-media-cleaner'>get the PRO version</a></b>. It will let you delete all the files in one click.", 'wp-media-cleaner' );
					echo "</p></div>";
				}

				echo sprintf( __( 'There are <b>%d issue(s)</b> with your files, accounting for <b>%d MB</b>. Your trash contains <b>%d MB.</b>', 'wp-media-cleaner' ), $issues_count, number_format( $total_size / 1000000, 2 ), number_format( $trash_total_size / 1000000, 2 ) );
			?>
		</p>

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
			<li class="all"><a <?php if ( $view == 'ignored' ) echo "class='current'"; ?> href='?page=wp-media-cleaner&s=<?php echo $s; ?>&view=ignored'><?php _e( "Ignored", 'wp-media-cleaner' ); ?></a><span class="count">(<?php echo $ignored_count; ?>)</span></li> |
			<li class="all"><a <?php if ( $view == 'deleted' ) echo "class='current'"; ?> href='?page=wp-media-cleaner&s=<?php echo $s; ?>&view=deleted'><?php _e( "Trash", 'wp-media-cleaner' ); ?></a><span class="count">(<?php echo $deleted_count; ?>)</span></li>
		</ul>

		<table id='wpmc-table' class='wp-list-table widefat fixed media'>

			<thead>
				<tr>
					<th scope="col" id="cb" class="manage-column column-cb check-column"><input id="wpmc-cb-select-all" type="checkbox" <?php echo ( wpmc_is_pro() ? '' : 'disabled' ) ?>></th>
					<?php if ( !wpmc_getoption( 'hide_thumbnails', 'wpmc_basics', false ) ): ?>
					<th style='width: 64px;'><?php _e( 'Thumb', 'wp-media-cleaner' ) ?></th>
					<?php endif; ?>
					<th style='width: 50px;'><?php _e( 'Type', 'wp-media-cleaner' ) ?></th>
					<th style='width: 80px;'><?php _e( 'Origin', 'wp-media-cleaner' ) ?></th>
					<th><?php _e( 'Path', 'wp-media-cleaner' ) ?></th>
					<th style='width: 220px;'><?php _e( 'Issue', 'wp-media-cleaner' ) ?></th>
					<th style='width: 80px; text-align: right;'><?php _e( 'Size', 'wp-media-cleaner' ) ?></th>
				</tr>
			</thead>

			<tbody>
				<?php foreach ( $items as $issue ) { ?>
				<tr>
					<td><input type="checkbox" name="id" value="<?php echo $issue->id ?>"></td>
					<?php if ( !wpmc_getoption( 'hide_thumbnails', 'wpmc_basics', false ) ): ?>
					<td>
						<?php
							if ( $issue->deleted == 0 ) {
								if ( $issue->type == 0 ) {
									// FILE
									$upload_dir = wp_upload_dir();
									echo "<img style='max-width: 48px; max-height: 48px;' src='" . htmlspecialchars( $upload_dir['baseurl'] . '/' . $issue->path, ENT_QUOTES ) . "' />";
								}
								else {
									// MEDIA
									$attachmentsrc = wp_get_attachment_image_src( $issue->postId, 'thumbnail' );
									echo "<img style='max-width: 48px; max-height: 48px;' src='" . htmlspecialchars( $attachmentsrc[0], ENT_QUOTES ) . "' />";
								}
							}
						?>
					</td>
					<?php endif; ?>
					<td><?php echo $issue->type == 0 ? 'FILE' : 'MEDIA'; ?></td>
					<td><?php echo $issue->type == 0 ? 'Filesystem' : ("<a href='media.php?attachment_id=" . $issue->postId . "&action=edit'>ID " . $issue->postId . "</a>"); ?></td>
					<td><?php echo stripslashes( $issue->path ); ?></td>
					<td><?php echo_issue( $issue->issue ); ?></td>
					<td style='text-align: right;'><?php echo number_format( $issue->size / 1000, 2 ); ?> KB</td>
				</tr>
				<?php } ?>
			</tbody>

			<tfoot>
				<tr><th></th>
				<?php if ( !wpmc_getoption( 'hide_thumbnails', 'wpmc_basics', false ) ): ?>
				<th></th>
				<?php endif; ?>
				<th><?php _e( 'Type', 'wp-media-cleaner' ) ?></th><th><?php _e( 'Origin', 'wp-media-cleaner' ) ?></th><th><?php _e( 'Path', 'wp-media-cleaner' ) ?></th><th><?php _e( 'Issue', 'wp-media-cleaner' ) ?></th><th style='width: 80px; text-align: right;'><?php _e( 'Size', 'wp-media-cleaner' ) ?></th></tr>
			</tfoot>

		</table>
	</wrap>

	<?php
	jordy_meow_footer();
}

/**
 *
 * PRO
 * Come on, it's not so expensive :'(
 *
 */

function wpmc_is_pro() {
	$validated = get_transient( 'wpmc_validated' );
	if ( $validated ) {
		$serial = get_option( 'wpmc_pro_serial');
		return !empty( $serial );
	}
	$subscr_id = get_option( 'wpmc_pro_serial', "" );
	if ( !empty( $subscr_id ) )
		return wpmc_validate_pro( wpmc_getoption( "subscr_id", "wpmc_pro", array() ) );
	return false;
}

function wpmc_validate_pro( $subscr_id ) {
	if ( empty( $subscr_id ) ) {
		delete_option( 'wpmc_pro_serial', "" );
		delete_option( 'wpmc_pro_status', "" );
		set_transient( 'wpmc_validated', false, 0 );
		return false;
	}
	require_once ABSPATH . WPINC . '/class-IXR.php';
	require_once ABSPATH . WPINC . '/class-wp-http-ixr-client.php';
	$client = new WP_HTTP_IXR_Client( 'http://apps.meow.fr/xmlrpc.php' );
	$client->useragent = 'MeowApps';
	if ( !$client->query( 'meow_sales.auth', $subscr_id, 'media-cleaner', get_site_url() ) ) {
		update_option( 'wpmc_pro_serial', "" );
		update_option( 'wpmc_pro_status', "A network error: " . $client->getErrorMessage() );
		set_transient( 'wpmc_validated', false, 0 );
		return false;
	}
	$post = $client->getResponse();
	if ( !$post['success'] ) {
		if ( $post['message_code'] == "NO_SUBSCRIPTION" ) {
			$status = __( "Your serial does not seem right.", 'wp-media-cleaner' );
		}
		else if ( $post['message_code'] == "NOT_ACTIVE" ) {
			$status = __( "Your subscription is not active.", 'wp-media-cleaner' );
		}
		else if ( $post['message_code'] == "TOO_MANY_URLS" ) {
			$status = __( "Too many URLs are linked to your subscription.", 'wp-media-cleaner' );
		}
		else {
			$status = __( "There is a problem with your subscription.", 'wp-media-cleaner' );
		}
		update_option( 'wpmc_pro_serial', "" );
		update_option( 'wpmc_pro_status', $status );
		set_transient( 'wpmc_validated', false, 0 );
		return false;
	}
	set_transient( 'wpmc_validated', $subscr_id, 3600 * 24 * 100 );
	update_option( 'wpmc_pro_serial', $subscr_id );
	update_option( 'wpmc_pro_status', __( "Your subscription is enabled.", 'wp-media-cleaner' ) );
	return true;
}

/**
 *
 * PLUGIN INSTALL / UNINSTALL / SCRIPTS
 *
 */

function wpmc_check_db() {
	global $wpdb;
	$tbl_m = $wpdb->prefix . 'wpmcleaner';
	if ( !$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = '%s' AND table_name = '%s';", $wpdb->dbname, $tbl_m ) ) ) {
		wpmc_activate();
	}
}

function wpmc_admin_menu() {
	load_plugin_textdomain( 'wp-media-cleaner', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	add_media_page( 'Media Cleaner', 'Media Cleaner', 'manage_options', 'wp-media-cleaner', 'wpmc_screen' );
	add_options_page( 'Media Cleaner', 'Media Cleaner', 'manage_options', 'wpmc_settings', 'wpmc_settings_page' );
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
		ignored TINYINT(1) NOT NULL DEFAULT 0,
		deleted TINYINT(1) NOT NULL DEFAULT 0,
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
	wp_enqueue_script( 'wp-media-cleaner', plugins_url( '/wp-media-cleaner.js', __FILE__ ), array( 'jquery' ), "2.6.0", true );
}
