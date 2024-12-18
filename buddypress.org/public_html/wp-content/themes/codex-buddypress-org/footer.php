			</div>

			<?php if ( function_exists( 'is_bbpress' ) && is_bbpress() ) locate_template( array( 'bbpress-sidebar.php' ), true ); ?>

		</div>
		<hr class="hidden" />

		<?php get_template_part( 'footer', 'edits' ); ?>

		<div id="footer"><div id="footer-inner">
			<div class="links">
				<p>
					<a href="https://wordpress.org"><?php _e( 'WordPress.org', 'bporg' ); ?></a>
					<a href="https://bbpress.org"><?php _e( 'bbPress.org', 'bporg' ); ?></a>
					<a href="https://buddypress.org"><?php _e( 'BuddyPress.org', 'bporg' ); ?></a>
					<a href="https://ma.tt"><?php _e( 'Matt', 'bporg' ); ?></a>
					<a href="<?php bloginfo( 'rss2_url' ); ?>"><?php _e( 'Blog RSS', 'bporg' ); ?></a>
				</p>
			</div>
			<div class="details">
				<p>
					<a href="https://buddypress.org/about/gpl/"><?php _e( 'GPL', 'bporg' ); ?></a>
					<a href="https://buddypress.org/contact/"><?php _e( 'Contact Us', 'bporg' ); ?></a>
					<a href="https://wordpress.org/about/privacy/"><?php _e('Privacy', 'bbporg'); ?></a>
					<a href="https://buddypress.org/terms/"><?php _e( 'Terms of Service', 'bporg' ); ?></a>
					<a href="https://x.com/buddypressdev"><?php _e( 'X', 'bporg' ); ?></a>
				</p>
			</div>
		</div></div>
		<?php wp_footer(); ?>
	</body>
</html>
