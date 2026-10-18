<?php
/**
 * Titles and share tags (parts/pages.json). The homepage keeps the original title "Ion Power"; the old site published
 * no meta description, so none is invented for it. When an SEO plugin (Yoast, Rank Math, AIOSEO, SEOPress) is active it
 * owns the tags of WordPress pages; the theme still titles the pages it serves itself before setup.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ionp_seo_data( $route ) {
    foreach ( ionp_pages_data() as $page ) {
        if ( $page['slug'] === $route ) { return $page; }
    }
    return array();
}

function ionp_seo_plugin_active() {
    return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}

add_filter( 'pre_get_document_title', function ( $title ) {
    $route = ionp_current_route();
    $virtual = ! empty( $GLOBALS['ionp_route'] );
    if ( ! $route || ( ionp_seo_plugin_active() && ! $virtual ) ) { return $title; }
    $seo = ionp_seo_data( $route );
    return ! empty( $seo['seo_title'] ) ? $seo['seo_title'] : $title;
}, 99 );

add_action( 'wp_head', function () {
    $route = ionp_current_route();
    if ( ! $route || ionp_seo_plugin_active() ) { return; }
    $seo = ionp_seo_data( $route );
    $url = 'home' === $route ? home_url( '/' ) : ionp_url( '/' . $route );
    $title = $seo['seo_title'] ?? 'Ion Power';
    $description = $seo['description'] ?? '';
    if ( '' !== $description ) { echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n"; }
    if ( ! empty( $GLOBALS['ionp_route'] ) || ( 'home' === $route && ! is_singular() ) ) { echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n"; }
    $image = get_theme_file_uri( 'assets/ion-share.jpg' );
    echo '<meta property="og:type" content="website"><meta property="og:site_name" content="Ion Power"><meta property="og:title" content="' . esc_attr( $title ) . '"><meta property="og:url" content="' . esc_url( $url ) . '"><meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="' . esc_attr( $title ) . '"><meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
    if ( '' !== $description ) { echo '<meta property="og:description" content="' . esc_attr( $description ) . '"><meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n"; }
    if ( 'home' === $route ) {
        $schema = array( '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Ion Power', 'url' => home_url( '/' ) );
        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ) . "</script>\n";
    }
}, 3 );

/** Add the theme-served pages to WordPress's XML sitemap until real WordPress pages provide them. */
add_action( 'init', function () {
    if ( ! function_exists( 'wp_register_sitemap_provider' ) || ! class_exists( 'WP_Sitemaps_Provider' ) ) { return; }
    wp_register_sitemap_provider( 'ionp', new class() extends WP_Sitemaps_Provider {
        public function __construct() { $this->name = 'ionp'; $this->object_type = 'ionp'; }
        public function get_url_list( $page_num, $object_subtype = '' ) {
            $urls = 'page' === get_option( 'show_on_front' ) ? array() : array( array( 'loc' => home_url( '/' ) ) );
            foreach ( ionp_routes() as $route ) {
                $page = get_page_by_path( $route );
                if ( ! $page || 'publish' !== $page->post_status ) { $urls[] = array( 'loc' => ionp_url( '/' . $route ) ); }
            }
            return $urls;
        }
        public function get_max_num_pages( $object_subtype = '' ) { return 1; }
    } );
} );
