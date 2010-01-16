<?php 
/* 
Plugin Name: I Make Plugins 
Description: Shows off the WordPress plugins you've written 
Version: 1.0
Author: Mark Jaquith 
Plugin URI: http://txfx.net/wordpress-plugins/i-make-plugins/
Author URI: http://coveredwebservices.com/
*/ 

/* 
    Copyright 2009 Mark Jaquith (email: mark.gpl@txfx.net) 

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

function cws_imp_init() {
	load_plugin_textdomain( 'cws-imp', '', plugin_basename( dirname( __FILE__ ) ) );
}

function cws_imp_get_plugin_list_page_id() {
	return get_option( 'cws_imp_container_id' );
}

function cws_imp_get_plugins() {
	$parent_id = cws_imp_get_plugin_list_page_id();
	return new WP_Query( array( 'post_type' => 'page', 'post_parent' => $parent_id, 'showposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
}

function cws_imp_get_plugin_description( $page_id ) {
	$readme = cws_imp_get_plugin_readme( $page_id );
	if ( $readme )
		return $readme->short_description;
	else
		return ' '; // Why a space? Must investigate further
}

function cws_imp_get_plugin_readme( $page_id ) {
	$page = get_page( $page_id );
	$slug = $page->post_name;

	global $cws_imp_readme_cache;

	// First, try in-memory cache
	if ( isset( $cws_imp_readme_cache[$slug] ) )
		return $cws_imp_readme_cache[$slug];

	// Next, try postmeta cache
	if ( $ts = get_post_meta( $page_id, '_cws_imp_readme_timestamp', true ) && $ts > time() - 3600 && $rm = get_post_meta( $page_id, '_cws_imp_readme', true ) ) { // fresh
		$cws_imp_readme_cache[$slug] = unserialize($rm);
		return $rm;
	}

	// Fetch via API
	require_once( ABSPATH . '/wp-admin/includes/plugin-install.php' );	
	$readme = plugins_api( 'plugin_information', array('slug' => $slug, 'fields' => array( 'short_description' => true ) ) );
	if ( is_wp_error( $readme ) )
		return false;
	$cws_imp_readme_cache[$slug] = $readme;
	update_post_meta( $page_id, '_cws_imp_readme', serialize( $readme ) );
	update_post_meta( $page_id, '_cws_imp_readme_timestamp', time() );
	return $readme;
}

function cws_imp_get_readme_url( $slug, $tag ) {
	if ( 'trunk' == $tag )
		return 'http://svn.wp-plugins.org/' . $slug . '/trunk/readme.txt';
	else
		return 'http://svn.wp-plugins.org/' . $slug . '/tags/' . $tag . '/readme.txt';
}

function cws_imp_plugin_list_html() {
	global $post;
	$temp_post = $post; // Backup
	$return = do_shortcode( get_option( 'cws_imp_plugin_list_template' ) );
	$post = $temp_post; // Restore
	return $return;
}

/* [implist] shortcodes */

function cws_imp_shortcode_implist( $atts, $content = NULL ) {
	global $post;
	$plugins = cws_imp_get_plugins();
	$return = '';
	while ( $plugins->have_posts() ) : $plugins->the_post();
		if ( get_post_meta( $post->ID, '_cws_imp_retired_plugin', true ) )
			continue; // TO-DO: UI for this
		$post->post_excerpt = trim( cws_imp_get_plugin_description( $post->ID ) );
		if ( empty( $post->post_excerpt ) )
			$post->post_excerpt = __( 'No description', 'cws-imp' );
		$return .= do_shortcode( $content );
	endwhile;
	return $return;
}

function cws_imp_shortcode_implist_name( $atts ) {
	return get_the_title();
}

function cws_imp_shortcode_implist_url( $atts ) {
	return get_permalink();
}

function cws_imp_shortcode_implist_desc( $atts ) {
	global $post;
	return $post->post_excerpt;
}

/* [imp_*] shortcodes */

function cws_imp_shortcode_imp_name( $atts ) {
	return get_the_title();
}

function cws_imp_shortcode_imp_url( $atts ) {
	return get_permalink();
}

function cws_imp_shortcode_imp_zip_url( $atts ) {
	global $imp_readme;
	return $imp_readme->download_link;
}

function cws_imp_shortcode_imp_full_desc( $atts ) {
	global $imp_readme;
	if ( $imp_readme->sections['description'] )
		return $imp_readme->sections['description'];
}

function cws_imp_shortcode_imp_if_installation( $atts, $content = NULL ) {
	global $imp_readme;
	if ( $imp_readme->sections['installation'] )
		return do_shortcode( $content );
}

function cws_imp_shortcode_imp_installation( $atts ) {
	global $imp_readme;
	return $imp_readme->sections['installation'];
}

function cws_imp_shortcode_imp_if_changelog( $atts, $content = NULL ) {
	global $imp_readme;
	if ( $imp_readme->sections['changelog'] )
		return do_shortcode( $content );
}

