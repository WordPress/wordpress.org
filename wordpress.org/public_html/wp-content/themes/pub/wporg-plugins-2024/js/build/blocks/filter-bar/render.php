<?php

global $wp_query;
$is_beta = 'beta' === $wp_query->get( 'browse' );
$is_favs = 'favorites' === $wp_query->get( 'browse' );
// The filter bar should not be shown on:
// - singular: not relevant on pages or individual plugins.
// - beta: likely unnecessary, these are probably all "community".
// - favorites: not necessary.

if ( is_singular() || $is_beta || $is_favs || ( is_search() && $wp_query->found_posts === 0 ) ) {
	return;
}

$local_nav_items = array(
	'' => __( 'All', 'wporg-plugins' ),
	'community' => __( 'Community', 'wporg-plugins' ),
	'commercial' => __( 'Commercial', 'wporg-plugins' ),
);

?>

<div class="wporg-filter-bar alignwide">
	<nav class="wporg-filter-bar__navigation" aria-label="<?php esc_html_e( 'Plugin filters', 'wporg-plugins' ); ?>">
		<ul>
		<?php
		foreach ( $local_nav_items as $slug => $label ) {
			$class = '';
			if (
				// URL contains this filter.
				( $slug === ( $_GET['plugin_business_model'] ?? false ) ) ||
				// Set the All item active if no business model is selected.
				( ! $slug && empty( $_GET['plugin_business_model'] ) )
			) {
				$class = 'is-active';
			}

			if ( $slug ) {
				$url = add_query_arg( array( 'plugin_business_model' => $slug ) );
			} else {
				$url = remove_query_arg( 'plugin_business_model' );
			}

			// Reset pagination.
			$url = remove_query_arg( 'paged', $url );
			$url = preg_replace( '!/page/\d+/?!i', '/', $url );

			printf(
				'<li class="page_item"><a class="%1$s" href="%2$s">%3$s</a></li>',
				esc_attr( $class ),
				esc_url( $url ),
				esc_html( $label )
			);
		}
		?>
		</ul>
	</nav>
</div>
