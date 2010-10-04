<?php
/*
Plugin Name: I Make Plugins
Description: Shows off the WordPress plugins you've written
Version: 1.2-beta
Author: Mark Jaquith
Plugin URI: http://txfx.net/wordpress-plugins/i-make-plugins/
Author URI: http://coveredwebservices.com/
License: GPLv2
*/

/*
    Copyright 2010 Mark Jaquith (email: mark.gpl@txfx.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class CWS_I_Make_Plugins {
	static $instance;
	static $version = '1.2';
	var $prevent_recursion = false;

	function __construct() {
		self::$instance =& $this;
		add_action( 'admin_init',  array( $this, 'admin_init'   )     );
		add_action( 'admin_menu',  array( $this, 'admin_menu'   )     );
		add_filter( 'the_content', array( $this, 'plugins_list' ), 15 );
		add_filter( 'the_content', array( $this, 'plugin'       ),  9 );
		add_filter( 'init',        array( $this, 'init'         )     );
	}

	function init() {
		load_plugin_textdomain( 'cws-imp', '', plugin_basename( dirname( __FILE__ ) ) );
		// Add our default options
		add_option( 'cws_imp_plugin_list_template', "<ul id=\"cws-imp-plugin-list\">\n\n[implist]\n<li class=\"cws-imp-plugin\"><a class=\"cws-imp-plugin-title\" href=\"[implist_url]\">[implist_name]</a>\n<p class=\"cws-imp-plugin-description\">[implist_desc]</p>\n</li>\n[/implist]\n\n</ul>" );
		add_option( 'cws_imp_plugin_template', "[imp_full_desc]\n\n<h3>Download</h3>\nLatest version: <a href=\"[imp_zip_url]\">Download <b>[imp_name]</b> v[imp_version]</a> [zip]\n\n[if_imp_installation]\n<h3>Installation</h3>\n[imp_installation]\n[/if_imp_installation]\n\n[if_imp_faq]\n<h3>FAQ</h3>\n[imp_faq]\n[/if_imp_faq]\n\n[if_imp_changelog]\n<h3>Changelog</h3>\n[imp_changelog]\n[/if_imp_changelog]" );

		// Upgrade routines
		$v = get_option( 'cws_imp_current_version' );
		if ( version_compare( $v, self::$version, '<' ) ) {
			foreach ( array( 'list_template', 'template' ) as $t ) {
				$t = 'cws_imp_plugin_' . $t;
				update_option( $t, str_replace( 'imp_if', 'if_imp', get_option( $t ) ) );
			}
			update_option( 'cws_imp_current_version', self::$version );
		}
	}

	function admin_init() {
		// Container Page Section
		add_settings_section( 'cws-imp-settings-container-page', __( 'Container page', 'cws-imp' ), NULL, 'cws-imp-settings' );
		register_setting( 'cws-imp-settings', 'cws_imp_container_id' );
		add_settings_field( 'cws-imp-container-id', __( 'Plugin container page', 'cws-imp' ), array( $this, 'field_container_page' ), 'cws-imp-settings', 'cws-imp-settings-container-page' );

		// Templates
		add_settings_section( 'cws-imp-settings-templates', __( 'Templates', 'cws-imp' ), array( $this, 'about_templates' ), 'cws-imp-settings' );
		register_setting( 'cws-imp-settings', 'cws_imp_plugin_list_template' );
		add_settings_field( 'cws-imp-plugin-list-template', __( 'Plugin list template', 'cws-imp' ), array( $this, 'field_list_template' ), 'cws-imp-settings', 'cws-imp-settings-templates' );
		register_setting( 'cws-imp-settings', 'cws_imp_plugin_template' );
		add_settings_field( 'cws-imp-plugin-template', __( 'Plugin template', 'cws-imp' ), array( $this, 'field_template' ), 'cws-imp-settings', 'cws-imp-settings-templates' );
	}

	function about_templates() {
		_e( '<p>The templating system is based on WordPress Shortcodes, which look like HTML tags but with square brackets.</p>
		<p>Any of the shortcodes can be turned into a conditional wrapper by adding <code>if_</code> to the front of the tag. So to test <code>[implist_version]</code>, you could wrap some code in <code>[if_implist_version]</code> ... <code>[/if_implist_version]</code>.</p>
		<p>Some loop tags can be used in a self-closing form, in which case the plugin will generate the HTML for you. You only have to use the advanced loop format if you want to choose your own HTML for the loop.</p>', 'cws-imp' );
	}

	function field_container_page() {
		wp_dropdown_pages( array( 'name' => 'cws_imp_container_id', 'echo' => 1, 'show_option_none' => __('- Select -'), 'selected' => get_option( 'cws_imp_container_id' ) ) ); ?> <span class="description"><?php esc_html_e( 'Your plugin listing page. Each plugin should be a subpage of this, and each page slug should match its slug in the WordPress.org plugin repository.', 'cws-imp' ); ?></span><?php
	}

	function field_list_template() {
		_e( '<p>This controls what will be displayed on the container page. You can use the following tags to loop through the plugins:</p>
		<p><code>[implist]</code>&mdash;<code>[/implist]</code></p>
		<p>Within that loop, you can use the following tags:</p>
		<p><code>[implist_name]</code> <code>[implist_url]</code> <code>[implist_version]</code> <code>[implist_desc]</code> <code>[implist_zip_url]</code></p>', 'cws-imp' ); ?><textarea rows="20" cols="50" class="large-text code" id="cws_imp_plugin_list_template" name="cws_imp_plugin_list_template"><?php form_option( 'cws_imp_plugin_list_template' ); ?></textarea></fieldset><?php
	}

	function field_template() {
		_e( '<p>This controls what will be displayed on each plugin page. You can use the following tags:</p>
		<p><code>[imp_name]</code> <code>[imp_url]</code> <code>[imp_zip_url]</code> <code>[imp_full_desc]</code> <code>[imp_version]</code> <code>[imp_changelog]</code> <code>[imp_faq]</code> <code>[imp_installation]</code> <code>[imp_min_version]</code> <code>[imp_tested_version]</code> <code>[imp_slug]</code> <code>[imp_downloads]</code></p>
		<p>An example advanced FAQ loop format is as follows:</p>
		<p><code>[imp_faq]</code><br />&mdash;Q. <code>[imp_faq_question]</code><br />&mdash;A. <code>[imp_faq_answer]</code><br /><code>[/imp_faq]</code></p>
		<p>An example advanced Changelog loop format is as follows:</p>
		<p><code>[imp_changelog]</code><br />&mdash;<code>[imp_changelog_version]</code><br />&mdash;&mdash;<code>[imp_changelog_changes]</code><br />&mdash;&mdash;&mdash;<code>[imp_changelog_change]</code><br />&mdash;&mdash;<code>[/imp_changelog_changes]</code><br /><code>[/imp_changelog]</code></p>', 'cws-imp' ); ?>
		<textarea rows="20" cols="50" class="large-text code" id="cws_imp_plugin_template" name="cws_imp_plugin_template"><?php form_option( 'cws_imp_plugin_template' ); ?></textarea><?php
	}

	function get_list_page_id() {
		return get_option( 'cws_imp_container_id' );
	}

	function get_plugins() {
		return new WP_Query( array( 'post_type' => 'page', 'post_parent' => $this->get_list_page_id(), 'showposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	}

	function get_plugin_description( $page_id ) {
		$readme = get_plugin_readme( $page_id );
		if ( $readme )
			return $readme->short_description;
		else
			return ' '; // Why a space? Must investigate further
	}

	function get_plugin_readme( $page_id ) {
		$page = get_page( $page_id );
		$slug = $page->post_name;

		// First, try in-memory cache
		if ( isset( $this->cache[$slug] ) )
			return $this->cache[$slug];

		// Next, try postmeta cache
		$ts = get_post_meta( $page_id, '_cws_imp_readme_timestamp', true );
		$rm = get_post_meta( $page_id, '_cws_imp_readme', true );
		if ( $rm && $ts && $ts > time() - 3600 ) { // fresh
			$this->cache[$slug] = maybe_unserialize( $rm );
			return $this->cache[$slug];
		}

		// Fetch via API
		require_once( ABSPATH . '/wp-admin/includes/plugin-install.php' );
		$readme = plugins_api( 'plugin_information', array('slug' => $slug, 'fields' => array( 'short_description' => true ) ) );
		if ( is_wp_error( $readme ) )
			return false;
		$this->cache[$slug] = $readme;
		update_post_meta( $page_id, '_cws_imp_readme', serialize( $readme ) );
		update_post_meta( $page_id, '_cws_imp_readme_timestamp', time() );
		return $readme;
	}

	function get_readme_url( $slug, $tag ) {
		if ( 'trunk' == $tag )
			return 'http://svn.wp-plugins.org/' . $slug . '/trunk/readme.txt';
		else
			return 'http://svn.wp-plugins.org/' . $slug . '/tags/' . $tag . '/readme.txt';
	}

	function plugin_list_html() {
		global $post;
		$temp_post = $post; // Backup
		$return = do_shortcode( get_option( 'cws_imp_plugin_list_template' ) );
		$post = $temp_post; // Restore
		return $return;
	}

	function shortcode( $atts, $content, $tag ) {
		global $post, $imp_readme, $imp_current_faq, $imp_current_faq_answer, $imp_current_changelog_v, $imp_current_changes, $imp_current_change;
		$imp_readme = $this->get_plugin_readme( $post->ID ); // fetch it, just in case we need it.
		$return = '';
		switch ( $tag ) :
			case 'implist' :
				return $this->shortcode_implist( $atts, $content, $tag );
				break;
			case 'imp_name' :
			case 'implist_name' :
				return get_the_title();
				break;
			case 'imp_version' :
			case 'implist_version' :
				return $imp_readme->version;
				break;
			case 'imp_url' :
			case 'implist_url' :
				return get_permalink();
				break;
			case 'implist_desc' :
				return $post->post_excerpt;
				break;
			case 'implist_zip_url' :
			case 'imp_zip_url' :
				if ( isset( $imp_readme->download_link ) )
					return $imp_readme->download_link;
				break;
			case 'imp_full_desc' :
				if ( isset( $imp_readme->sections['description'] ) )
					return $imp_readme->sections['description'];
				break;
			case 'imp_installation' :
				if ( isset( $imp_readme->sections['installation'] ) )
					return $imp_readme->sections['installation'];
				break;
			case 'imp_screenshots' :
				if ( isset( $imp_readme->sections['screenshots'] ) )
					return $imp_readme->sections['screenshots'];
				break;
			case 'imp_other_notes' :
				if ( isset ( $imp_readme->sections['other_notes'] ) )
					return $imp_readme->sections['other_notes'];
				break;
			case 'imp_changelog' :
				if ( isset( $imp_readme->sections['changelog'] ) ) {
					$imp_changes = $this->parse_changelog( $imp_readme->sections['changelog'] );
					if ( $content ) {
						$shortcodes = array( 'imp_changelog_version', 'imp_changelog_changes', 'imp_changelog_change' );
						$this->add_shortcodes( $shortcodes );
						foreach ( (array) $imp_changes as $imp_current_changelog_v => $imp_current_changes )
							$return .= do_shortcode( $content );
						$this->remove_shortcodes( $shortcodes );
						unset( $imp_current_changelog_v, $imp_current_changes, $imp_current_change );
						return $return;
					} else {
						return $this->output_changelog( $imp_changes );
					}
				}
				break;
			case 'imp_changelog_version' :
				return $imp_current_changelog_v;
				break;
			case 'imp_changelog_changes' :
				$shortcodes = array( 'imp_changelog_change' );
				foreach ( (array) $imp_current_changes as $imp_current_change )
					$return .= do_shortcode( $content );
				return $return;
				break;
			case 'imp_changelog_change' :
				return $imp_current_change;
				break;
			case 'imp_faq' :
				if ( isset( $imp_readme->sections['faq'] ) ) {
					$imp_faqs = $this->parse_faq( $imp_readme->sections['faq'] );
					if ( $content ) {
						$shortcodes = array( 'imp_faq_question', 'imp_faq_answer' );
						$this->add_shortcodes( $shortcodes );
						foreach ( $imp_faqs as $imp_current_faq => $imp_current_faq_answer )
							$return .= do_shortcode( $content );
						$this->remove_shortcodes( $shortcodes );
						unset( $imp_current_faq, $imp_current_faq_answer );
						return $return;
					} else {
						return $this->output_faq( $imp_faqs );
					}
				}
				break;
			case 'imp_faq_question' :
				return $imp_current_faq;
				break;
			case 'imp_faq_answer' :
				return $imp_current_faq_answer;
				break;
			case 'imp_min_version' :
				return $imp_readme->requires;
				break;
			case 'imp_tested_version' :
				return $imp_readme->tested;
				break;
			case 'imp_slug' :
				return $imp_readme->slug;
				break;
			case 'imp_downloads' :
				return $imp_readme->downloaded;
				break;
		endswitch;
	}

	function shortcode_conditional( $atts, $content, $tag ) {
		$test_tag = preg_replace( '#^if_#', '', $tag );
		$test_output = $this->shortcode( NULL, NULL, $test_tag );
		if ( !empty( $test_output ) )
			return do_shortcode( $content );
	}

	function shortcode_implist( $atts, $content = NULL ) {
		global $post;
		$plugins = $this->get_plugins();
		$return = '';
		while ( $plugins->have_posts() ) : $plugins->the_post();
			if ( get_post_meta( $post->ID, '_cws_imp_retired_plugin', true ) )
				continue; // TO-DO: UI for this
			$post->post_excerpt = trim( $this->get_plugin_description( $post->ID ) );
			if ( empty( $post->post_excerpt ) )
				$post->post_excerpt = __( 'No description', 'cws-imp' );
			$return .= do_shortcode( $content );
		endwhile;
		return $return;
	}

	function add_shortcodes( $array ) {
		foreach ( (array) $array as $shortcode ) {
			$conditional = 'if_' . $shortcode;
			add_shortcode( $shortcode, array( $this, 'shortcode' ) );
			add_shortcode( $conditional, array( $this, 'shortcode_conditional' ) );
		}
	}

	function remove_shortcodes( $array ) {
		foreach ( (array) $array as $shortcode ) {
			$conditional = 'if_' . $shortcode;
			remove_shortcode( $shortcode );
			remove_shortcode( $conditional );
		}
	}

	function plugins_list( $content ) {
		global $post;
		if ( ( isset( $this->prevent_recursion ) && $this->prevent_recursion ) || $post->ID != $this->get_list_page_id() ) {
			return $content;
		} else {
			$this->prevent_recursion = true;
			$shortcodes = array( 'implist', 'implist_name', 'implist_url', 'implist_version', 'implist_desc', 'implist_zip_url' );
			$this->add_shortcodes( $shortcodes );
			$content = $this->plugin_list_html() . $content;
			$this->remove_shortcodes( $shortcodes );

			$this->prevent_recursion = false;
			return $content;
		}
	}

	function parse_faq( $faq ) {
		$faq = preg_split( '#<h4>#ims', $faq );
		array_shift( $faq );
		$questions = array();

		foreach ( (array) $faq as $f ) {
			$f = '<h4>' . $f;
			preg_match('#<h4>(.*?)</h4>#ims', $f, $matches );
			$q = trim( $matches[1] );
			$a = trim( str_replace( $matches[0], '', $f ) );
			$a = trim( str_replace( array( '<p>', '</p>' ), array( '', '' ), $a ) );
			$questions[$q] = $a;
		}
		return $questions;
	}

	function output_faq( $questions ) {
		$return = '';
		foreach ( (array) $questions as $q => $a ) {
				$return .= '<strong>Q. ' . $q . '</strong>' . "\n";
				$return .= '<strong>A.</strong> ' . $a . "\n\n";
		}
		return $return;
	}

	function parse_changelog( $changelog ) {
		$changelog = preg_split( "#<h4>#ims", $changelog );
		array_shift( $changelog );
		$changes = array();

		foreach ( (array) $changelog as $c ) {
			$c = '<h4>' . $c;
			preg_match('#<h4>(.*?)</h4>#ims', $c, $matches );
			$v = trim( $matches[1] );
			$cs = trim( str_replace( $matches[0], '', $c ) );
			preg_match_all( '#<li>(.*)</li>#ims', $cs, $change_matches );
			$changes[$v] = $change_matches[1];
		}

		return $changes;
	}

	function output_changelog( $changes ) {
		$return = '';
		foreach ( (array) $changes as $v => $cs ) {
			$return .= "<h4>$v</h4>\n<ul>\n";
			foreach ( (array) $cs as $c ) {
				$return .= "<li>$c</li>\n";
			}
			$return .= "</ul>\n\n";
		}
		return $return;
	}

	function plugin( $content ) {
		global $post, $imp_readme;
		if ( get_post_meta( $post->ID, '_cws_imp_retired_plugin', true ) )
			$content = __( '<p><strong>This plugin has been marked as retired. It is recommended that you no longer use it.</strong></p>', 'cws-imp' );
		if ( $post->post_parent && $post->post_parent == get_option( 'cws_imp_container_id' ) ) {
			$imp_readme = $this->get_plugin_readme( $post->ID );
			if ( $imp_readme ) {
				$shortcodes = array( 'imp_name', 'imp_url', 'imp_zip_url', 'imp_full_desc', 'imp_if_installation', 'imp_installation', 'imp_if_changelog', 'imp_changelog', 'imp_if_faq', 'imp_faq', 'imp_version', 'imp_min_version', 'imp_tested_version', 'imp_slug', 'imp_downloads', 'imp_screenshots', 'imp_other_notes' );
				$this->add_shortcodes( $shortcodes );
				$content = '';
				$content .= do_shortcode( get_option( 'cws_imp_plugin_template' ) );
				$this->remove_shortcodes( $shortcodes );
			}
			$content = apply_filters( 'cws_imp_plugin_body', $content );
		}
		return $content;
	}

	function admin_menu() {
		$hook = add_options_page( __( 'I Make Plugins', 'cws-imp' ), __( 'I Make Plugins', 'cws-imp' ), 'manage_options', 'cws-imp', array( $this, 'options_page' ) );
	}

	function options_page() {
	?>
	<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php esc_html_e( 'I Make Plugins Settings', 'cws-imp' ); ?></h2>

	<form action="options.php" method="post">
		<?php settings_fields( 'cws-imp-settings' ); ?>
		<?php do_settings_sections( 'cws-imp-settings' ); ?>
		<p class="submit"><input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ); ?>" /></p>
	</form>

	<style>
	#cws-imp-donate {
		float: left;
		width: 250px;
		padding: 0 10px;
		background: #464646;
		color: #fff;
		-moz-border-radius: 5px;
		-webkit-border-radius: 5px;
	}
	#cws-imp-donate img {
		float: left;
		margin-right: 5px;
		-moz-border-radius: 5px;
		-webkit-border-radius: 5px;
	}
	#cws-imp-donate a {
		color: #ff0;
	}
	#cws-imp-donate a:hover {
		color: #fff;
	}

	</style>
	<div id="cws-imp-donate">
	<p><img src="http://www.gravatar.com/avatar/5f40ed513eae85b532e190c012785df7?s=64" height="64" width="64" /><?php esc_html_e( 'Hi there! If you enjoy this plugin, consider showing your appreciation by making a small donation to its author!', 'cws-imp' ); ?></p>
	<p style="text-align: center"><a href="http://txfx.net/wordpress-plugins/donate" target="_new"><?php esc_html_e( 'Click here to donate using PayPal' ); ?></a></p>
	</div>
	</div>
	<?php
	}

}

new CWS_I_Make_Plugins;
