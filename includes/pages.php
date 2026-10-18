<?php
/**
 * Editable WordPress pages (parts/pages.json + parts/pages/<slug>.html) and the main menu.
 * The setup button creates them once, at their original addresses, after the images and videos are in the Media
 * Library. Existing published pages are never overwritten; a page an editor deletes is not re-created.
 * WordPress's untouched sample "Privacy Policy" draft is replaced by the site's own Privacy Policy page.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ionp_pages_data() {
    static $pages = null;
    if ( null === $pages ) {
        $file = get_theme_file_path( 'parts/pages.json' );
        $pages = is_readable( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
    }
    return $pages;
}

/** Page with this slug in any status (including the trash), or 0. */
function ionp_page_by_slug( $slug ) {
    $ids = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ), 'numberposts' => 1, 'fields' => 'ids' ) );
    if ( ! $ids ) { $ids = get_posts( array( 'post_type' => 'page', 'name' => $slug . '__trashed', 'post_status' => 'trash', 'numberposts' => 1, 'fields' => 'ids' ) ); }
    return $ids ? (int) $ids[0] : 0;
}

/** WordPress's own sample privacy page, still a draft with the "Suggested text" template. */
function ionp_sample_privacy_page() {
    $id = (int) get_option( 'wp_page_for_privacy_policy' );
    if ( ! $id || 'draft' !== get_post_status( $id ) ) { return 0; }
    return str_contains( (string) get_post_field( 'post_content', $id ), 'Suggested text' ) ? $id : 0;
}

function ionp_pages_pending() {
    $pending = array();
    foreach ( ionp_pages_data() as $page ) {
        if ( get_option( 'ionp_page_' . $page['slug'] ) ) { continue; } // created before (maybe deleted by an editor since)
        $existing = ionp_page_by_slug( $page['slug'] );
        if ( $existing && ! ( ! empty( $page['privacy'] ) && $existing === ionp_sample_privacy_page() ) ) { continue; }
        $pending[] = $page;
    }
    if ( ! get_option( 'ionp_menu_created' ) ) { $pending[] = array( 'slug' => '_menu' ); }
    return $pending;
}

function ionp_pages_seed() {
    if ( ! current_user_can( 'edit_pages' ) ) { return 0; }
    $created = 0;
    foreach ( ionp_pages_pending() as $page ) {
        if ( '_menu' === $page['slug'] ) { continue; }
        $postarr = array(
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => $page['title'],
            'post_name'    => $page['slug'],
            'post_content' => ionp_page_markup( $page['slug'] ),
        );
        $sample = ! empty( $page['privacy'] ) ? ionp_sample_privacy_page() : 0;
        if ( $sample ) { $postarr['ID'] = $sample; }
        $id = $sample ? wp_update_post( wp_slash( $postarr ), true ) : wp_insert_post( wp_slash( $postarr ), true );
        if ( is_wp_error( $id ) ) { continue; }
        update_option( 'ionp_page_' . $page['slug'], (int) $id, false );
        if ( ! empty( $page['seo_title'] ) ) { update_post_meta( $id, '_yoast_wpseo_title', $page['seo_title'] ); }
        if ( ! empty( $page['front'] ) ) {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', (int) $id );
        }
        if ( ! empty( $page['privacy'] ) ) { update_option( 'wp_page_for_privacy_policy', (int) $id ); }
        ionp_mark_seeded( $id );
        ++$created;
    }
    ionp_menu_seed();
    ionp_trash_samples();
    return $created;
}

/** Content version of the theme's page designs (theme version that last changed them). */
function ionp_content_version() {
    $file = get_theme_file_path( 'parts/content-version.txt' );
    return is_readable( $file ) ? trim( (string) file_get_contents( $file ) ) : '0';
}

/** Remember which design version a page holds and a fingerprint of it, to tell later whether anyone edited it. */
function ionp_mark_seeded( $id ) {
    update_post_meta( $id, '_ionp_seed_version', ionp_content_version() );
    update_post_meta( $id, '_ionp_seed_hash', md5( (string) get_post_field( 'post_content', $id ) ) );
}

/**
 * Pages created by setup whose design is older than the theme's: [slug => [id, title, state]], state "unchanged"
 * (nobody edited it since setup) or "edited".
 */
function ionp_pages_outdated() {
    $out = array();
    foreach ( ionp_pages_data() as $page ) {
        $id = (int) get_option( 'ionp_page_' . $page['slug'] );
        if ( ! $id || 'publish' !== get_post_status( $id ) || get_post_meta( $id, '_ionp_seed_version', true ) === ionp_content_version() ) { continue; }
        $hash = get_post_meta( $id, '_ionp_seed_hash', true );
        $unchanged = $hash && md5( (string) get_post_field( 'post_content', $id ) ) === $hash;
        $out[ $page['slug'] ] = array( $id, $page['title'], $unchanged ? 'unchanged' : 'edited' );
    }
    return $out;
}

/** Replace a page's content with the theme's current design (WordPress keeps the previous version as a revision). */
function ionp_page_refresh( $slug ) {
    $id = (int) get_option( 'ionp_page_' . $slug );
    if ( ! $id || ! current_user_can( 'edit_page', $id ) ) { return false; }
    $result = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => ionp_page_markup( $slug ) ) ), true );
    if ( is_wp_error( $result ) ) { return false; }
    ionp_mark_seeded( $id );
    return true;
}

/** Appearance → ION Power Pages: update pages to a newer theme design, page by page. */
add_action( 'admin_menu', function () {
    add_theme_page( 'ION Power Pages', 'ION Power Pages', 'edit_pages', 'ionp-pages', 'ionp_pages_screen' );
} );

