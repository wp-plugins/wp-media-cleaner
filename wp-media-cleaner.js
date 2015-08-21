/*
Plugin Name: WP Media Cleaner
Description: Clean your Media Library and Uploads Folder.
Author: Jordy Meow
*/

function wpmc_pop_array(items, count) {
	var newItems = [];
	while ( newItems.length < count && items.length > 0 ) {
		newItems.push( items.pop() );
	}
	return newItems;
}

/**
 *
 * RECOVER
 *
 */

function wpmc_recover() {
	var items = [];
	jQuery('#wpmc-table input:checked').each(function (index) {
		if (jQuery(this)[0].value != 'on') {
			items.push(jQuery(this)[0].value);
		}
	});
	wpmc_recover_do(items, items.length);
}

function wpmc_recover_all() {
	var items = [];
	var data = { action: 'wpmc_get_all_deleted' };
	jQuery.post(ajaxurl, data, function (response) {
		reply = jQuery.parseJSON(response);
		if ( !reply.success ) {
			alert( reply.message );
			return;
		}
		wpmc_recover_do(reply.results.ids, reply.results.ids.length);
	});
}

function wpmc_recover_do(items, totalcount) {
	wpmc_update_progress(totalcount - items.length, totalcount);
	if (items.length > 0) {
		newItems = wpmc_pop_array(items, 5);
		data = { action: 'wpmc_recover_do', data: newItems };
	}
	else {
		jQuery('#wpmc_progression').html("Done. Please <a href='?page=wp-media-cleaner'>refresh</a> this page.");
		return;
	}
	jQuery.post(ajaxurl, data, function (response) {
		reply = jQuery.parseJSON(response);
		if ( !reply.success ) {
			alert( reply.message );
			return;
		}
		wpmc_recover_do(items, totalcount);
	});
}

/**
 *
 * DELETE
 *
 */

function wpmc_ignore() {
	var items = [];
	jQuery('#wpmc-table input:checked').each(function (index) {
		if (jQuery(this)[0].value != 'on') {
			items.push(jQuery(this)[0].value);
		}
	});
	wpmc_ignore_do(items, items.length);
}

function wpmc_delete() {
	var items = [];
	jQuery('#wpmc-table input:checked').each(function (index) {
		if (jQuery(this)[0].value != 'on') {
			items.push(jQuery(this)[0].value);
		}
	});
	wpmc_delete_do(items, items.length);
}

function wpmc_delete_all(isTrash) {
	var items = [];
	var data = { action: 'wpmc_get_all_issues', isTrash: isTrash ? 1 : 0 };
	if (!isTrash && !wpmc_cfg.isPro) {
		alert("You need the Pro version to delete all the files at once.");
		return;
	}
	jQuery.post(ajaxurl, data, function (response) {
		reply = jQuery.parseJSON(response);
		if ( !reply.success ) {
			alert( reply.message );
			return;
		}
		wpmc_delete_do(reply.results.ids, reply.results.ids.length);
	});
}

function wpmc_update_progress(current, totalcount) {
	jQuery('#wpmc_progression').html('<span class="dashicons dashicons-controls-play"></span> ' + current + "/" + totalcount + " (" + Math.round(current / totalcount * 100) + "%)");
}

function wpmc_delete_do(items, totalcount) {
	wpmc_update_progress(totalcount - items.length, totalcount);
	if (items.length > 0) {
		newItems = wpmc_pop_array(items, 5);
		data = { action: 'wpmc_delete_do', data: newItems };
	}
	else {
		jQuery('#wpmc_progression').html("Done. Please <a href='?page=wp-media-cleaner'>refresh</a> this page.");
		return;
	}
	jQuery.post(ajaxurl, data, function (response) {
		reply = jQuery.parseJSON(response);
		if ( !reply.success ) {
			alert( reply.message );
			return;
		}
		wpmc_delete_do(items, totalcount);
	});
}

