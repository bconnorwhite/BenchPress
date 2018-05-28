<?php
/* Add Menus-------- */
function register_menus(){
	register_nav_menus(array('header-menu' => __('Header Menu'),));
}
add_action('init', 'register_menus');

/* Remove Page Editor-------- */
function remove_editor_init() {
  remove_post_type_support('page', 'editor');
}
add_action('init', 'remove_editor_init');

/* Remove Admin Bar-------- */
function remove_admin_bar() {
	if(wp_get_current_user()->user_email == 'connor.bcw@gmail.com') {
	  show_admin_bar(false);
	}
}
add_action('after_setup_theme', 'remove_admin_bar');

/* Register Styles and Scripts-------- */
function register(){
	wp_register_style('style', get_stylesheet_uri());
	$query_args = array(
		'family' => 'Open+Sans:300,300i,400|Merriweather:300,400,700',
		'subset' => 'latin,latin-ext');
	wp_register_style( 'google_fonts', add_query_arg( $query_args, "//fonts.googleapis.com/css" ), array(), null );

}
add_action( 'wp_enqueue_scripts', 'register' );

/* Enque Styles and Scripts-------- */
function enqueue(){
	wp_enqueue_style('style');
	wp_enqueue_style('google_fonts');
}
add_action( 'wp_enqueue_scripts', 'enqueue' );

/* Advanced Custom Fields ---------- */
require(get_template_directory() . '/plugins/include.php');
include_once(get_template_directory() . '/inc/acf.php');
