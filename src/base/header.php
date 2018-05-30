<!DOCTYPE html>
<html id="html">
    <head>
        <?php if(is_front_page()){ ?>
        	<title><?php bloginfo('name'); ?> | <?php bloginfo('description'); ?></title>
        <?php } else { ?>
        	<title><?php wp_title(); ?> | <?php bloginfo('name'); ?></title>
        <?php } ?>
        <link rel="shortcut icon" href="<?php bloginfo('template_url'); ?>/img/favicon.ico" />
        <meta description="<?php bloginfo('description'); ?>" />
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="robots" content="index,follow">
        <meta name="viewport" content="width=device-width, height=device-height, initial-scale=1.0, maximum-scale=1.0">
        <?php wp_head(); ?>
    </head>
