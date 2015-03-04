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

if ( ! function_exists( 'get_theme_feature_list' ) ) {
	include ABSPATH . 'wp-admin/includes/theme.php';
}
get_header();
?>

	<div id="themes" class="wrap">
		<div class="wp-filter">
			<div class="filter-count">
				<span class="count theme-count"><?php echo $GLOBALS['wp_query']->found_posts; ?></span>
			</div>

			<ul class="filter-links">
				<li><a href="<?php echo esc_url( home_url( 'browse/featured/' ) ); ?>" data-sort="featured"><?php _ex( 'Featured', 'themes' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( 'browse/popular/' ) ); ?>" data-sort="popular"><?php _ex( 'Popular', 'themes' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( 'browse/new/' ) ); ?>" data-sort="new"><?php _ex( 'Latest', 'themes' ); ?></a></li>
			</ul>

			<a class="drawer-toggle" href="#"><?php _e( 'Feature Filter' ); ?></a>

			<div class="search-form"></div>

			<div class="filter-drawer">
				<div class="buttons">
					<button type="button" disabled="disabled" class="apply-filters button button-secondary"><?php _e( 'Apply Filters' ); ?><span></span></button>
					<button type="button" class="clear-filters button button-secondary"><?php _e( 'Clear' ); ?></button>
				</div>

				<div class="filtered-by">
					<span><?php _e( 'Filtering by:' ); ?></span>
					<div class="tags"></div>
					<a href="#"><?php _e( 'Edit' ); ?></a>
				</div>

				<?php foreach( get_theme_feature_list() as $feature_name => $features ) : ?>
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
					while ( have_posts() ) :
						the_post();
						get_template_part( 'content', is_single() ? 'single' : 'index' );
					endwhile;

					the_posts_navigation( array(
						'prev_text' => __( 'Next', 'wporg-themes' ),
						'next_text' => __( 'Previous', 'wporg-themes' ),
					) );
				?>
			</div>
			<p class="no-themes"><?php _e( 'No themes found. Try a different search.' ); ?></p>
		</div>
		<div class="theme-install-overlay"></div>
		<div class="theme-overlay"></div>
		<span class="spinner"></span>
	</div>

<?php
get_footer();
