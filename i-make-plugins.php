<?php
/*
Plugin Name: I Make Plugins
Description: Shows off the WordPress plugins you've written
Version: 1.2.3
Author: Mark Jaquith
Plugin URI: http://txfx.net/wordpress-plugins/i-make-plugins/
Author URI: http://coveredwebservices.com/
License: GPL
*/

/*
    Copyright 2010-2012 Mark Jaquith

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
	const VERSION = '1.2.2';
	const CRON_HOOK = 'cws_imp_update_plugins';
	var $prevent_recursion = false;
	var $readme;
	var $current_faq;
	var $current_faq_answer;
	var $current_changelog_v;
	var $current_changes;
	var $current_change;
	var $did_list = false;
	var $post_type = 'page';

	function __construct() {
		self::$instance = $this;
		add_action( 'admin_init',    array( $this, 'admin_init'     )        );
		add_action( 'admin_menu',    array( $this, 'admin_menu'     )        );
		add_filter( 'the_content',   array( $this, 'plugins_list'   ), 15    );
		add_filter( 'the_content',   array( $this, 'plugin'         ),  9    );
		add_filter( 'init',          array( $this, 'init'           )        );
		add_action( 'do_meta_boxes', array( $this, 'do_meta_boxes'  ), 20, 2 );
		add_action( 'save_post',     array( $this, 'save_post'      )        );
		add_action( self::CRON_HOOK, array( $this, 'update_plugins' )        );
	}

	function init() {
		$this->post_type = apply_filters( 'i-make-plugins__post_type', $this->post_type );
		load_plugin_textdomain( 'cws-imp', '', plugin_basename( dirname( __FILE__ ) ) );
		// Add our default options
		add_option( 'cws_imp_plugin_list_template', "<ul id=\"cws-imp-plugin-list\">\n\n[implist]\n<li class=\"cws-imp-plugin\"><a class=\"cws-imp-plugin-title\" href=\"[implist_url]\">[implist_name]</a>\n<p class=\"cws-imp-plugin-description\">[implist_desc]</p>\n</li>\n[/implist]\n\n</ul>" );
		add_option( 'cws_imp_plugin_template', "[imp_full_desc]\n\n<h3>Download</h3>\nLatest version: <a href=\"[imp_zip_url]\">Download <b>[imp_name]</b> v[imp_version]</a> [zip]\n\n[if_imp_installation]\n<h3>Installation</h3>\n[imp_installation]\n[/if_imp_installation]\n\n[if_imp_faq]\n<h3>FAQ</h3>\n[imp_faq]\n[/if_imp_faq]\n\n[if_imp_changelog]\n<h3>Changelog</h3>\n[imp_changelog]\n[/if_imp_changelog]" );

		// Upgrade routines
		if ( version_compare( get_option( 'cws_imp_current_version' ), '1.1', '<' ) ) {
			foreach ( array( 'list_template', 'template' ) as $t ) {
				$t = 'cws_imp_plugin_' . $t;
				update_option( $t, str_replace( 'imp_if', 'if_imp', get_option( $t ) ) );
			}
		}
		if ( version_compare( get_option( 'cws_imp_current_version' ), '1.2.1', '<' ) ) {
			// We killed double serialization in this release, so we have to refresh the post_meta cache
			$this->update_plugins();
		}
		update_option( 'cws_imp_current_version', self::VERSION );
		add_shortcode( 'implist_template', array( $this, 'plugins_list' ) );

		// Cron jobs
		if ( !wp_next_scheduled( self::CRON_HOOK ) )
			wp_schedule_event( current_time( 'timestamp' ), 'hourly', self::CRON_HOOK );
	}

	function admin_init() {
		// Container Page Section
		add_settings_section( 'cws-imp-settings-container-page', __( 'Container page', 'cws-imp' ), '__return_false', 'cws-imp-settings' );
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
		<p>Any of the shortcodes can be turned into a conditional wrapper by adding <code>if_</code> or <code>if_not_</code>to the front of the tag. So to test <code>[implist_version]</code>, you could wrap some code in <code>[if_implist_version]</code> ... <code>[/if_implist_version]</code>.</p>
		<p>Some loop tags can be used in a self-closing form, in which case the plugin will generate the HTML for you. You only have to use the advanced loop format if you want to choose your own HTML for the loop.</p>', 'cws-imp' );
	}

	function field_container_page() {
		wp_dropdown_pages( array( 'name' => 'cws_imp_container_id', 'echo' => 1, 'show_option_none' => __('- Select -'), 'selected' => get_option( 'cws_imp_container_id' ) ) ); ?> <span class="description"><?php esc_html_e( 'Your plugin listing page. Each plugin should be a subpage of this, and each page slug should match its slug in the WordPress.org plugin repository.', 'cws-imp' ); ?></span><?php
	}

	function field_list_template() {
		_e( '<p>This controls what will be displayed on the container page. You can use the following tags to loop through the plugins:</p>
		<p><code>[implist]</code>&mdash;<code>[/implist]</code></p>
		<p>Within that loop, you can use the following tags:</p>
		<p><code>[implist_name]</code> <code>[implist_url]</code> <code>[implist_version]</code> <code>[implist_desc]</code> <code>[implist_zip_url]</code> <code>[implist_banner-772x250]</code></p>', 'cws-imp' ); ?><textarea rows="20" cols="50" class="large-text code" id="cws_imp_plugin_list_template" name="cws_imp_plugin_list_template"><?php form_option( 'cws_imp_plugin_list_template' ); ?></textarea></fieldset><?php
	}

	function field_template() {
		_e( '<p>This controls what will be displayed on each plugin page. You can use the following tags:</p>
		<p><code>[imp_name]</code> <code>[imp_url]</code> <code>[imp_zip_url]</code> <code>[imp_full_desc]</code> <code>[imp_version]</code> <code>[imp_banner-772x250]</code> <code>[imp_changelog]</code> <code>[imp_faq]</code> <code>[imp_installation]</code> <code>[imp_min_version]</code> <code>[imp_tested_version]</code> <code>[imp_slug]</code> <code>[imp_downloads]</code> <code>[imp_screenshots]</code> <code>[imp_other_notes]</code></p>
		<p>An example advanced FAQ loop format is as follows:</p>
		<p><code>[imp_faq]</code><br />&mdash;Q. <code>[imp_faq_question]</code><br />&mdash;A. <code>[imp_faq_answer]</code><br /><code>[/imp_faq]</code></p>
		<p>An example advanced Changelog loop format is as follows:</p>
		<p><code>[imp_changelog]</code><br />&mdash;<code>[imp_changelog_version]</code><br />&mdash;&mdash;<code>[imp_changelog_changes]</code><br />&mdash;&mdash;&mdash;<code>[imp_changelog_change]</code><br />&mdash;&mdash;<code>[/imp_changelog_changes]</code><br /><code>[/imp_changelog]</code></p>', 'cws-imp' ); ?>
		<textarea rows="20" cols="50" class="large-text code" id="cws_imp_plugin_template" name="cws_imp_plugin_template"><?php form_option( 'cws_imp_plugin_template' ); ?></textarea><?php
	}

	function do_meta_boxes( $page, $context ) {
		global $post;
		if ( $this->post_type === $page && 'normal' === $context && $this->is_plugin( $post ) )
			add_meta_box( 'cws-imp-slug', __( 'Plugin Slug', 'cws-imp' ), array( $this, 'meta_box' ), $page, $context, 'high' );
	}

	function meta_box() {
		global $post;
		echo '<p>' . __( 'Normally the plugin slug is determined by the slug of this page, but you can override it here. It should <em>exactly</em> match the slug used in the WordPress.org plugin repository.', 'cws-imp' ) . '</p>';
?>
	<p><label for="cws-imp-slug-field"><?php _e( 'Plugin slug:', 'cws-imp' ); ?></label> <input id="cws-imp-slug-field" name="cws_imp_slug" type="text" value="<?php echo esc_attr( get_post_meta( $post->ID, '_cws_imp_slug', true ) ); ?>" /><?php wp_nonce_field( 'cws_imp', '_cws_imp_nonce', false, true ); ?></p>
<?php
	}

	function save_post( $id ) {
		if ( isset( $_REQUEST['_cws_imp_nonce'] ) && wp_verify_nonce( $_REQUEST['_cws_imp_nonce'], 'cws_imp' ) ) {
			if ( strlen( $_REQUEST['cws_imp_slug'] ) > 0 )
				update_post_meta( $id, '_cws_imp_slug', stripslashes( $_REQUEST['cws_imp_slug'] ) );
			else
				delete_post_meta( $id, '_cws_imp_slug' );
		}
		return $id;
	}

	function get_list_page_id() {
		return get_option( 'cws_imp_container_id' );
	}

	function get_plugins() {
		$options = apply_filters( 'i-make-plugins__get_plugins', array( 'post_type' => $this->post_type, 'post_parent' => $this->get_list_page_id(), 'showposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		return new WP_Query( $options );
	}

	function get_plugin_description( $page_id ) {
		$readme = $this->get_plugin_readme( $page_id );
		if ( isset( $readme->short_description ) && !empty( $readme->short_description ) )
			return $readme->short_description;
		else
			return ' '; // Why a space? Must investigate further
	}

	function get_plugin_slug( $post ) {
		$post = get_post( $post );
		$postmeta_slug = get_post_meta( $post->ID, '_cws_imp_slug', true );
		$slug = ( $postmeta_slug ) ? $postmeta_slug : $post->post_name;
		return $slug;
	}

	function get_plugin_readme( $page_id, $force_update = false ) {
		$slug = $this->get_plugin_slug( $page_id );

		if ( !$force_update ) {
			// First, try in-memory cache
			if ( isset( $this->cache[$slug] ) )
				return $this->cache[$slug];

			// Next, try postmeta cache
			$ts = get_post_meta( $page_id, '_cws_imp_readme_timestamp', true );
			$rm = get_post_meta( $page_id, '_cws_imp_readme', true );
			// We force a dynamic update after two hours
			// Note that we have a cron job that ideally does this once an hour
			if ( $rm && $ts && $ts > time() - 7200 ) { // fresh
				$this->cache[$slug] = $rm;
				return $this->cache[$slug];
			}
		}

		// Fetch via API
		require_once( ABSPATH . '/wp-admin/includes/plugin-install.php' );
		$readme = plugins_api( 'plugin_information', array('slug' => $slug, 'fields' => array( 'short_description' => true ) ) );
		if ( is_wp_error( $readme ) )
			return false;
		$readme->banners = array( '772x250' => $this->get_banner_url( $page_id, '772x250' ) );
		$this->cache[$slug] = $readme;

		update_post_meta( $page_id, '_cws_imp_readme', $readme );
		update_post_meta( $page_id, '_cws_imp_readme_timestamp', time() );
		return $readme;
	}

	function update_plugins() {
		$plugins = $this->get_plugins();
		if( $plugins->have_posts() ) {
			foreach ( $plugins->posts as $p ) {
				$this->get_plugin_readme( $p->ID, true );
			}
		}
	}

	function get_readme_url( $slug, $tag ) {
		if ( 'trunk' == $tag )
			return 'http://plugins.svn.wordpress.org/' . $slug . '/trunk/readme.txt';
		else
			return 'http://plugins.svn.wordpress.org/' . $slug . '/tags/' . $tag . '/readme.txt';
	}

	function get_banner_url( $post, $dimension_string = '772x250' ) {
		$slug = $this->get_plugin_slug( $post );
		if ( !$slug )
			return false;
		foreach ( array( 'png', 'jpg' ) as $extension ) {
			$url = "http://plugins.svn.wordpress.org/{$slug}/assets/banner-{$dimension_string}.{$extension}";
			$result = wp_remote_head( $url );
			if ( !is_wp_error( $result ) && 200 == $result['response']['code'] )
				return $url;
		}
		return false;
	}

	function plugin_list_html() {
		global $post;
		$temp_post = $post; // Backup
		$return = do_shortcode( get_option( 'cws_imp_plugin_list_template' ) );
		$post = $temp_post; // Restore
		return $return;
	}

	function shortcode( $atts, $content, $tag ) {
		global $post;
		$this->readme = $this->get_plugin_readme( $post->ID ); // fetch it, just in case we need it.
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
				return isset( $this->readme->version ) ? $this->readme->version : '';
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
				return isset( $this->readme->download_link ) ? $this->readme->download_link : '';
				break;
			case 'imp_full_desc' :
				return isset( $this->readme->sections['description'] ) ? $this->readme->sections['description'] : '';
				break;
			case 'imp_installation' :
				return isset( $this->readme->sections['installation'] ) ? $this->readme->sections['installation'] : '';
				break;
			case 'imp_screenshots' :
				return isset( $this->readme->sections['screenshots'] ) ? $this->readme->sections['screenshots'] : '';
				break;
			case 'imp_other_notes' :
				return isset( $this->readme->sections['other_notes'] ) ? $this->readme->sections['other_notes'] : '';
				break;
			case 'imp_changelog' :
				if ( isset( $this->readme->sections['changelog'] ) ) {
					$this->changes = $this->parse_changelog( $this->readme->sections['changelog'] );
					if ( $content ) {
						$shortcodes = array( 'imp_changelog_version', 'imp_changelog_changes', 'imp_changelog_change' );
						$this->add_shortcodes( $shortcodes );
						foreach ( (array) $this->changes as $this->current_changelog_v => $this->current_changes )
							$return .= do_shortcode( $content );
						$this->remove_shortcodes( $shortcodes );
						unset( $this->current_changelog_v, $this->current_changes, $this->current_change );
						return $return;
					} else {
						return $this->output_changelog( $this->changes );
					}
				}
				break;
			case 'imp_changelog_version' :
				return $this->current_changelog_v;
				break;
			case 'imp_changelog_changes' :
				$shortcodes = array( 'imp_changelog_change' );
				foreach ( (array) $this->current_changes as $this->current_change )
					$return .= do_shortcode( $content );
				return $return;
				break;
			case 'imp_changelog_change' :
				return $this->current_change;
				break;
			case 'imp_faq' :
				if ( isset( $this->readme->sections['faq'] ) ) {
					$imp_faqs = $this->parse_faq( $this->readme->sections['faq'] );
					if ( $content ) {
						$shortcodes = array( 'imp_faq_question', 'imp_faq_answer' );
						$this->add_shortcodes( $shortcodes );
						foreach ( $imp_faqs as $this->current_faq => $this->current_faq_answer )
							$return .= do_shortcode( $content );
						$this->remove_shortcodes( $shortcodes );
						unset( $this->current_faq, $this->current_faq_answer );
						return $return;
					} else {
						return $this->output_faq( $imp_faqs );
					}
				}
				break;
			case 'imp_faq_question' :
				return $this->current_faq;
				break;
			case 'imp_faq_answer' :
				return $this->current_faq_answer;
				break;
			case 'imp_min_version' :
				return isset( $this->readme->requires ) ? $this->readme->requires : '';
				break;
			case 'imp_tested_version' :
				return isset( $this->readme->tested ) ? $this->readme->tested : '';
				break;
			case 'imp_slug' :
				return isset( $this->readme->slug ) ? $this->readme->slug : '';
				break;
			case 'imp_downloads' :
				return isset( $this->readme->downloaded ) ? $this->readme->downloaded : '';
				break;
			case 'imp_banner-772x250' :
			case 'implist_banner-772x250' :
				return isset( $this->readme->banners['772x250'] ) ? $this->readme->banners['772x250'] : '';
				break;
		endswitch;
	}

	function shortcode_conditional( $atts, $content, $tag ) {
		$test_tag = preg_replace( '#^if_#', '', $tag );
		$test_output = $this->shortcode( NULL, NULL, $test_tag );
		if ( !empty( $test_output ) )
			return do_shortcode( $content );
	}

	function shortcode_negative_conditional( $atts, $content, $tag ) {
		$test_tag = preg_replace( '#^if_not_#', '', $tag );
		$test_output = $this->shortcode( NULL, NULL, $test_tag );
		if ( empty( $test_output ) )
			return do_shortcode( $content );
	}

	function shortcode_implist( $atts, $content = NULL ) {
		global $post;
		$plugins = $this->get_plugins();
		$return = '';
		while ( $plugins->have_posts() ) : $plugins->the_post();
			if ( get_post_meta( $post->ID, '_cws_imp_retired_plugin', true ) )
				continue; // TO-DO: UI for this
			$desc = trim( $this->get_plugin_description( $post->ID ) );
			if ( !empty( $desc ) )
				$post->post_excerpt = $desc;
			$return .= do_shortcode( $content );
		endwhile;
		return $return;
	}

	function add_shortcodes( $array ) {
		foreach ( (array) $array as $shortcode ) {
			$conditional = 'if_' . $shortcode;
			$negative_conditional = 'if_not_' . $shortcode;
			add_shortcode( $shortcode, array( $this, 'shortcode' ) );
			add_shortcode( $conditional, array( $this, 'shortcode_conditional' ) );
			add_shortcode( $negative_conditional, array( $this, 'shortcode_negative_conditional' ) );
		}
	}

	function remove_shortcodes( $array ) {
		foreach ( (array) $array as $shortcode ) {
			$conditional = 'if_' . $shortcode;
			remove_shortcode( $shortcode );
			remove_shortcode( $conditional );
		}
	}

	function plugins_list( $content = '' ) {
		global $post;
		if ( ( isset( $this->prevent_recursion ) && $this->prevent_recursion ) || $post->ID != $this->get_list_page_id() ) {
			return $content;
		} else {
			$this->prevent_recursion = true;
			$shortcodes = array( 'implist', 'implist_name', 'implist_url', 'implist_version', 'implist_desc', 'implist_zip_url', 'implist_banner-772x250' );
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

	function is_plugin( $post ) {
		$post = get_post( $post );
		return apply_filters( 'i-make-plugins__is_plugin', $post && isset( $post->post_parent ) && $post->post_parent && $post->post_parent == get_option( 'cws_imp_container_id' ), $post->ID );
	}

	function plugin( $content ) {
		global $post;
		if ( get_post_meta( $post->ID, '_cws_imp_retired_plugin', true ) )
			$content = __( '<p><strong>This plugin has been marked as retired. It is recommended that you no longer use it.</strong></p>', 'cws-imp' );
		if ( $this->is_plugin( $post ) ) {
			$this->readme = $this->get_plugin_readme( $post->ID );
			if ( $this->readme ) {
				$shortcodes = array( 'imp_name', 'imp_url', 'imp_zip_url', 'imp_full_desc', 'imp_installation', 'imp_changelog', 'imp_faq', 'imp_version', 'imp_min_version', 'imp_tested_version', 'imp_slug', 'imp_downloads', 'imp_screenshots', 'imp_other_notes', 'imp_banner-772x250' );
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
