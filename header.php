<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0F2A2E"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="ionp-skip" href="#main">Skip to content</a>
<div class="ionp-progress" aria-hidden="true"></div>
<header class="ionp-header" data-header>
    <div class="ionp-bar">
        <a class="ionp-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="ION POWER home"><?php echo ionp_bolt(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?><span>ION POWER</span></a>
        <nav class="ionp-nav" id="ionp-nav" aria-label="Main navigation">
            <?php
            if ( has_nav_menu( 'primary' ) ) {
                wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'menu_class' => 'ionp-menu', 'depth' => 1, 'fallback_cb' => false ) );
            } else {
                echo ionp_fallback_menu(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the function.
            }
            ?>
        </nav>
        <button class="ionp-burger" type="button" aria-expanded="false" aria-controls="ionp-nav" aria-label="Open menu"><span></span><span></span><span></span></button>
    </div>
</header>
<main id="main">
