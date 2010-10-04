uch === Plugin Name ===
Contributors: markjaquith
Donate link: http://txfx.net/wordpress-plugins/donate/
Tags: plugin, directory, list, plugin directory
Requires at least: 2.8
Tested up to: 3.0.1
Stable tag: trunk

For plugin authors. Showcase your plugins on your WordPress site. You only update your readme.txt files!

== Description ==

This plugin is for WordPress plugin __authors__, not __users__. It showcases your plugins on your WordPress blog, using the WordPress.org plugin repository as a source. It generates both a directory listing page, and the content of each plugin's page. All you have to provide is the title; the rest comes from the repository. You get to control the markup of each type of page using intuitive WordPress shortcodes.

== Installation ==

1. You're a plugin author. I really hope you know how to install plugins.

== Frequently Asked Questions ==

= Why isn't my plugin pulling information from the repository? =

Its page "slug" must match the "slug" of the plugin in the repository. That is how the match is made.

= I've updated my plugin's `readme.txt` file. Why aren't the changes showing? =

Be patient. The API responses are cached for a little while.


== Changelog ==

= 1.1 =
* Added `[implist_version]`, `[imp_min_version]`, `[imp_tested_version]`, `[imp_slug]`, `[imp_downloads]` shortcodes
* Added optional advanced loop structure for FAQ, using `[imp_faq]`, `[imp_faq_question]`, `[imp_faq_answer]`, `[/imp_faq]`
* Added optional advanced loop structure for changelog, using `[imp_changelog]`, `[imp_changelog_version]`, `[imp_changelog_changes]`, `[imp_changelog_change]`, `[/imp_changelog_changes]`, `[/imp_changelog]`
* Allowed any tag to have a conditional wrapper counterpart by adding `if_` to the front of its shortcode, e.g. `[if_imp_changelog]`
* Better inline documentation
* Moved donation suggestion box to the bottom of the options form

= 1.0 =
* Initial release
