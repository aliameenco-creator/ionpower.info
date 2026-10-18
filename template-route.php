<?php
/** Original URL served before its editable WordPress page exists: renders the same block content the page will get. */
get_header();
echo '<div class="ionp-content">' . apply_filters( 'the_content', ionp_page_markup( $GLOBALS['ionp_route'] ?? '' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme block markup.
get_footer();
