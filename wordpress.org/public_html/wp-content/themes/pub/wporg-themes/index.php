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

$menu_items = array(
	array(
		'href' => home_url( '/' ),
		'label' => _x( 'Popular', 'themes', 'wporg-themes' ),
		'data-sort' => 'popular',
		'is-current' => ( is_front_page() && ! get_query_var( 'browse' ) ) || 'popular' == get_query_var( 'browse' ),
	),
	array(
		'href' => home_url( 'browse/new/' ),
		'label' => _x( 'Latest', 'themes', 'wporg-themes' ),
		'data-sort' => 'new',
		'is-current' => 'new' == get_query_var( 'browse' ),
	),
	array(
		'href' => home_url( 'browse/commercial/' ),
		'label' => _x( 'Commercial', 'theme category', 'wporg-themes' ),
		'data-model' => 'commercial',
		'is-current' => 'commercial' == get_query_var( 'theme_business_model' ),
	),
	array(
		'href' => home_url( 'browse/community/' ),
		'label' => _x( 'Community', 'theme category', 'wporg-themes' ),
		'data-model' => 'community',
		'is-current' => 'community' == get_query_var( 'theme_business_model' ),
	),
	array(
		'href' => home_url( 'tags/full-site-editing/' ),
		'label' => _x( 'Block Themes', 'themes filter', 'wporg-themes' ),
		'data-tag' => 'full-site-editing',
		'is-current' => 'full-site-editing' == get_query_var( 'tag' ),
	),
);

get_header();
?>
	<main id="themes" class="wrap">
		<div class="wp-filter">
			<h2 class="screen-reader-text"><?php _e( 'Themes List', 'wporg-themes' ); ?></h2>
			<ul class="filter-links">
				<?php
				foreach ( $menu_items as $item ) {
					$data_attrs = array();
					foreach ( array( 'data-sort', 'data-tag', 'data-model' ) as $key ) {
						if ( isset( $item[ $key ] ) ) {
							$data_attrs[] = $key . '="' . esc_attr( $item[ $key ] ) . '"';
						}
					}
					printf(
						'<li><a href="%1$s" %2$s class="filter-tab %3$s">%4$s</a></li>',
						esc_url( $item['href'] ),
						implode( ' ', $data_attrs ),
						$item['is-current'] ? 'current' : '',
						esc_html( $item['label'] )
					);
				}
				?>
				<li style="flex-grow:1;"></li>
				<?php if ( is_user_logged_in() ) : ?>
					<li>
						<a
							href="<?php echo esc_url( home_url( 'browse/favorites/' ) ); ?>"
							data-sort="favorites"
							class="filter-tab <?php echo ( 'favorites' == get_query_var( 'browse' ) ) ? 'current' : ''; ?>"
						>
							<?php _ex( 'Favorites', 'themes', 'wporg-themes' ); ?>
						</a>
					</li>
				<?php endif; ?>
				<li>
					<a class="drawer-toggle" href="#">
						<span class="drawer-text"><?php _e( 'Feature Filter', 'wporg-themes' ); ?></span>
					</a>
				</li>
			</ul>

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
				if ( get_query_var( 'name' ) && ! is_404() ) {
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
				}
				?>
			</div>

			<?php /* TODO: Don't display this for no-js queries where $wp_query->post_count > 0, but JS needs it too. */ ?>
			<p class="no-themes"><?php _e( 'No themes found. Try a different search.', 'wporg-themes' ); ?></p>
		</div>
		<div class="theme-load-more">
			<button class="button button-primary button-large js-load-more-themes hidden"><?php esc_html_e( 'Load more themes', 'wporg-themes' ); ?></button>
		</div>
		<div class="theme-install-overlay"></div>
		<div class="theme-overlay"></div>
		<span class="spinner"></span>
	</main>

	<?php get_template_part( 'sidebar', 'footer' ); ?>

<?php
get_footer();
