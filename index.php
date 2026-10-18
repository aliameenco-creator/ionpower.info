<?php
/** Posts index, single posts, archives and search (the original site had no published posts). */
get_header();
if ( is_singular() ) {
    while ( have_posts() ) {
        the_post();
        ionp_page_head( get_the_title(), get_the_date( 'j F Y' ) );
        echo '<div class="ionp-content ionp-plain">';
        the_content();
        echo '</div>';
    }
} else {
    ionp_page_head( is_search() ? 'Search: ' . get_search_query() : ( is_archive() ? wp_strip_all_tags( get_the_archive_title() ) : 'News' ) );
    echo '<div class="ionp-content ionp-plain">';
    if ( have_posts() ) {
        echo '<div class="ionp-post-list">';
        while ( have_posts() ) {
            the_post();
            echo '<article class="ionp-post-card"><time datetime="' . esc_attr( get_the_date( DATE_W3C ) ) . '">' . esc_html( get_the_date( 'j F Y' ) ) . '</time><h2><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h2><p>' . esc_html( wp_trim_words( get_the_excerpt(), 32 ) ) . '</p></article>';
        }
        echo '</div>';
        the_posts_pagination( array( 'mid_size' => 1 ) );
    } else {
        echo '<p>Nothing has been published here yet.</p>';
    }
    echo '</div>';
}
get_footer();
