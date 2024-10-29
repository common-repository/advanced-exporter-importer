=== Advanced Exporter Importer ===
Tags: export, import, custom post type, media, cpt, xml, zip, grandplugins
Tested up to: 5.6
Requires at least: 5.3.0
Requires PHP: 5.4
Stable Tag: trunk
Version: 1.0.0
Contributors: grandplugins
Author: GrandPlugins
Author URI: https://grandplugins.com
Author email: services@grandplugins.com
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html


== Description ==
The plugin export all custom post types and menus including meta fields, categories, terms, comments and all related media in a fast and clean way.

= Features =

* Export and import all posts related content including posts - featured image and in post-content links and attachments
* The export and import process keep track of the categories and terms tree order
* Export and import WooCommerce Variable and Groupd products and all children products
* Import posts authors or remap them to different ones.
* ACF Support: The plugin remaps all ACF relational ACF fields for all posts [ File - Image - Wysiwyg Editor - Gallery - Link - Post Object - Page Link - Relationship - Taxonomy - User ]
* The plugin remaps all built-in shortcodes with the new IDs.

[Pro Version](https://grandplugins.com/product/advanced-exporter-importer-pro/)

* You can choose specific posts from any post type to export
* You can export and import menus with the same tree order and export - import menus items targets [ posts - pages - terms - etc... ]

= How it Works =

*Export Side:*

1. Enter the Settings page in *Tools / Advanced Exporter Importer*
2. Select the post type you want to export
3. Click on *Download Export XML File* button to export the database content xml File
4. Click on *Download Export Attachments Zip File* button to export the related media zip file

*Import Side:*

1. Click on *Import* tab in the plugin settings page
2. import the attachments zip file
3. then import the xml file
4. remap the content authors if you want
5. start importing

= Upcoming Updates =

	* Export - Import WooCommerce Orders and related products

== Changelog ==

	= 1.0.0 =
		First Version

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The Plugin's Page is in *Tools / Advanced Exporter Importer*


== Frequently Asked Questions ==

= Does this plugin work with newest WP version and also older versions? =
Yes, this plugin has been tested with WordPress 5.6, minimum version required is 4.5.0

== screenshots ==

1. Export Filter options
2. Export XML and Attachment zip file
3. Import XML file
4. Remap Authors
5. Select specific posts instead of by filters
6. list seleted posts
7. Export - Import menus
