<?php
/**
 * Media Library: one-click import of every image the theme shows (descriptive file names and alt text from
 * parts/media.json). Pages created afterwards use the Media Library copies; until the import runs, the bundled
 * copies are used.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ionp_media_manifest() {
    static $manifest = null;
    if ( null === $manifest ) {
        $file = get_theme_file_path( 'parts/media.json' );
        $manifest = is_readable( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
    }
    return $manifest;
}

/** Imported images: manifest key => attachment ID (only attachments that still exist). */
function ionp_media_ids() {
    static $ids = null;
    if ( null !== $ids ) { return $ids; }
    $ids = array_filter( array_map( 'intval', (array) get_option( 'ionp_media_ids', array() ) ) );
    if ( $ids ) {
        _prime_post_caches( array_values( $ids ), false, true );
        $ids = array_filter( $ids, function ( $id ) { return 'attachment' === get_post_type( $id ); } );
    }
    return $ids;
}

function ionp_media_pending() {
    $ids = ionp_media_ids();
    $failed = (array) get_option( 'ionp_media_failed', array() );
    return array_values( array_filter( ionp_media_manifest(), function ( $m ) use ( $ids, $failed ) {
        return empty( $ids[ $m['key'] ] ) && ! isset( $failed[ $m['key'] ] );
    } ) );
}

/** Raw (not HTML-escaped) URL: used for redirects; escape with esc_url() when printing. */
function ionp_media_import_url() {
    return add_query_arg( array( 'action' => 'ionp_media_import', '_wpnonce' => wp_create_nonce( 'ionp_media_import' ) ), admin_url( 'admin-post.php' ) );
}

/** Imports a few images per request (shared hosting time limits) and continues until all are in the library. */
add_action( 'admin_post_ionp_media_import', function () {
    if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'upload_files' ) ) { wp_die( 'Not allowed.' ); }
    check_admin_referer( 'ionp_media_import' );
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $ids = (array) get_option( 'ionp_media_ids', array() );
    $failed = (array) get_option( 'ionp_media_failed', array() );
    $start = microtime( true );
    $imported = 0;
    foreach ( ionp_media_pending() as $m ) {
        $key = $m['key'];
        // Re-use an attachment from an earlier run (safe to re-run; never duplicates).
        $existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_ionp_media_key', 'meta_value' => $key, 'fields' => 'ids', 'numberposts' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
        if ( $existing ) { $ids[ $key ] = (int) $existing[0]; continue; }
        if ( $imported >= 4 || microtime( true ) - $start > 18 ) { break; }
        $source = get_theme_file_path( $m['file'] );
        $ext = strtolower( pathinfo( $m['file'], PATHINFO_EXTENSION ) );
        $tmp = wp_tempnam( $m['name'] . '.' . $ext );
        if ( ! is_readable( $source ) || ! $tmp || ! copy( $source, $tmp ) ) { $failed[ $key ] = 'Bundled file missing: ' . $m['file']; continue; }
        $id = media_handle_sideload( array( 'name' => $m['name'] . '.' . $ext, 'tmp_name' => $tmp ), 0, $m['title'] );
        if ( is_wp_error( $id ) ) {
            wp_delete_file( $tmp );
            $failed[ $key ] = $id->get_error_message();
            continue;
        }
        update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $m['alt'] ) );
        update_post_meta( $id, '_ionp_media_key', $key );
        $ids[ $key ] = (int) $id;
        ++$imported;
    }
    update_option( 'ionp_media_ids', $ids, false );
    update_option( 'ionp_media_failed', $failed, false );
    $left = count( array_filter( ionp_media_manifest(), function ( $m ) use ( $ids, $failed ) { return empty( $ids[ $m['key'] ] ) && ! isset( $failed[ $m['key'] ] ); } ) );
    if ( $left ) {
        // Small progress page that continues automatically (and works as a plain link without JavaScript).
        $next = ionp_media_import_url();
        $total = count( ionp_media_manifest() );
        echo '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . esc_url( $next ) . '"><title>Adding images…</title><body style="font:16px system-ui;padding:40px;background:#F5FBFA;color:#0F2A2E"><p>Adding ION Power images to the Media Library: ' . (int) ( $total - $left ) . ' of ' . (int) $total . '…</p><p><a style="color:#067F77" href="' . esc_url( $next ) . '">Continue</a></p>';
        exit;
    }
    // Images are in the library: continue the setup (creates the editable pages).
    wp_safe_redirect( add_query_arg( array( 'action' => 'ionp_setup', '_wpnonce' => wp_create_nonce( 'ionp_setup' ), 'ionp_media' => 'done' ), admin_url( 'admin-post.php' ) ) );
    exit;
} );

add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $failed = (array) get_option( 'ionp_media_failed', array() );
    if ( isset( $_GET['ionp_media'] ) && ! $failed ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        echo '<div class="notice notice-success is-dismissible"><p><strong>ION Power:</strong> all ' . count( ionp_media_manifest() ) . ' images are in the Media Library with descriptive file names and alt text. Edit any alt text here and the website uses it.</p></div>';
    }
    if ( $failed ) {
        $retry = wp_nonce_url( admin_url( 'admin-post.php?action=ionp_media_retry' ), 'ionp_media_retry' );
        echo '<div class="notice notice-error"><p><strong>ION Power:</strong> ' . count( $failed ) . ' image(s) could not be added to the Media Library (the website keeps using the theme copies). First problem: ' . esc_html( (string) reset( $failed ) ) . '</p><p><a class="button" href="' . esc_url( $retry ) . '">Try again</a></p></div>';
    }
} );

add_action( 'admin_post_ionp_media_retry', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
    check_admin_referer( 'ionp_media_retry' );
    delete_option( 'ionp_media_failed' );
    wp_safe_redirect( ionp_media_import_url() );
    exit;
} );
