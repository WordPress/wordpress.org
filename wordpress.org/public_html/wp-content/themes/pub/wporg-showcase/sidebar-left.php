<?php
/**
 * The left sidebar containing the site submission and tag cloud links.
 *
 * @package wporg-showcase
 */

?>
		<div class="col-2 secondary leftsidebar">
			<a href="<?php echo esc_url( home_url( '/submit-a-wordpress-site/' ) ); ?>" class="wpsc-submit-site"><?php esc_html_e( 'Submit a Site &rarr;', 'wporg-showcase' ); ?></a>

			<h2 class="heading search"><?php _e( 'Search', 'wporg-showcase' ); ?></h2>
			<?php // @todo: use get_search_form(); ?>
			<form method="get" id="searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<input type="text" value="<?php the_search_query(); ?>" name="s" id="s" class="text" />
				<input type="submit" id="searchsubmit" value="<?php esc_attr_e( 'Go', 'wporg-showcase' ); ?>" class="button" />
			</form>

			<?php popular_tags(); ?>
			<a href='<?php echo esc_url( home_url( '/tag-cloud/' ) ); ?>' class="wpsc-all-tags"><?php esc_html_e( 'View All Tags &rarr;', 'wporg-showcase' ); ?></a>

			<h2 class="heading"><?php _e( 'Browse by Flavor', 'wporg-showcase' ); ?></h2>
			<ul class="submenu">
				<?php wp_list_categories( 'exclude=4&title_li=' ); ?>
			</ul>
		</div>
