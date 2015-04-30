<?php
/**
 * The template for displaying search forms in wporg-developer
 *
 * @package wporg-developer
 */
?>
<div class="search-section section clear <?php if ( ! is_page( 'reference' ) ) { echo 'hide-if-js'; } ?>">

<?php if ( is_search() ) { ?>

	<div class="search-results-summary"><?php
	$count = (int) $GLOBALS['wp_query']->found_posts;

	if ( $count ) {
		if ( is_paged() ) {
			$start = get_query_var( 'posts_per_page' ) * ( get_query_var( 'paged' ) - 1 );
		} else {
			$start = 0;
		}
		$end = min( $count, $start + get_query_var( 'posts_per_page' ) );
		printf(
			_n( '<strong>%d</strong> result found for "<strong>%s</strong>".', '<strong>%d</strong> results found for "<strong>%s</strong>". Showing results %d to %d.', $count, 'wporg' ),
			$count,
			esc_html( get_search_query() ),
			$start + 1,
			$end
		);
	} else {
		printf( __( '<strong>%d</strong> results found for "<strong>%s</strong>".', 'wporg' ), $count, esc_html( get_search_query() ) );
	}
	?></div>

<?php } ?>

	<form role="search" method="get" class="searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<div>
		<label>
			<label for="search-field" class="screen-reader-text"><?php _ex( 'Search for:', 'label', 'wporg' ); ?></label>
			<input type="text" id="search-field" class="search-field" placeholder="<?php echo esc_attr_x( 'Search &hellip;', 'placeholder', 'wporg' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s">
		</label>
		<input type="submit" class="shiny-blue search-submit" value="<?php echo esc_attr_x( 'Search', 'submit button', 'wporg' ); ?>">
		</div>

		<div class="search-post-type">
			<span><?php _e( 'Filter by type:', 'wporg' ); ?></span>
			<?php
				$search_post_types = array(
					'wp-parser-function' => __( 'Functions', 'wporg' ),
					'wp-parser-hook'     => __( 'Hooks',     'wporg' ),
					'wp-parser-class'    => __( 'Classes',   'wporg' ),
					'wp-parser-method'   => __( 'Methods',   'wporg' ),
				);
				foreach ( $search_post_types as $post_type => $label ) {
					$qv_post_type = (array) get_query_var( 'post_type' );
				?>
					<label><input type="checkbox" name="post_type[]" value="<?php echo esc_attr( $post_type ); ?>"
					<?php checked( ! is_search() || in_array( 'any', $qv_post_type ) || in_array( $post_type, $qv_post_type ) ); ?> /> <?php echo $label; ?></label>
				<?php } ?>
		</div>
	</form>

</div><!-- /search-guide -->
