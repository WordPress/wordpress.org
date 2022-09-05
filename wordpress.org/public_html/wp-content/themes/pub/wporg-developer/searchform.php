<?php
/**
 * The template for displaying search forms in wporg-developer
 *
 * @package wporg-developer
 */

$show_filters = DevHub\should_show_search_filters();

$is_handbook = $GLOBALS['wp_query']->is_handbook;

// Add classes for different views
$classes  = ( ! $show_filters && ! $is_handbook ) ? ' search-wrap-inline' : '';
$classes .= ( is_page( 'reference' ) ) ? ' search-wrap-embedded' : '';

$form_class = ( $is_handbook ) ? 'searchform-handbook' : '';

$search_url = get_query_var( 'current_handbook_home_url' );
$search_url = $search_url ? $search_url : home_url( '/' );

$placeholder = _x( 'Search reference', 'placeholder', 'wporg' );
if ( $is_handbook ) {
	$placeholder = sprintf(
		/* translators: %s handbook name. */
		_x( 'Search %s', 'placeholder', 'wporg' ),
		get_query_var( 'current_handbook_name' )
	);
}

?>
<div class="search-wrap <?php echo esc_attr( $classes ); ?>">
	<form role="search" method="get" class="searchform <?php echo esc_attr( $form_class ); ?>" action="<?php echo esc_url( $search_url ); ?>">
		<div class="search-field">
			<input type="search" id="search-field" placeholder="<?php echo esc_attr( $placeholder ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s">
			<button class="button-search" aria-label="<?php esc_html_e( 'Search', 'wporg' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" role="img" aria-hidden="true" focusable="false"><path d="M13.5 6C10.5 6 8 8.5 8 11.5c0 1.1.3 2.1.9 3l-3.4 3 1 1.1 3.4-2.9c1 .9 2.2 1.4 3.6 1.4 3 0 5.5-2.5 5.5-5.5C19 8.5 16.5 6 13.5 6zm0 9.5c-2.2 0-4-1.8-4-4s1.8-4 4-4 4 1.8 4 4-1.8 4-4 4z"></path></svg>	
			</button>
		</div>
	<?php if ( $show_filters ) : ?>

		<div class="search-post-type">
			<span><?php _e( 'Filter by type:', 'wporg' ); ?></span>
			<?php
				$search_post_types = array(
					'wp-parser-function' => __( 'Functions', 'wporg' ),
					'wp-parser-hook'     => __( 'Hooks', 'wporg' ),
					'wp-parser-class'    => __( 'Classes', 'wporg' ),
					'wp-parser-method'   => __( 'Methods', 'wporg' ),
				);

				$qv_post_type = array_filter( (array) get_query_var( 'post_type' ) );
				$no_filters   = $GLOBALS['wp_query']->is_empty_post_type_search;

				if ( ! is_search() || in_array( 'any', $qv_post_type ) || $no_filters ) {
					// No filters used.
					$qv_post_type = array();
				}

				foreach ( $search_post_types as $post_type => $label ) {
					$checked = checked( in_array( $post_type, $qv_post_type ), true, false );
					?>
					<div>
						<input id="<?php echo esc_attr( $post_type ); ?>" type="checkbox" name="post_type[]" value="<?php echo esc_attr( $post_type ); ?>" <?php echo $checked; ?> />
						<label for="<?php echo esc_attr( $post_type ); ?>"><?php echo $label; ?></label>	
					</div>
						
			<?php } ?>
		</div>

	<?php endif; ?>

	</form>
</div>
