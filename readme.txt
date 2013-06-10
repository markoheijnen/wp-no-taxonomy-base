=== WP No Taxonomy Base ===
Contributors: Marko Heijnen, Christian Föllmann, Jaime Martinez, Luke Thomas, David DiGiovanni
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=CQFB8UMDTEGGG
Tags: taxonomy, custom taxonomy
Requires at least: 3.3
Tested up to: 3.6
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This is a WordPress plugin to remove the base slug on custom taxonomies.

== Description ==

This is a WordPress plugin to remove the base slug on custom taxonomies. It removes the base slug for both custom taxonomies and the default "category" taxonomy for posts.

== Installation ==

Installation

1. Place plugin files in the /wp-content/plugins/ folder (or download via the dashboard "Add New" page under plugins).

2. Activate the plugin from the "Plugins" page in the dashboard.

3. Go to Settings -> WP No Taxonomy Base to check the taxonomies for which you want to remove the base slug.

This plugin was developed by Luke Thomas (http://twitter.com/luk3thomas) and submitted by David DiGiovanni (http://twitter.com/daviddigiovanni).


== Changelog ==

= 1.1 ( 2013-6-10 ) =
* Fix not working redirect
* Always show correct link to the terms
* Fix notices (Thanks to Jaime Martinez)
* Made plugin translatable (Thanks to Christian Föllmann)
* No direct access to the file (Thanks to Christian Föllmann)
* Add nonce to the settings page
* Cleanup redirect logic to the new urls
* Use the taxonomy labels instead of their names on the settings page
* Cleanup the code even more

= 1.0 ( 2012-8-29 ) =
* First release by Luke Thomas and David DiGiovanni