function cws_imp_shortcode_imp_changelog( $atts, $content = NULL ) {
	global $imp_readme;
	if ( $imp_readme->sections['changelog'] )
		return cws_imp_filter_changelog( $imp_readme->sections['changelog'] );
}

function cws_imp_shortcode_imp_if_faq( $atts, $content = NULL ) {
	global $imp_readme;
	if ( $imp_readme->sections['faq'] )
		return do_shortcode( $content );
}

function cws_imp_shortcode_imp_faq( $atts, $content = NULL ) {
	global $imp_readme;
	if ( $imp_readme->sections['faq'] )
		return cws_imp_filter_faq( $imp_readme->sections['faq'] );
}

function cws_imp_shortcode_imp_version( $atts ) {
	global $imp_readme;
	return $imp_readme->version;
}



function cws_imp_add_shortcodes( $array ) {
	foreach ( (array) $array as $shortcode ) {
		$function = 'cws_imp_shortcode_' . $shortcode;
		add_shortcode( $shortcode, $function );
	}
}

function cws_imp_remove_shortcodes( $array ) {
	foreach ( (array) $array as $shortcode )
		remove_shortcode( $shortcode );
}




function cws_imp_plugins_list( $content ) {
	global $post, $cws_imp_prevent_recursion;
	if ( ( isset( $cws_imp_prevent_recursion ) && $cws_imp_prevent_recursion ) || $post->ID != cws_imp_get_plugin_list_page_id() ) {
		return $content;
	} else {
		$cws_imp_prevent_recursion = true;
		$shortcodes = array( 'implist', 'implist_name', 'implist_url', 'implist_desc' );
		cws_imp_add_shortcodes( $shortcodes );
		$content = cws_imp_plugin_list_html() . $content;
		cws_imp_remove_shortcodes( $shortcodes );
		
		$cws_imp_prevent_recursion = false;
		return $content;
	}
}

function cws_imp_filter_faq( $faq ) {
	$faq = explode( "\n\n", $faq );
	$return = '';
	$i = -1;
	foreach ( $faq as $f ) {
		$i++;
		if ( $i % 2 )
			$return .= '<strong>A.</strong> '. $f . "\n\n";
		else
			$return .= '<strong>Q. ' . $f . '</strong>' . "\n";
	}
	return $return;
}

function cws_imp_filter_changelog( $changelog ) {
	$array = preg_split( "#</ul>#ims", $changelog );
	$return = '';
	$changes = array();
	foreach ( (array) $array as $a ) {
		$change = preg_split( '#<ul>#ims', $a );
		if ( trim( $change[0] ) )
			$changes[trim( $change[0] )] = '<ul>' . trim( $change[1] ) . '</ul>';
	}
	foreach ( (array) $changes as $v => $cs ) {
		$return .= "<strong>$v</strong>\n$cs\n\n";
	}
	return $return;
}

function cws_imp_plugin( $content ) {
	global $post, $imp_readme;
	if ( get_post_meta( $post->ID, '_cws_imp_retired_plugin', true ) )
		$content = __( '<p><strong>This plugin has been marked as retired. It is recommended that you no longer use it.</strong></p>', 'cws-imp' );
	if ( $post->post_parent && $post->post_parent == get_option( 'cws_imp_container_id' ) ) {
		$imp_readme = cws_imp_get_plugin_readme( $post->ID );
		if ( $imp_readme ) {
			$shortcodes = array( 'imp_name', 'imp_url', 'imp_zip_url', 'imp_full_desc', 'imp_if_installation', 'imp_installation', 'imp_if_changelog', 'imp_changelog', 'imp_if_faq', 'imp_faq', 'imp_version' );
			cws_imp_add_shortcodes( $shortcodes );
			$content = '';
			$content .= do_shortcode( get_option( 'cws_imp_plugin_template' ) );
			cws_imp_remove_shortcodes( $shortcodes );
		}
		$content = apply_filters( 'cws_imp_plugin_body', $content );
	}
	return $content;
}

function cws_imp_admin_menu() {
	$hook = add_options_page( __( 'I Make Plugins', 'cws-imp' ), __( 'I Make Plugins', 'cws-imp' ), 'manage_options', 'cws-imp', 'cws_imp_options_page' );
	add_action( 'load-' . $hook, 'cws_imp_options_save' );
}

function cws_imp_options_save() {
	if ( !isset( $_POST['cws-imp-form'] ) )
		return;
	check_admin_referer( 'cws-imp-update' );
	foreach ( array( 'container_id', 'plugin_list_template', 'plugin_template' ) as $setting ) {
		$setting = 'cws_imp_' . $setting;
		update_option( $setting, stripslashes( $_POST[$setting] ) );
	}
	wp_redirect( admin_url( 'options-general.php?page=cws-imp&updated=true' ) );
	exit();
}

function cws_imp_options_page() {
?>
<style>
#cws-imp-donate {
	position: absolute;
	width: 250px;
	right: 10px;
	top: 30px;
	border: 1px solid #aaa;
	padding: 0 10px;
	background: #6db9f2;
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
	color: #333;
}
#cws-imp-donate a:hover {
	color: #000;
}

