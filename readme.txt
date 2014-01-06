=== WP Media Cleaner ===
Contributors: TigrouMeow
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=JAWE2XWH7ZE5U
Tags: management, admin, file, files, images, image, media, libary, upload, clean, cleaning
Requires at least: 3.5
Tested up to: 3.8
Stable tag: 1.7.0

Help you cleaning your Uploads Directory and your Media Library.

== Description ==

Help you cleaning your Uploads Directory and your Media Library. It detects files which are in your uploads directory but not referenced anywhere in your WordPress install (posts, pages, media...) + detects the media which are not used anywhere. The deleted files will be moved to a trash directory and can be restored directly through the WP Media Cleaner dashboard.

It has been tested on big websites and handles the retina files as well (and therefore such plugin as WP Retina 2x).

Languages: English, French.

== Installation ==

1. Upload `wp-media-cleaner` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go in Media -> Clean

== Upgrade Notice ==

Replace all the files. Nothing else to do.

== Frequently Asked Questions ==

= Is it safe? =
I am not sure how a plugin that deletes files could be 100% safe ;) I did my best (and will improve it in every way I can) but it's impossible to cover all the cases. I ran it on a few big websites and it performs very well. Make a backup (database + uploads directory) then run it.

= What is 'Reset' doing exactly? =
It re-creates the WP Media Cleaner table in the database. You will need to re-run the scan after this.

= I donated, how can I get rid of the donation button? =
Of course. I don't like to see too many of those buttons neither ;) You can disable the donation buttons from all my plugins by adding this to your wp-config.php: `define('WP_HIDE_DONATION_BUTTONS', true);`

= Can I contact you? =
Please contact me through my website <a href='http://www.totorotimes.com'>Totoro Times</a>. Thanks!

== Screenshots ==

1. Media -> Clean

== Changelog ==

= 1.7.0 =
* Change: the MEDIA files are now going to the trash but the MEDIA reference in the DB is still removed permanently.

= 1.6.0 =
* Stable release.

= 1.4.2 =
* Change: Readme.txt.

= 1.4.0 =
* Add: check the meta properties.
* Add: check the 'featured image' properties.
* Fix: keep the trash information when a new scan is started.
* Fix: remove the DB on uninstall, not on desactivate.

= 1.2.2 =
* Add: progress %.
* Fix: issues with apostrophes in filenames.
* Change: UI cleaning.

= 1.2.0 =
* Add: options (scan files / scan media).
* Fix: mkdir issues.
* Change: operations are buffered by 5 (faster).

= 0.1.0 =
* First release.

== Wishlist ==

Do you have suggestions? Feel free to contact me at <a href='http://www.totorotimes.com'>Totoro Times</a>.