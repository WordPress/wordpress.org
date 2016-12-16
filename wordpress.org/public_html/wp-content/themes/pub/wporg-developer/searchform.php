<?php
/**
 * The template for displaying search forms in wporg-developer
 *
 * @package wporg-developer
 */
?>
<div class="search-section section clear <?php if ( ! ( is_page( 'reference' ) || is_search() || is_404() ) ) { echo 'hide-if-js'; } ?>">

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

	<?php
		$is_handbook = get_query_var( 'is_handbook' );
		$search_url  = get_query_var( 'current_handbook_home_url' );
		$search_url  = $search_url ? $search_url : home_url( '/' );
		$filters     = ! ( $is_handbook || is_404() );
		$form_class  = ( $filters ) ? ' searchform-filtered' : '';
		if ( $is_handbook ) {
			$form_class .= ' searchform-handbook';
		}
	?>

	<form role="search" method="get" class="searchform<?php echo esc_attr( $form_class ); ?>" action="<?php echo esc_url( $search_url ); ?>">
		<label for="search-field" class="screen-reader-text"><?php _ex( 'Search for:', 'label', 'wporg' ); ?></label>
		<input type="text" id="search-field" class="search-field" placeholder="<?php echo esc_attr_x( 'Search &hellip;', 'placeholder', 'wporg' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s">
		<button class="button button-primary button-search"><i class="dashicons dashicons-search"></i><span class="screen-reader-text"><?php _e( 'Search plugins', 'wporg' ); ?></span></button>
	<?php if ( $filters ) : ?>

		<div class="search-post-type">
			<span><?php _e( 'Filter by type:', 'wporg' ); ?></span>
			<?php
				$search_post_types = array(
					'wp-parser-function' => __( 'Functions', 'wporg' ),
					'wp-parser-hook'     => __( 'Hooks',     'wporg' ),
					'wp-parser-class'    => __( 'Classes',   'wporg' ),
					'wp-parser-method'   => __( 'Methods',   'wporg' ),
				);
				
				$qv_post_type = array_filter( (array) get_query_var( 'post_type' ) );	
				$no_filters   = get_query_var( 'empty_post_type_search' );

				if ( ! is_search() || in_array( 'any', $qv_post_type ) || $no_filters ) {
					// No filters used.
					$qv_post_type = array();
				}
						
				foreach ( $search_post_types as $post_type => $label ) {
					$checked = checked( in_array( $post_type, $qv_post_type ), true, false );
				?>
						<label><input type="checkbox" name="post_type[]" value="<?php echo esc_attr( $post_type ); ?>"
						<?php echo $checked; ?> /> <?php echo $label; ?></label>
			<?php } ?>
		</div>

	<?php endif; ?>

	</form>

</div><!-- /search-guide -->
