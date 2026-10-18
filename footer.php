</main>
<footer class="ionp-footer">
    <div class="ionp-footer-inner">
        <div class="ionp-fbig" aria-hidden="true">ION POWER</div>
        <div class="ionp-frow">
            <a class="ionp-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo ionp_bolt(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?><span>ION POWER</span></a>
            <nav class="ionp-fnav" aria-label="Footer">
                <?php
                foreach ( array( '/#about' => 'About', '/#cavitation' => 'Cavitation', '/#a-zerogrid-solution' => 'Zero-Grid', '/#power-beyond' => 'Power Beyond', '/#gallery' => 'Gallery', '/contact-us' => 'Contact Us' ) as $ionp_path => $ionp_label ) {
                    echo '<a href="' . esc_url( ionp_url( $ionp_path ) ) . '">' . esc_html( $ionp_label ) . '</a>';
                }
                ?>
            </nav>
            <a class="ionp-totop" href="#main" aria-label="Back to top"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M6 11l6-6 6 6"/></svg></a>
        </div>
        <div class="ionp-fbottom">
            <p class="ionp-copy">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> ION POWER. All rights reserved.</p>
            <nav class="ionp-legal" aria-label="Legal">
                <?php
                foreach ( array( '/privacy-policy' => 'Privacy Policy', '/terms-and-conditions' => 'Terms and Conditions', '/cookie-policy' => 'Cookie Policy' ) as $ionp_path => $ionp_label ) {
                    echo '<a href="' . esc_url( ionp_url( $ionp_path ) ) . '">' . esc_html( $ionp_label ) . '</a>';
                }
                ?>
            </nav>
        </div>
    </div>
</footer>
<div class="ionp-lb" hidden role="dialog" aria-modal="true" aria-label="Gallery image"><button class="ionp-lb-x" type="button" aria-label="Close">&times;</button><button class="ionp-lb-p" type="button" aria-label="Previous image">&#8249;</button><img alt=""><button class="ionp-lb-n" type="button" aria-label="Next image">&#8250;</button></div>
<?php wp_footer(); ?>
</body></html>
