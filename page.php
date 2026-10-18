<?php
/** WordPress pages: theme pages carry their own heading band; pages created from scratch get one. */
get_header();
while ( have_posts() ) :
    the_post();
    $ionp_hero = str_contains( (string) get_the_content(), 'ionp-page-hero' ) || str_contains( (string) get_the_content(), 'ionp-hero' );
    if ( ! $ionp_hero ) { ionp_page_head( get_the_title() ); }
    echo '<div class="ionp-content' . ( $ionp_hero ? '' : ' ionp-plain' ) . '">';
    the_content();
    echo '</div>';
endwhile;
get_footer();
