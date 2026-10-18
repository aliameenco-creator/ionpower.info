<?php
/** Page not found. */
get_header();
ionp_page_head( 'Page not found', 'The page you are looking for does not exist or has moved.' );
echo '<div class="ionp-content ionp-plain"><p><a class="ionp-textlink" href="' . esc_url( home_url( '/' ) ) . '">Back to ION POWER</a></p></div>';
get_footer();
