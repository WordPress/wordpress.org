<?php
/**
 * Class providing navigation-related functionality.
 *
 * @package handbook
 */

class WPorg_Handbook_Navigation {

	/**
	 * Outputs previous and/or next post navigation links using the
	 * specified menu to inform navigation ordering.
	 *
	 * @param string $menu_name The name of the menu to use for nav ordering.
	 */
	public static function navigate_via_menu( $menu_name ) {
		// Get the items for the specified menu
		if ( ! $menu_items = wp_get_nav_menu_items( $menu_name ) ) {
			return;
		}

		// Get ids for all menu objects
		$menu_ids = wp_list_pluck( $menu_items, 'object_id' );

		// Get current post
		if ( ! $post = get_post() ) {
			return;
		}

		// Index of current post in menu. Return if not in menu.
		$i = array_search( $post->ID, $menu_ids );
		if ( false === $i ) {
			return;
		}

		// Find the previous post (note: preview menu item may not be a post)
		$previous = null;
		for ( $n = $i-1; $n >= 0; $n-- ) {
			if ( isset( $menu_items[ $n ] ) && is_a( $menu_items[ $n ], 'WP_Post' ) ) {
				$previous = $menu_items[ $n ];
				break;
			}
		}

		// Find the next post (note: next menu item may not be a post)
		$next = null;
		$max = count( $menu_items );
		for ( $n = $i+1; $n < $max; $n++ ) {
			if ( isset( $menu_items[ $n ] ) && is_a( $menu_items[ $n ], 'WP_Post' ) ) {
				$next = $menu_items[ $n ];
				break;
			}
		}

		if ( ! $previous && ! $next ) {
			return;
		}
		?>

		<nav class="handbook-navigation" role="navigation">
			<h1 class="screen-reader-text"><?php _e( 'Handbook navigation', 'wporg' ); ?></h1>
			<div class="nav-links">

			<?php
			if ( $previous ) {
				printf( '<a href="%s" rel="previous"><span class="meta-nav">&larr;</span> %s</a>',
					esc_url( $previous->url ),
					esc_html( $previous->title )
				);
			}

			if ( $next ) {
				printf( '<a href="%s" rel="next">%s <span class="meta-nav">&rarr;</span></a>',
					esc_url( $next->url ),
					esc_html( $next->title )
				);
			}
			?>

			</div>
			<!-- .nav-links -->
		</nav><!-- .navigation -->
	<?php
	}

}

