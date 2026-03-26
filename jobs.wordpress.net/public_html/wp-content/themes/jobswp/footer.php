<?php
/**
 * The template for displaying the footer.
 *
 * @package jobswp
 */
?>

	<footer class="site-footer">
		<div class="site-footer__inner">
			<div class="site-footer__columns">
				<div class="site-footer__column">
					<h4><?php esc_html_e( 'About', 'jobswp' ); ?></h4>
					<ul>
						<li><a href="https://wordpress.org/about/"><?php esc_html_e( 'About WordPress', 'jobswp' ); ?></a></li>
						<li><a href="https://wordpress.org/news/"><?php esc_html_e( 'News', 'jobswp' ); ?></a></li>
						<li><a href="https://wordpress.org/hosting/"><?php esc_html_e( 'Hosting', 'jobswp' ); ?></a></li>
						<li><a href="https://wordpress.org/donate/"><?php esc_html_e( 'Donate', 'jobswp' ); ?></a></li>
					</ul>
				</div>
				<div class="site-footer__column">
					<h4><?php esc_html_e( 'Learn', 'jobswp' ); ?></h4>
					<ul>
						<li><a href="https://learn.wordpress.org/"><?php esc_html_e( 'Learn WordPress', 'jobswp' ); ?></a></li>
						<li><a href="https://developer.wordpress.org/"><?php esc_html_e( 'Developer Resources', 'jobswp' ); ?></a></li>
						<li><a href="https://wordpress.org/documentation/"><?php esc_html_e( 'Documentation', 'jobswp' ); ?></a></li>
						<li><a href="https://wordpress.org/support/"><?php esc_html_e( 'Support Forums', 'jobswp' ); ?></a></li>
					</ul>
				</div>
				<div class="site-footer__column">
					<h4><?php esc_html_e( 'Community', 'jobswp' ); ?></h4>
					<ul>
						<li><a href="https://make.wordpress.org/"><?php esc_html_e( 'Get Involved', 'jobswp' ); ?></a></li>
						<li><a href="https://central.wordcamp.org/"><?php esc_html_e( 'WordCamp', 'jobswp' ); ?></a></li>
						<li><a href="https://wordpress.tv/"><?php esc_html_e( 'WordPress.TV', 'jobswp' ); ?></a></li>
						<li><a href="https://wordpress.org/showcase/"><?php esc_html_e( 'Showcase', 'jobswp' ); ?></a></li>
					</ul>
				</div>
				<div class="site-footer__column">
					<h4><?php esc_html_e( 'WordPress Jobs', 'jobswp' ); ?></h4>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Current Openings', 'jobswp' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/post-a-job/' ) ); ?>"><?php esc_html_e( 'Post a Job', 'jobswp' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>"><?php esc_html_e( 'FAQ', 'jobswp' ); ?></a></li>
						<li><a href="https://wordpress.org/"><?php esc_html_e( 'WordPress.org', 'jobswp' ); ?></a></li>
					</ul>
				</div>
			</div>
			<div class="site-footer__bottom">
				<div class="site-footer__social">
					<a href="https://twitter.com/WordPress" aria-label="<?php esc_attr_e( 'WordPress on X', 'jobswp' ); ?>">X / Twitter</a>
					<a href="https://www.facebook.com/WordPress/" aria-label="<?php esc_attr_e( 'WordPress on Facebook', 'jobswp' ); ?>">Facebook</a>
					<a href="https://www.youtube.com/wordpress" aria-label="<?php esc_attr_e( 'WordPress on YouTube', 'jobswp' ); ?>">YouTube</a>
				</div>
				<p class="site-footer__tagline"><?php esc_html_e( 'Code is Poetry.', 'jobswp' ); ?></p>
			</div>
		</div>
	</footer>

<?php wp_footer(); ?>

</body>
</html>
