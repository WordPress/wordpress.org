<?php
/**
 * Class providing navigation links.
 *
 * @package handbook
 */

class WPorg_Handbook_Navigation {

	/**
	 * Is the handbook table of contents produced by the handbook pages widget?
	 *
	 * @access private
	 * @var bool
	 */
	private static $using_pages_widget = false;

	/**
	 * Initializes handbook navigation.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ), 100 );
	}

	/**
	 * Fires on 'init' action.
	 */
	public static function do_init() {
		// Note if the WPorg_Handbook_Pages_Widget widget is in use.
		if ( is_active_widget( false, false, WPorg_Handbook_Pages_Widget::get_widget_id_base(), true ) ) {
			self::$using_pages_widget = true;
		}
	}

	/**
	 * Outputs previous and next page navigation links (and wrapper markup).
	 *
	 * This function determines the method used for the handbook table of contents
	 * and outputs page navigation links accordingly.
	 *
	 * Recognizes use of either the WPorg_Handbook_Pages_Widget (as provided as
	 * part of this plugin) or a custom menu widget (by default, associated with
	 * the custom menu having the name "Table of Contents"). If both are present,
	 * the WPorg_Handbook_Pages_Widget is used.
	 *
	 * @param string $menu_name Optional. The name of the menu for the table of
	 *                          contents. Only applies if the handbook pages
	 *                          widget is not in use. Default 'Table of Contents'.
	 */
	public static function show_nav_links( $menu_name = 'Table of Contents' ) {
		if ( self::$using_pages_widget ) {
			self::navigate_via_handbook_pages_widget();
		} else {
			self::navigate_via_menu( $menu_name );
		}
	}

	/**
	 * Outputs previous and/or next page navigation links according to the active
	 * handbook widget settings.
	 *
	 * @access protected
	 */
	protected static function navigate_via_handbook_pages_widget() {
		// Get current post.
		if ( ! $post = get_post() ) {
			return;
		}

		// Bail unless a handbook page.
		if ( ! in_array( get_post_type(), WPorg_Handbook_Init::get_post_types() ) ) {
			return;
		}

		// Get settings for widget.
		$sort_column = 'menu_order';
		$exclude     = '';
		$widget_options = get_option( 'widget_' . WPorg_Handbook_Pages_Widget::get_widget_id_base() );
		foreach ( (array) $widget_options as $widget ) {
			if ( $widget && is_array( $widget ) ) {
				if ( ! empty( $widget['sortby']  ) ) { $sort_column = $widget['sortby'];  }
				if ( ! empty( $widget['exclude'] ) ) { $exclude     = $widget['exclude']; }
				break;
			}
		}

		// Cache key format is pages:{post type}:{sort column}(:{excluded})?.
		$cache_key = 'pages:' . get_post_type() . ':' . $sort_column;
		if ( $exclude ) {
			$cache_key .= ':' . str_replace( ' ', '', $exclude );
		}
		$cache_group = 'wporg_handbook:' . get_current_blog_id();

		// Get the hierarchically and menu_order ordered list of handbook pages.
		$handbook_pages = wp_cache_get( $cache_key, $cache_group );
		if ( false === $handbook_pages ) {
			if ( 'menu_order' === $sort_column ) {
				$sort_column = 'menu_order, post_title';
			}

			$handbook_pages = get_pages( array(
				'echo'        => 0,
				'exclude'     => $exclude,
				'post_type'   => get_post_type(),
				'sort_column' => $sort_column,
				'sort_order'  => 'asc',
			) );

			if ( $handbook_pages ) {
				wp_cache_add( $cache_key, $handbook_pages, $cache_group, 2 * MINUTE_IN_SECONDS );
			}
		}

		// Determine the previous and next handbook pages.
		if ( $handbook_pages ) {
			$current_page  = wp_list_filter( $handbook_pages, array( 'ID' => get_the_ID() ) );
			$current_index = array_keys( $current_page );

			if ( $current_index ) {
				$current_index = $current_index[0];
				$current_page  = $current_page[ $current_index ];

				$prev = $next = false;

				if ( array_key_exists( $current_index - 1, $handbook_pages ) ) {
					$prev = $handbook_pages[ $current_index - 1 ];
					$prev = (object) array(
						'url'   => get_the_permalink( $prev->ID ),
						'title' => get_the_title( $prev->ID )
					);
				}

				if ( array_key_exists( $current_index + 1, $handbook_pages ) ) {
					$next = $handbook_pages[ $current_index + 1 ];
					$next = (object) array(
						'url'   => get_the_permalink( $next->ID ),
						'title' => get_the_title( $next->ID )
					);
				}

				self::output_navigation( $prev, $next );
			}
		}
	}

	/**
	 * Outputs previous and/or next page navigation links using the
	 * specified menu to inform navigation ordering.
	 *
	 * @access protected
	 *
	 * @param string $menu_name The name of the menu to use for nav ordering.
	 */
	protected static function navigate_via_menu( $menu_name ) {
		// Get current post.
		if ( ! $post = get_post() ) {
			return;
		}

		// Get the items for the specified menu.
		if ( ! $menu_items = wp_get_nav_menu_items( $menu_name ) ) {
			return;
		}

		// Get ids for all menu objects.
		$menu_ids = wp_list_pluck( $menu_items, 'object_id' );

		// Index of current post in menu. Return if not in menu.
		$i = array_search( $post->ID, $menu_ids );
		if ( false === $i ) {
			return;
		}

		// Find the previous post (note: preview menu item may not be a post).
		$previous = null;
		for ( $n = $i-1; $n >= 0; $n-- ) {
			if ( isset( $menu_items[ $n ] ) && is_a( $menu_items[ $n ], 'WP_Post' ) ) {
				$previous = $menu_items[ $n ];
				break;
			}
		}

		// Find the next post (note: next menu item may not be a post).
		$next = null;
		$max = count( $menu_items );
		for ( $n = $i+1; $n < $max; $n++ ) {
			if ( isset( $menu_items[ $n ] ) && is_a( $menu_items[ $n ], 'WP_Post' ) ) {
				$next = $menu_items[ $n ];
				break;
			}
		}

		self::output_navigation( $previous, $next );
	}

	/**
	 * Outputs navigation markup for the specified previous and/or next pages.
	 *
	 * @access protected
	 *
	 * @param object $previous Object with the 'url' and 'title' attribute for the
	 *                         previous page.
	 * @param object $next     Object with the 'url' and 'title' attribute for the
	 *                         next page.
	 */
	protected static function output_navigation( $previous, $next ) {
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

WPorg_Handbook_Navigation::init();

