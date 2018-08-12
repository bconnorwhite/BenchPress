<?php

/* Register Styles and Scripts-------- */
function register_essence_editor_scripts(){
    //Essence Editor Script
    wp_register_script('essence-editor-script', get_template_directory_uri() . '/admin/js/essence-editor.js', array('jquery'), NULL, false);
    wp_localize_script('essence-editor-script', 'wpApiSettings', array('root' => esc_url_raw(rest_url()), 'nonce' => wp_create_nonce('wp_rest')));
    wp_localize_script('essence-editor-script', 'postSettings', array('id' => get_the_ID()));
    wp_enqueue_script('essence-editor-script');
    //Essence Editor Styles
    wp_register_style('essence-editor-style', get_template_directory_uri() . "/css/essence-editor.css");
    wp_enqueue_style('essence-editor-style');
    //Media Picker
    wp_enqueue_media();
    //Link Picker
    wp_enqueue_script('wplink');
    wp_enqueue_style('editor-buttons');
}
add_action( 'wp_enqueue_scripts', 'register_essence_editor_scripts' );

function display_wplink_stuff() {
	require_once ABSPATH . "wp-includes/class-wp-editor.php";
  _WP_Editors::wp_link_dialog(); ?>
	<script type="text/javascript">
		var ajaxurl = "<?php echo admin_url( 'admin-ajax.php'); ?>";
	</script> <?php
}
add_action('wp_footer', 'display_wplink_stuff');

//Allow for updating meta from REST API
add_action("rest_insert_page", function (\WP_Post $post, $request, $creating) {
    $metas = $request->get_param("meta");
    if (is_array($metas)) {
        foreach ($metas as $name => $value) {
            update_post_meta($post->ID, $name, $value);
        }
    }
}, 10, 3);
