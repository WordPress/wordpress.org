<?php
/**
 * Footer template
 */
?>
	</div><!-- #main -->

	<div id="footer" class="group" role="contentinfo">
		<div id="colophon">

<?php
	/* A sidebar in the footer? Yep. You can can customize
	 * your footer with four columns of widgets.
	 */
	get_sidebar( 'footer' );
?>

			<a href="http://wordpress.org" title="Code is Poetry | WordPress.org" class="wc-code-is-poetry">
				Code is Poetry
			</a>

			<?php wp_nav_menu( array( 'container_class' => 'menu-footer', 'theme_location' => 'primary', 'depth' => 1 ) ); ?>
			
		</div><!-- #colophon -->
	</div><!-- #footer -->

</div><!-- #wrapper -->

<?php
	/* Always have wp_footer() just before the closing </body>
	 * tag of your theme, or you will break many plugins, which
	 * generally use this hook to reference JavaScript files.
	 */

	wp_footer();
?>
</body>
</html>
