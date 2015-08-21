=== WP Media Cleaner ===
Contributors: TigrouMeow
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=H2S7S3G4XMJ6J
Tags: management, admin, file, files, images, image, media, libary, upload, clean, cleaning
Requires at least: 3.5
Tested up to: 4.3
Stable tag: 2.6.0

Clean your Media Library and Uploads directory from the files which are not used.

== Description ==

Clean your Media Library and Uploads directory from the files which are not used. First, backup all your files and your database. You cannot trust any plugin or any tool to do this automatically. If you don't know what you are doing, simply do not do it. The plugin will go through all your files and will detect if:

- the physical file is linked to a media in the media library
- the media is used in a post, post meta, WP gallery or sidebar widget
- a retina image is orphan (without the base image)

Those file will be shown in a specific dashboard. At this point, it will be up to you to delete them.

Files detected as un-used are added to a specific dashboard where you can choose to trash them. They will be then moved to a trash internal to the plugin. After more testing, you can trash them definitely.

Again, this plugin deletes files so be careful. Backup is really important!

It has been tested with WP Retina 2x and WPML.

Languages: English, French.

== Installation ==

1. Upload `wp-media-cleaner` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go in the Settings -> WP Media Cleaner and check the appropriate options
3. Go in Media -> Media Cleaner

== Upgrade Notice ==

Replace all the files. Nothing else to do.

== Frequently Asked Questions ==

= Is it safe? =
No! :) How can a plugin that deletes files be 100% safe? ;) I did my best (and will improve it in every way I can) but it is impossible to cover all the cases. On a normal WordPress install it should work perfectly, however other themes and plugins can do whatever they want do and register files in their own way, not always going through the API. I ran it on a few big websites and it performed very well. Make a backup (database + uploads directory) then run it. Again, I insist: BACKUP, BACKUP, BACKUP! Don't come here to complain that it deleted your files, because, yes, it deletes files. The plugin tries its best to help you and it is the only plugin that does it well.

= What is 'Reset' doing exactly? =
It re-creates the WP Media Cleaner table in the database. You will need to re-run the scan after this.

= I want to thank you! =
Donations can be made through Paypal here: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=H2S7S3G4XMJ6J. They are really rare but always welcomed :)

= I donated, can I get rid of the donation button? =
Of course. I don't like to see too many of those buttons neither ;) You can disable the donation buttons from all my plugins by adding this to your wp-config.php: `define('WP_HIDE_DONATION_BUTTONS', true);`

== Screenshots ==

1. Media -> Media Cleaner

== Changelog ==

= 2.6.0 =
* Add: Option for resolving shortcode during analysis.
* Update: French translation. Big thanks to Guillaume (and also for all his testing!).

= 2.5.0 =
* Add: Delete the unused directories.
* Add: Doesn't break when there are too many files in the system.
* Add: Pro version with better support.
* Update: Improved detection of unused files.
* Fix: UTF8 filenames skipped by default but can be scanned through an option.
* Fix: Really many fixes :)
* Info: Contact me if you have been using the plugin for a long time and love it.

= 2.4.2 =
* Add: Inclusion of gallery post format images.
* Fix: Better gallery URL matching.
* Info: Thanks to syntax53 for those improvements via GitHub (https://github.com/tigroumeow/wp-media-cleaner/pull/3). Please review Media Cleaner if you like it. The plugin needs reviews to live. Thank you :) (https://wordpress.org/support/view/plugin-reviews/wp-media-cleaner)

= 2.4.0 =
* Fix: Cross site scripting vulnerability fixes.
* Change: Many enhancements and fixes made by Matt (http://www.twistedtek.net/). Please thanks him :)
* Info: Please perform a "Reset" in the plugin dashboard after installing this new version.

= 2.2.6 =
* Fix: Scan for multisite.

= 2.2.4 =
* Change: options are now all enabled by default.

= 2.2.0 =
* Fix: DB issue avoided trashed files from being deleted permanently.

= 2.0.2 =
* Works with WP 4.

= 2.0.0 =
* Gallery support.

= 1.9.4 =
* I did something but not sure what.
* Ah yeah, I got married :)

= 1.9.2 =
* Fix: IGNORE function was... ignored by the scanning process.

= 1.9.0 =
* Add: thumbnails.
* Add: IGNORE function.
* Change: cosmetic changes.

= 1.8.0 =
* Add: now detects the custom header and custom background.
* Change: the CSS was updated to fit the new Admin theme.

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
