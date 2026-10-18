<?php
/**
 * Page content, menus and original URLs for the ION Power theme.
 *
 * Every page has block markup in parts/pages/<slug>.html. The setup button turns it into a real, editable WordPress
 * page (includes/pages.php); until that page exists the theme renders the same markup at the same URL.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Theme pages besides the homepage. The old Strikingly cookie page (/pages/cookie-policy) redirects to /cookie-policy. */
function ionp_routes() {
    return array( 'contact-us', 'privacy-policy', 'terms-and-conditions', 'cookie-policy' );
}

/** Root-relative path ("/contact-us", "/#about") as a full URL in the site's trailing-slash form. */
function ionp_url( $path ) {
    if ( str_starts_with( $path, '/#' ) ) { return home_url( '/' ) . substr( $path, 1 ); }
    return '/' === $path ? home_url( '/' ) : home_url( user_trailingslashit( $path ) );
}

/** Media Library URL for an imported theme image key, or ''. */
function ionp_media_url( $key ) {
    $ids = ionp_media_ids();
    return isset( $ids[ $key ] ) ? (string) wp_get_attachment_url( $ids[ $key ] ) : '';
}

/** Image blocks get their Media Library attachment ID; /assets/img/<key>.webp points at the library copy or the theme copy. */
function ionp_resolve_blocks( $markup ) {
    $ids = ionp_media_ids();
    $markup = preg_replace_callback( '~<!-- wp:image (\{[^}]*\}) -->(.*?)<!-- /wp:image -->~s', function ( $m ) use ( $ids ) {
        if ( ! preg_match( '~<img src="/assets/img/([a-z0-9-]+)\.webp"~', $m[2], $k ) || empty( $ids[ $k[1] ] ) ) { return $m[0]; }
        $id = (int) $ids[ $k[1] ];
        $attrs = json_decode( $m[1], true );
        if ( ! is_array( $attrs ) ) { return $m[0]; }
        $attrs = array( 'id' => $id ) + $attrs;
        // The block editor saves the attachment as class="wp-image-<id>" on the <img>.
        $inner = preg_replace( '~<img src="([^"]+)" alt="([^"]*)"\s*/>~', '<img src="$1" alt="$2" class="wp-image-' . $id . '"/>', $m[2], 1 );
        return '<!-- wp:image ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' -->' . $inner . '<!-- /wp:image -->';
    }, $markup );
    return preg_replace_callback( '~/assets/img/([a-z0-9-]+)\.webp~', function ( $m ) {
        $url = ionp_media_url( $m[1] );
        return $url ? $url : get_theme_file_uri( 'assets/img/' . $m[1] . '.webp' );
    }, $markup );
}

/** Block markup of a theme page (tokens resolved), or ''. Only fixed slugs are accepted. */
function ionp_page_markup( $slug ) {
    if ( ! preg_match( '~^[a-z0-9-]+$~D', $slug ) ) { return ''; }
    $file = get_theme_file_path( 'parts/pages/' . $slug . '.html' );
    return is_readable( $file ) ? ionp_resolve_blocks( (string) file_get_contents( $file ) ) : '';
}

/** Root-relative links (/contact-us, /#about) become full links in the site's trailing-slash form. */
function ionp_links( $html ) {
    return preg_replace_callback( '~href="(/(?!/)[^"<>]*)"~', function ( $m ) {
        if ( preg_match( '~^/[a-z0-9-]+$~D', $m[1] ) || str_starts_with( $m[1], '/#' ) ) { return 'href="' . esc_url( ionp_url( $m[1] ) ) . '"'; }
        return 'href="' . esc_url( home_url( $m[1] ) ) . '"';
    }, $html );
}

/** Logo mark: the original ION POWER lightning bolt. */
function ionp_bolt() {
    return '<span class="ionp-bolt" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg></span>';
}

