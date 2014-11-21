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

include ABSPATH . 'wp-admin/includes/theme.php';
$themes = themes_api( 'query_themes', array(
	'per_page' => 15,
	'browse'   => get_query_var( 'attachment' ) ? get_query_var( 'attachment' )  : 'search',
) );

get_header();
?>

<div id="themes" class="wrap">
	<div class="wp-filter">
		<div class="filter-count">
			<span class="count theme-count"></span>
		</div>

		<ul class="filter-links">
			<li><a href="#" data-sort="featured"><?php _ex( 'Featured', 'themes' ); ?></a></li>
			<li><a href="#" data-sort="popular"><?php _ex( 'Popular', 'themes' ); ?></a></li>
			<li><a href="#" data-sort="new"><?php _ex( 'Latest', 'themes' ); ?></a></li>
		</ul>

		<a class="drawer-toggle" href="#"><?php _e( 'Feature Filter' ); ?></a>

		<div class="search-form"></div>

		<div class="filter-drawer">
			<div class="buttons">
				<a class="apply-filters button button-secondary" href="#"><?php _e( 'Apply Filters' ); ?><span></span></a>
				<a class="clear-filters button button-secondary" href="#"><?php _e( 'Clear' ); ?></a>
			</div>
			<?php foreach ( get_theme_feature_list() as $feature_name => $features ) : ?>
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
			<div class="filtered-by">
				<span><?php _e( 'Filtering by:' ); ?></span>
				<div class="tags"></div>
				<a href="#"><?php _e( 'Edit' ); ?></a>
			</div>
		</div>
	</div>
	<div class="theme-browser content-filterable">
		<?php
			if ( ! is_wp_error( $themes ) ) :
				foreach ( $themes->themes as $theme ) :
					get_template_part( 'content', 'index' );
				endforeach;
			endif;
		?>
	</div>
	<div class="theme-overlay"></div>

	<p class="no-themes"><?php _e( 'No themes found. Try a different search.' ); ?></p>
	<span class="spinner"></span>
</div>

<?php
get_footer();