function ionp_pages_screen() {
    if ( ! current_user_can( 'edit_pages' ) ) { return; }
    echo '<div class="wrap"><h1>ION Power Pages</h1>';
    if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        echo '<div class="notice notice-success"><p>' . (int) $_GET['updated'] . ' page(s) updated to the new design. The previous version of each is saved under Revisions in the page editor.</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }
    if ( ionp_media_pending() ) {
        $url = wp_nonce_url( admin_url( 'admin-post.php?action=ionp_setup' ), 'ionp_setup' );
        echo '<p>This theme version has new images or videos. Add them to the Media Library first.</p><p><a class="button button-primary" href="' . esc_url( $url ) . '">Complete ION Power setup</a></p></div>';
        return;
    }
    $outdated = ionp_pages_outdated();
    if ( ! $outdated ) { echo '<p>All pages created by the theme are up to date with version ' . esc_html( ionp_content_version() ) . '.</p></div>'; return; }
    echo '<p>The theme has an improved design for these pages. Pages nobody has edited are ticked. Updating a page you edited replaces your changes (you can restore them from the page\'s Revisions).</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ionp_pages_refresh">';
    wp_nonce_field( 'ionp_pages_refresh' );
    echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th style="width:40px"></th><th>Page</th><th>Status</th></tr></thead><tbody>';
    foreach ( $outdated as $slug => $row ) {
        echo '<tr><td><input type="checkbox" name="pages[]" value="' . esc_attr( $slug ) . '"' . checked( 'unchanged' === $row[2], true, false ) . '></td><td><a href="' . esc_url( get_edit_post_link( $row[0] ) ) . '">' . esc_html( $row[1] ) . '</a></td><td>' . ( 'unchanged' === $row[2] ? 'Not edited since setup' : '<strong>Edited</strong> — your changes would be replaced' ) . '</td></tr>';
    }
    echo '</tbody></table><p><button class="button button-primary" type="submit">Update selected pages</button></p></form></div>';
}

add_action( 'admin_post_ionp_pages_refresh', function () {
    if ( ! current_user_can( 'edit_pages' ) ) { wp_die( 'Not allowed.' ); }
    check_admin_referer( 'ionp_pages_refresh' );
    $slugs = array_map( 'sanitize_key', (array) wp_unslash( $_POST['pages'] ?? array() ) );
    $outdated = ionp_pages_outdated();
    $done = 0;
    foreach ( $slugs as $slug ) {
        if ( isset( $outdated[ $slug ] ) && ionp_page_refresh( $slug ) ) { ++$done; }
    }
    wp_safe_redirect( admin_url( 'themes.php?page=ionp-pages&updated=' . $done ) );
    exit;
} );

add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'edit_pages' ) || ( isset( $_GET['page'] ) && 'ionp-pages' === $_GET['page'] ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( ionp_pages_pending() || ! ionp_pages_outdated() ) { return; }
    echo '<div class="notice notice-info"><p><strong>ION Power:</strong> an updated design is available for your pages. <a class="button" href="' . esc_url( admin_url( 'themes.php?page=ionp-pages' ) ) . '">Review and update pages</a></p></div>';
} );

/** WordPress's untouched "Hello world!" post and "Sample Page" would show on News and in search: move them to the trash. */
function ionp_trash_samples() {
    foreach ( array( array( 'post', 'hello-world', 'Welcome to WordPress' ), array( 'page', 'sample-page', 'This is an example page' ) ) as $sample ) {
        $posts = get_posts( array( 'post_type' => $sample[0], 'name' => $sample[1], 'post_status' => 'publish', 'numberposts' => 1 ) );
        if ( $posts && str_contains( $posts[0]->post_content, $sample[2] ) ) { wp_trash_post( $posts[0]->ID ); }
    }
}

/** Main menu (Appearance → Menus): the original one-page sections and Contact Us. */
function ionp_menu_seed() {
    if ( get_option( 'ionp_menu_created' ) ) { return; }
    $locations = get_theme_mod( 'nav_menu_locations', array() );
    if ( ! empty( $locations['primary'] ) && wp_get_nav_menu_object( $locations['primary'] ) ) { update_option( 'ionp_menu_created', 1, false ); return; }
    $menu = wp_get_nav_menu_object( 'Main menu' );
    $menu_id = $menu ? (int) $menu->term_id : wp_create_nav_menu( 'Main menu' );
    if ( is_wp_error( $menu_id ) ) { return; }
    foreach ( ionp_menu_items() as $item ) {
        $args = array( 'menu-item-title' => $item[1], 'menu-item-status' => 'publish', 'menu-item-classes' => $item[2] ?? '' );
        $page = str_starts_with( $item[0], '/#' ) ? null : get_page_by_path( ltrim( $item[0], '/' ) );
        if ( $page && 'publish' === $page->post_status ) {
            $args += array( 'menu-item-object' => 'page', 'menu-item-object-id' => $page->ID, 'menu-item-type' => 'post_type' );
        } else {
            // Section links stay root-relative so they keep working when the real domain replaces the temporary address.
            $args += array( 'menu-item-type' => 'custom', 'menu-item-url' => $item[0] );
        }
        wp_update_nav_menu_item( $menu_id, 0, $args );
    }
    $locations['primary'] = $menu_id;
    set_theme_mod( 'nav_menu_locations', $locations );
    update_option( 'ionp_menu_created', 1, false );
}

/** Root-relative custom menu links (/#about) work on any domain and from every page. */
add_filter( 'nav_menu_link_attributes', function ( $atts ) {
    if ( isset( $atts['href'] ) && str_starts_with( $atts['href'], '/' ) && ! str_starts_with( $atts['href'], '//' ) ) { $atts['href'] = ionp_url( $atts['href'] ); }
    return $atts;
} );