/** Heading band for pages without their own (posts, archives, pages created from scratch). */
function ionp_page_head( $title, $intro = '' ) {
    echo '<section class="ionp-page-hero ionp-page-hero-plain"><div class="ionp-page-hero-inner"><h1>' . esc_html( $title ) . '</h1>' . ( $intro ? '<p class="ionp-lead">' . esc_html( $intro ) . '</p>' : '' ) . '</div></section>';
}

/** Main menu items: the original one-page sections, then Contact Us. Used for the fallback and the setup-created menu. */
function ionp_menu_items() {
    return array(
        array( '/#about', 'About' ),
        array( '/#cavitation', 'Cavitation' ),
        array( '/#a-zerogrid-solution', 'Zero-Grid' ),
        array( '/#power-beyond', 'Power Beyond' ),
        array( '/#the-process', 'The Process' ),
        array( '/#gallery', 'Gallery' ),
        array( '/#build-own-operate', 'B.O.O.D' ),
        array( '/contact-us', 'Contact Us', 'ionp-cta' ),
    );
}

function ionp_fallback_menu() {
    $html = '<ul class="ionp-menu">';
    foreach ( ionp_menu_items() as $item ) {
        $current = '/contact-us' === $item[0] && 'contact-us' === ionp_current_route();
        $html .= '<li class="menu-item' . ( isset( $item[2] ) ? ' ' . esc_attr( $item[2] ) : '' ) . ( $current ? ' current-menu-item' : '' ) . '"><a href="' . esc_url( ionp_url( $item[0] ) ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>' . esc_html( $item[1] ) . '</a></li>';
    }
    return $html . '</ul>';
}

/** Request path relative to the site root, e.g. "contact-us". */
function ionp_request_path() {
    $path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidationSanitization.InputNotSanitized -- Only compared with fixed strings.
    $base = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
    if ( $base && str_starts_with( $path, $base ) ) { $path = substr( $path, strlen( $base ) ); }
    return trim( rawurldecode( $path ), '/' );
}

/** Theme page served by the theme itself: no published WordPress page has that slug yet (a draft never stands in). */
function ionp_virtual_route() {
    $path = ionp_request_path();
    if ( ! in_array( $path, ionp_routes(), true ) || is_preview() ) { return ''; }
    if ( is_404() ) { return $path; }
    if ( is_page() && 'publish' !== get_post_status( get_queried_object_id() ) ) { return $path; }
    return '';
}

/** Old Strikingly addresses: the cookie policy lived at /pages/cookie-policy. */
add_action( 'template_redirect', function () {
    if ( ! is_404() ) { return; }
    if ( 'pages/cookie-policy' === ionp_request_path() ) { wp_safe_redirect( ionp_url( '/cookie-policy' ), 301 ); exit; }
}, 0 );

add_action( 'template_redirect', function () {
    $route = ionp_virtual_route();
    if ( ! $route ) { return; }
    $raw = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidationSanitization.InputNotSanitized -- Only inspected.
    if ( 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && ! str_ends_with( $raw, '/' ) && str_ends_with( user_trailingslashit( '/x' ), '/' ) ) {
        wp_safe_redirect( ionp_url( '/' . $route ), 301 );
        exit;
    }
    $GLOBALS['ionp_route'] = $route;
    global $wp_query;
    $wp_query->is_404 = false;
    status_header( 200 );
}, 1 );
add_filter( 'template_include', function ( $template ) {
    return empty( $GLOBALS['ionp_route'] ) ? $template : get_theme_file_path( 'template-route.php' );
} );

/** Slug of the theme page being shown ("home" for the front page), or ''. */
function ionp_current_route() {
    if ( is_front_page() ) { return 'home'; }
    if ( ! empty( $GLOBALS['ionp_route'] ) ) { return $GLOBALS['ionp_route']; }
    if ( is_page() ) {
        $slug = get_post_field( 'post_name', get_queried_object_id() );
        return in_array( $slug, ionp_routes(), true ) ? $slug : '';
    }
    return '';
}

/** [ionp_form] prints the original contact form (Name, Email, Message). */
add_shortcode( 'ionp_form', function () {
    ob_start();
    ionp_form();
    return (string) ob_get_clean();
} );
