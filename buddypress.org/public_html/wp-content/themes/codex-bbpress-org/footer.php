			</div>
		</div>
		<hr class="hidden" />

		<?php get_template_part( 'footer', 'edits' ); ?>

		<div id="footer"><div id="footer-inner">
			<div class="links">
				<p>
					<a href="https://wordpress.org"><?php esc_html_e( 'WordPress.org', 'bbporg' ); ?></a>
					<a href="https://bbpress.org"><?php esc_html_e( 'bbPress.org', 'bbporg' ); ?></a>
					<a href="https://buddypress.org"><?php esc_html_e( 'BuddyPress.org', 'bbporg' ); ?></a>
					<a href="https://ma.tt"><?php esc_html_e( 'Matt', 'bbporg' ); ?></a>
					<a href="<?php bloginfo( 'rss2_url' ); ?>" title="<?php esc_attr_e( 'RSS Feed for Articles', 'bbporg' ); ?>"><?php esc_html_e( 'Blog RSS', 'bbporg' ); ?></a>
				</p>
			</div>
			<div class="details">
				<p>
					<a href="https://bbpress.org/about/gpl/"><?php esc_html_e( 'GPL', 'bbporg' ); ?></a>
					<a href="https://bbpress.org/contact/"><?php esc_html_e( 'Contact Us', 'bbporg' ); ?></a>
					<a href="https://wordpress.org/about/privacy/"><?php esc_html_e( 'Privacy', 'bbporg' ); ?></a>
					<a href="https://bbpress.org/terms/"><?php esc_html_e( 'Terms of Service', 'bbporg' ); ?></a>
					<a href="https://x.com/bbpress"><?php esc_html_e( 'X', 'bbporg' ); ?></a>
				</p>
			</div>
		</div></div>
		<?php wp_footer(); ?>
	</body>
</html>
