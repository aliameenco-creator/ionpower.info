<?php
/**
 * One-click setup: pretty permalinks (needed for /contact-us and the other pages), the original site title
 * ("Ion Power"), the Media Library import of every theme image, then the editable pages (Home as front page,
 * Contact Us, Privacy Policy, Terms and Conditions, Cookie Policy) and the Main menu.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ionp_setup_missing() {
    $missing = array();
    if ( '' === (string) get_option( 'permalink_structure' ) ) { $missing['permalinks'] = 'turn on pretty permalinks so /contact-us and the legal pages work'; }
    if ( 'Ion Power' !== get_option( 'blogname' ) ) { $missing['title'] = 'set the site title to "Ion Power" (the original title)'; }
    if ( ionp_media_pending() ) { $missing['media'] = 'add the website images to the Media Library'; }
    if ( ionp_pages_pending() ) { $missing['pages'] = 'create the editable pages (Home, Contact Us, Privacy Policy, Terms and Conditions, Cookie Policy) and the main menu'; }
    return $missing;
}

add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    if ( isset( $_GET['ionp_setup'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        echo '<div class="notice notice-success is-dismissible"><p><strong>ION Power is set up.</strong> Every page is now under <a href="' . esc_url( admin_url( 'edit.php?post_type=page' ) ) . '">Pages</a> (Home is the front page) and the menu is under <a href="' . esc_url( admin_url( 'nav-menus.php' ) ) . '">Appearance → Menus</a>. Edit text, images and buttons in the block editor.</p></div>';
    }
    $missing = ionp_setup_missing();
    if ( ! $missing ) { return; }
    $url = wp_nonce_url( admin_url( 'admin-post.php?action=ionp_setup' ), 'ionp_setup' );
    echo '<div class="notice notice-warning"><p><strong>ION Power setup:</strong> ' . esc_html( implode( '; ', $missing ) ) . '.</p><p><a class="button button-primary" href="' . esc_url( $url ) . '">Complete ION Power setup</a></p></div>';
} );

add_action( 'admin_post_ionp_setup', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
    check_admin_referer( 'ionp_setup' );
    if ( '' === (string) get_option( 'permalink_structure' ) ) {
        update_option( 'permalink_structure', '/%postname%/' );
        flush_rewrite_rules( true );
    }
    if ( 'Ion Power' !== get_option( 'blogname' ) ) {
        update_option( 'blogname', 'Ion Power' );
        update_option( 'blogdescription', '' );
    }
    // Pages are created after the media import so their images point at the Media Library.
    if ( ionp_media_pending() ) { wp_safe_redirect( ionp_media_import_url() ); exit; }
    ionp_pages_seed();
    wp_safe_redirect( admin_url( 'edit.php?post_type=page&ionp_setup=done' ) );
    exit;
} );
