<?php
/** Site root: the editable Home page once setup has created it, otherwise the same design from the theme. */
get_header();
if ( 'page' === get_option( 'show_on_front' ) && have_posts() ) {
    while ( have_posts() ) {
        the_post();
        echo '<div class="ionp-content">';
        the_content();
        echo '</div>';
    }
} else {
    echo '<div class="ionp-content">' . apply_filters( 'the_content', ionp_page_markup( 'home' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme block markup.
}
get_footer();
