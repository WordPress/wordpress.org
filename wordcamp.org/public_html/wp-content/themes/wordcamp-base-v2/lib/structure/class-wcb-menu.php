<?php

class WCB_Menu extends WCB_Element {
	function get_id() {
		return 'main-menu';
	}

	function content() { ?>
		<div id="<?php echo $this->get_id(); ?>" class="grid_12">
			<div id="access" role="navigation" class="clearfix">
				<?php /*  Allow screen readers / text browsers to skip the navigation menu and get right to the good stuff */ ?>
				<div class="skip-link screen-reader-text"><a href="#content" title="<?php esc_attr_e( 'Skip to content', 'wordcampbase' ); ?>"><?php _e( 'Skip to content', 'wordcampbase' ); ?></a></div>
				<?php /* Our navigation menu.  If one isn't filled out, wp_nav_menu falls back to wp_page_menu.  The menu assiged to the primary position is the one used.  If none is assigned, the menu with the lowest ID is used.  */ ?>
				<?php wp_nav_menu( array( 'container_class' => 'menu-header', 'theme_location' => 'primary' ) ); ?>

				<?php
				$option = wcb_get_option('featured_button');
				if ( $option['visible'] ): ?>
					<a href="<?php echo esc_url( $option['url'] ); ?>" class="button featured-button">
						<?php echo esc_html( $option['text'] ); ?>
					</a>
				<?php endif; ?>

			</div><!-- #access -->
		</div>
	<?php
	}
}

?>