</style>
<div style="position: relative">
<div id="cws-imp-donate">
<p><img src="http://www.gravatar.com/avatar/5f40ed513eae85b532e190c012785df7?s=64" height="64" width="64" /> Hi there! If you enjoy this plugin, consider showing your appreciation by making a small donation to its author!</p>
<p style="text-align: center"><a href="http://txfx.net/wordpress-plugins/donate" target="_new">Click here to donate using PayPal</a></p>
</div>
</div>
<div class="wrap">
<?php screen_icon(); ?>
<h2><?php esc_html_e( 'I Make Plugins Settings', 'cws-imp' ); ?></h2>
<form method="post">
<?php wp_nonce_field( 'cws-imp-update' ); ?>
<input type="hidden" name="cws-imp-form" value="1" />
<h3><?php esc_html_e( 'Container page', 'cws-imp' ); ?></h3>
<table class="form-table">
	<tr valign="top">
	<th scope="row"><label for="cws_imp_container_id"> <?php esc_html_e( 'Plugin container page', 'cws-imp' ); ?></label></th>
	<td><?php wp_dropdown_pages( array( 'name' => 'cws_imp_container_id', 'echo' => 1, 'show_option_none' => __('- Select -'), 'selected' => get_option( 'cws_imp_container_id' ) ) ); ?></td> 
	</tr>
</table>
<h3><?php esc_html_e( 'Templates', 'cws-imp' ); ?></h3>
<table class="form-table">
	<tr valign="top">
	<th scope="row"><?php esc_html_e( 'Plugin list template', 'cws-imp' ); ?></th>
	<td><fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'Plugin list template', 'cws-imp' ); ?></span></legend>
	<?php _e( '<p>This controls what will be displayed on the container page. You can use the following tags to loop through the plugins:</p>
	<p><code>[implist]</code>&mdash;<code>[/implist]</code></p>
	<p>Within that loop, you can use the following tags:</p>
	<p><code>[implist_name]</code> <code>[implist_url]</code> <code>[implist_desc]</code></p>', 'cws-imp' ); ?><textarea rows="10" cols="50" class="large-text code" id="cws_imp_plugin_list_template" name="cws_imp_plugin_list_template"><?php form_option( 'cws_imp_plugin_list_template' ); ?></textarea></fieldset></td>
	</tr>

	<tr valign="top">
	<th scope="row"><?php esc_html_e( 'Plugin template', 'cws-imp' ); ?></th>
	<td><fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'Plugin template', 'cws-imp' ); ?></span></legend>
	<?php _e( '<p>This controls what will be displayed on each plugin page. You can use the following tags:</p>
	<p><code>[imp_name]</code> <code>[imp_url]</code> <code>[imp_zip_url]</code> <code>[imp_full_desc]</code> <code>[imp_version]</code> <code>[imp_changelog]</code> <code>[imp_faq]</code> <code>[imp_installation]</code></p>
	<p>The following conditional wrappers can be used to hide sections that don\'t exist:</p>
	<p><code>[imp_if_installation]</code>&mdash;<code>[/imp_if_installation]</code> <code>[imp_if_changelog]</code>&mdash;<code>[/imp_if_changelog]</code> <code>[imp_if_faq]</code>&mdash;<code>[/imp_if_faq]</code></p>', 'cws-imp' ); ?>
	<textarea rows="10" cols="50" class="large-text code" id="cws_imp_plugin_template" name="cws_imp_plugin_template"><?php form_option( 'cws_imp_plugin_template' ); ?></textarea></td>
	</tr>
</table>
<p class="submit"><input type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes' ); ?>" /></p>
</form>
</div>
<?php
}

add_action( 'admin_menu', 'cws_imp_admin_menu' );
add_filter( 'the_content', 'cws_imp_plugins_list', 15 );
add_filter( 'the_content', 'cws_imp_plugin', 9 );
add_filter( 'init', 'cws_imp_init' );

// Add our default options
add_option( 'cws_imp_plugin_list_template', "<ul id=\"cws-imp-plugin-list\">\n\n[implist]\n<li class=\"cws-imp-plugin\"><a class=\"cws-imp-plugin-title\" href=\"[implist_url]\">[implist_name]</a>\n<p class=\"cws-imp-plugin-description\">[implist_desc]</p>\n</li>\n[/implist]\n\n</ul>" );
add_option( 'cws_imp_plugin_template', "[imp_full_desc]\n\n<h3>Download</h3>\nLatest version: <a href=\"[imp_zip_url]\">Download <b>[imp_name]</b> v[imp_version]</a> [zip]\n\n[imp_if_installation]\n<h3>Installation</h3>\n[imp_installation]\n[/imp_if_installation]\n\n[imp_if_faq]\n<h3>FAQ</h3>\n[imp_faq]\n[/imp_if_faq]\n\n[imp_if_changelog]\n<h3>Changelog</h3>\n[imp_changelog]\n[/imp_if_changelog]" );
