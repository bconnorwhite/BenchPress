<?php

function essence_child_init() {
  /* Register Menus ---------- */
  include_once(get_stylesheet_directory() . '/inc/menus.php');

  /* Advanced Custom Fields ---------- */
  include_once(get_stylesheet_directory() . '/inc/acf.php');
}

add_action( 'after_setup_theme', 'essence_child_init');
