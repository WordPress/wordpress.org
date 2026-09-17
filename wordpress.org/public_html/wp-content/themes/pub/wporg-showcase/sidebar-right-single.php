<?php
/**
 * Sidebar shown alongside a single showcase entry.
 *
 * @package wporg-showcase
 */

?>
		<div class="col-3 rightsidebar">
			<div class="rightsidebarwrapper">
			<div class="currentSiteRating">
				<p class="button"><a href="<?php echo esc_url( 'http://' . get_site_domain( false, false ) ); ?>"><?php esc_html_e( 'Visit Site', 'wporg-showcase' ); ?></a></p>

				<?php wp_flavors(); ?>
				<br />
				<?php tags_with_count( 'list', '<h2 class="heading">' . __( 'Tags', 'wporg-showcase' ) . '</h2><ul>', '', '</ul>' ); ?>
			</div>
			</div>
		</div>
