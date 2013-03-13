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
		jQuery('#wpmc_progression').html("Done. Please <a href='javascript:history.go(0)'>refresh</a> this page.");
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
	jQuery('#wpmc_progression').text(current + "/" + totalcount + " (" + Math.round(current / totalcount * 100) + "%)");
}

function wpmc_delete_do(items, totalcount) {
	wpmc_update_progress(totalcount - items.length, totalcount);
	if (items.length > 0) {
		newItems = wpmc_pop_array(items, 5);
		data = { action: 'wpmc_delete_do', data: newItems };
	}
	else {
		jQuery('#wpmc_progression').html("Done. Please <a href='javascript:history.go(0)'>refresh</a> this page.");
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

/**
 *
 * SCAN
 *
 */

function wpmc_scan() {
	var data = { action: 'wpmc_scan', library: true, upload: true };
	jQuery('#wpmc_progression').text("Please wait...");
	jQuery.post(ajaxurl, data, function (response) {
		reply = jQuery.parseJSON(response);
		if ( !reply.success ) {
			alert( reply.message );
			return;
		}
		wpmc_scan_do(reply.results.files, reply.results.medias, reply.results.files.length + reply.results.medias.length, 0);
	});
}

function wpmc_scan_do(files, medias, totalcount, totalissues) {
	wpmc_update_progress((totalcount - (files.length + medias.length)), totalcount);
	var data = {};
	var expectedSuccess = 0;
	if (files.length > 0) {
		newFiles = wpmc_pop_array(files, 5);
		expectedSuccess = newFiles.length;
		data = { action: 'wpmc_scan_do', type: 'file', data: newFiles };
	}
	else if (medias.length > 0) {
		newMedias = wpmc_pop_array(medias, 5);
		expectedSuccess = newMedias.length;
		data = { action: 'wpmc_scan_do', type: 'media', data: newMedias };
	}
	else {
		jQuery('#wpmc_progression').html("Done. " + totalissues + " issue(s) found. Please <a href='javascript:history.go(0)'>refresh</a> this page.");
		return;
	}
	jQuery.post(ajaxurl, data, function (response) {
		reply = jQuery.parseJSON(response);
		if ( !reply.success ) {
			alert( reply.message );
			return;
		}
		totalissues += expectedSuccess - reply.result.success;
		wpmc_scan_do(files, medias, totalcount, totalissues);
	});
}

/**
 *
 * INIT
 *
 */

jQuery('#wpmc-cb-select-all').on('change', function (cb) {
	jQuery('#wpmc-table input').prop('checked', cb.target.checked);
});