<?php

// 1. Customize ACF path
function my_acf_settings_path($path) {
    return get_stylesheet_directory() . '/plugins/advanced-custom-fields-pro/';
}
add_filter('acf/settings/path', 'my_acf_settings_path');

// 2. Customize ACF dir
function my_acf_settings_dir( $dir ) {
    return get_stylesheet_directory_uri() . '/plugins/advanced-custom-fields-pro/';
}
add_filter('acf/settings/dir', 'my_acf_settings_dir');

// 3. Hide ACF field group menu item
add_filter('acf/settings/show_admin', '__return_false');

// 4. Include ACF
include_once(__DIR__ . '/advanced-custom-fields-pro/acf.php');