function wpmc_ignore_do(items, totalcount) {
	wpmc_update_progress(totalcount - items.length, totalcount);
	if (items.length > 0) {
		newItems = wpmc_pop_array(items, 5);
		data = { action: 'wpmc_ignore_do', data: newItems };
	}
	else {
		jQuery('#wpmc_progression').html("Done. Please <a href='?page=wp-media-cleaner'>refresh</a> this page.");
		return;
	}
	jQuery.post(ajaxurl, data, function (response) {
		reply = jQuery.parseJSON(response);
		if ( !reply.success ) {
			alert( reply.message );
			return;
		}
		wpmc_ignore_do(items, totalcount);
	});
}

/**
 *
 * SCAN
 *
 */

var wpmc = {
	dirs: [],
	files: [],
	medias: [],
	total: 0,
	issues: 0
};

function wpmc_scan_type(type, path) {
	var data = { action: 'wpmc_scan', medias: type === 'medias', uploads: type === 'uploads', path: path };
	if (path) {
		elpath = path.replace(/^.*[\\\/]/, '');
		jQuery('#wpmc_progression').html('<span class="dashicons dashicons-portfolio"></span> Read files (' + elpath + ')...');
	}
	else if (type === 'medias')
		jQuery('#wpmc_progression').html('<span class="dashicons dashicons-admin-media"></span> Read medias...');
	else
		jQuery('#wpmc_progression').html('<span class="dashicons dashicons-portfolio"></span> Read files...');

	jQuery.post(ajaxurl, data, function (response) {
		reply = jQuery.parseJSON(response);
		if ( !reply.success ) {
			alert( reply.message );
			return;
		}

		// Store results
		for (var i = 0, len = reply.results.length; i < len; i++) {
		  var r = reply.results[i];
			if (type === 'uploads') {
				if ( r.type === 'dir' )
					wpmc.dirs.push( r.path );
				else if ( r.type === 'file' ) {
					wpmc.files.push( r.path );
					wpmc.total++;
				}
			}
			else if (type === 'medias')
				wpmc.medias.push( r );
			wpmc.total++;
		}

		// Next query
		if (type === 'medias') {
			if (wpmc_cfg.scanFiles)
				return wpmc_scan_type('uploads', null);
			else
				return wpmc_scan_do();
		}
		else if (type === 'uploads') {
			var dir = wpmc.dirs.pop();
			if (dir)
				return wpmc_scan_type('uploads', dir);
			else
				return wpmc_scan_do();
		}
	});
}

function wpmc_scan() {
	wpmc = { dirs: [], files: [], medias: [], total: 0, issues: 0 };
	if (wpmc_cfg.scanMedia)
		wpmc_scan_type('medias', null);
	else if (wpmc_cfg.scanFiles)
		wpmc_scan_type('uploads', null);
}

function wpmc_scan_do() {
	wpmc_update_progress(wpmc.total - (wpmc.files.length + wpmc.medias.length), wpmc.total);
	var data = {};
	var expectedSuccess = 0;
	if (wpmc.files.length > 0) {
		newFiles = wpmc_pop_array(wpmc.files, 5);
		expectedSuccess = newFiles.length;
		data = { action: 'wpmc_scan_do', type: 'file', data: newFiles };
	}
	else if (wpmc.medias.length > 0) {
		newMedias = wpmc_pop_array(wpmc.medias, 5);
		expectedSuccess = newMedias.length;
		data = { action: 'wpmc_scan_do', type: 'media', data: newMedias };
	}
	else {
		jQuery('#wpmc_progression').html(wpmc.issues + " issue(s) found. <a href='?page=wp-media-cleaner'></span>Refresh</a>.");
		return;
	}
	jQuery.post(ajaxurl, data, function (response) {
		reply = jQuery.parseJSON(response);
		if ( !reply.success ) {
			alert( reply.message );
			return;
		}
		wpmc.issues += expectedSuccess - reply.result.success;
		wpmc_scan_do();
	});
}

/**
 *
 * INIT
 *
 */

jQuery('#wpmc-table input').change(function () {
	if (wpmc_cfg.isPro)
		return;
	jQuery('#wpmc-table input:checked').attr('checked', false);
	jQuery(this).attr('checked', true);
	return;
});

jQuery('#wpmc-cb-select-all').on('change', function (cb) {
	jQuery('#wpmc-table input').prop('checked', cb.target.checked);
});
