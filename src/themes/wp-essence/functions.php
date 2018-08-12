<?php
/* Remove Page Editor-------- */
function remove_editor_init() {
  remove_post_type_support('page', 'editor');
}
add_action('init', 'remove_editor_init');

/*Essence Editor-------- */
if( current_user_can('administrator') || current_user_can('editor') || current_user_can('author') || current_user_can('contributor')) {
  include_once(get_template_directory() . '/admin/php/essence-editor.php');
}

/* Register Styles and Scripts-------- */
function register_scripts(){
	wp_register_style('style', get_stylesheet_uri());
  wp_enqueue_style('style');
}
add_action( 'wp_enqueue_scripts', 'register_scripts' );

/* Register Menus ---------- */
include_once(get_template_directory() . '/inc/menus.php');

/* Advanced Custom Fields ---------- */
require(get_template_directory() . '/plugins/include.php');
include_once(get_template_directory() . '/inc/acf.php');

/* Admin Bar ---------- */
function custom_button_example($wp_admin_bar){
	$args = array(
		'id' => 'edit-mode',
		'title' => 'Edit',
		'href' => '#',
		'meta' => array(
			'class' => 'custom-button-class',
			'onclick' => 'toggleEditMode(); return false;'
		)
	);
	$wp_admin_bar->add_node($args);
}
add_action('admin_bar_menu', 'custom_button_example', 50);

function remove_toolbar_node($wp_admin_bar) {
	$wp_admin_bar->remove_node('site-name');
	$wp_admin_bar->remove_node('new-content');
	$wp_admin_bar->remove_node('comments');
	$wp_admin_bar->remove_node('search');
	$wp_admin_bar->remove_node('edit');
}
add_action('admin_bar_menu', 'remove_toolbar_node', 999);

//Remove dashboard widgets
function essence_remove_dashboard_widgets() {
	remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
}
add_action( 'wp_dashboard_setup', 'essence_remove_dashboard_widgets' );


//Block dashboard
if(!current_user_can('administrator')) {
  define( 'DISALLOW_FILE_EDIT', true ); //Just in case...
  add_action('admin_init', 'essence_dashboard_redirect');
}

function essence_dashboard_redirect() {
  global $pagenow;
  if ( 'profile.php' !== $pagenow ) {
    wp_redirect( get_home_url() );
  }
}
