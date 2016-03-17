<?php
/**
 * The main template file.
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 * Learn more: http://codex.wordpress.org/Template_Hierarchy
 *
 * @package wporg-themes
 */

get_header();
?>
	<div id="themes" class="wrap">
		<div class="wp-filter">
			<div class="filter-count">
				<span class="count theme-count"><?php echo number_format_i18n( $wp_query->found_posts ); ?></span>
			</div>

			<ul class="filter-links">
				<li><a href="<?php echo esc_url( home_url( 'browse/featured/' ) ); ?>" data-sort="featured" <?php if ( (is_front_page() && !get_query_var('browse') ) || 'featured' == get_query_var('browse') ) { echo 'class="current"'; } ?>><?php _ex( 'Featured', 'themes', 'wporg-themes' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( 'browse/popular/' ) ); ?>" data-sort="popular" <?php if ( 'popular' == get_query_var('browse') ) { echo 'class="current"'; } ?>><?php _ex( 'Popular', 'themes', 'wporg-themes' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( 'browse/new/' ) ); ?>" data-sort="new" <?php if ( 'new' == get_query_var('browse') ) { echo 'class="current"'; } ?>><?php _ex( 'Latest', 'themes', 'wporg-themes' ); ?></a></li>
				<?php if ( is_user_logged_in() ) { ?>
					<li><a href="<?php echo esc_url( home_url( 'browse/favorites/' ) ); ?>" data-sort="favorites" <?php if ( 'favorites' == get_query_var('browse') ) { echo 'class="current"'; } ?>><?php _ex( 'Favorites', 'themes', 'wporg-themes' ); ?></a></li>
				<?php } ?>
			</ul>

			<a class="drawer-toggle" href="#"><?php _e( 'Feature Filter', 'wporg-themes' ); ?></a>

			<div class="search-form"></div>

			<div class="filter-drawer">
				<div class="buttons">
					<button type="button" disabled="disabled" class="apply-filters button button-secondary"><?php _e( 'Apply Filters', 'wporg-themes' ); ?><span></span></button>
					<button type="button" class="clear-filters button button-secondary"><?php _e( 'Clear', 'wporg-themes' ); ?></button>
				</div>

				<div class="filtered-by">
					<span><?php _e( 'Filtering by:', 'wporg-themes' ); ?></span>
					<div class="tags"></div>
					<a href="#"><?php _e( 'Edit', 'wporg-themes' ); ?></a>
				</div>

				<?php foreach ( wporg_themes_get_feature_list() as $feature_name => $features ) : ?>
				<div class="filter-group">
					<h4><?php echo esc_html( $feature_name ); ?></h4>
					<ol class="feature-group">
						<?php foreach ( $features as $feature => $feature_name ) : ?>
						<li>
							<input type="checkbox" id="filter-id-<?php echo esc_attr( $feature ); ?>" value="<?php echo esc_attr( $feature ); ?>" />
							<label for="filter-id-<?php echo esc_attr( $feature ); ?>"><?php echo esc_html( $feature_name ); ?></label>
						</li>
						<?php endforeach; ?>
					</ol>
				</div>
				<?php endforeach; ?>
			</div>
		</div><!-- .wp-filter -->

		<div class="theme-browser content-filterable">
			<div class="themes">
				<?php
				if ( get_query_var('name') && !is_404() ) {
					while ( have_posts() ) {
						the_post();
						$theme = wporg_themes_theme_information( $post->post_name );
						include __DIR__ . '/theme-single.php';
					}
				} else {
					while ( have_posts() ) {
						the_post();
						$theme = wporg_themes_theme_information( $post->post_name );
						include __DIR__ . '/theme.php';
					}

					// Add the navigation between pages
					echo '<nav class="posts-navigation">';
					echo paginate_links( array(
						'mid_size' => 3,
					) );
					echo '</nav>';
				}
				?>
			</div>

			<?php /* TODO: Don't display this for no-js queries where $wp_query->post_count > 0, but JS needs it too. */ ?>
			<p class="no-themes"><?php _e( 'No themes found. Try a different search.', 'wporg-themes' ); ?></p>
		</div>
		<div class="theme-install-overlay"></div>
		<div class="theme-overlay"></div>
		<span class="spinner"></span>
	</div>

<?php
get_footer();
