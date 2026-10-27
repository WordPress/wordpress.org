<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the #content div and all content after
 *
 * @package bporg-developer
 * @since 1.0.0
 */

?>
        </div><!-- #content -->

    </div><!-- #page -->

    <hr class="hidden" />

    <div id="footer"><div id="footer-inner">
        <div class="links">
            <p>
                <a href="https://wordpress.org"><?php esc_html_e( 'WordPress.org', 'bporg-developer' ); ?></a>
                <a href="https://bbpress.org"><?php esc_html_e( 'bbPress.org', 'bporg-developer' ); ?></a>
                <a href="https://buddypress.org"><?php esc_html_e( 'BuddyPress.org', 'bporg-developer' ); ?></a>
                <a href="https://ma.tt"><?php esc_html_e( 'Matt', 'bporg-developer' ); ?></a>
                <a href="<?php bloginfo( 'rss2_url' ); ?>"><?php esc_html_e( 'Blog RSS', 'bporg-developer' ); ?></a>
            </p>
        </div>
        <div class="details">
            <p>
                <a href="https://buddypress.org/about/gpl/"><?php esc_html_e( 'GPL', 'bporg-developer' ); ?></a>
                <a href="https://buddypress.org/contact/"><?php esc_html_e( 'Contact Us', 'bporg-developer' ); ?></a>
                <a href="https://wordpress.org/about/privacy/"><?php esc_html_e('Privacy', 'bporg-developer'); ?></a>
                <a href="https://buddypress.org/terms/"><?php esc_html_e( 'Terms of Service', 'bporg-developer' ); ?></a>
                <a href="https://x.com/buddypressdev"><?php esc_html_e( 'X', 'bporg-developer' ); ?></a>
            </p>
        </div>
    </div></div>
    <?php wp_footer(); ?>
</body>
</html>
