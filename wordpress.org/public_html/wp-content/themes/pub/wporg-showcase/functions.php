<?php

// Add support for adding feed links to head.
add_theme_support( 'automatic-feed-links' );

// Add support for title tags.
add_theme_support( 'title-tag' );

// Disable comments feed.
add_filter( 'feed_links_show_comments_feed', '__return_false' );

// Remove extra feed links from singular queries.
add_action( 'wp_head', function () {
	if ( is_singular() ) {
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}
}, 1 );

function get_site_domain( $rep_slash = true, $echo = true, $rem_trail_slash = false ) {
	global $post;

	$domain = get_post_meta( $post->ID, 'domain', true );

	//remove trailing slash
	if ( $rem_trail_slash && ( strrpos( $domain, '/' ) == ( strlen( $domain ) - 1 ) ) )
		$domain = substr( $domain, 0, strlen( $domain ) - 1 );

	#if ( false !== strpos( $domain, 'http://' ) )
	#	$domain = substr( $domain, 7 );
	$domain = preg_replace( '#^https?://#i', '', $domain );
	if ( $rep_slash )
		$domain = str_replace('/', '%2F', $domain );

	if ( $echo ) echo $domain;
	else return $domain;
}

function site_screenshot_src( $width = '', $echo = true ) {
	global $post;

	$screenshot = get_post_meta($post->ID, 'screenshot', true);
	
	if ( empty( $screenshot ) ) {
		$screenshot = 'https://wordpress.com/mshots/v1/http%3A%2F%2F' . get_site_domain( true, false );
	} elseif ( function_exists( 'jetpack_photon_url' ) ) {
		$screenshot = jetpack_photon_url( $screenshot );
	}

	if ( $width ) {
		$screenshot = add_query_arg( 'w', $width, $screenshot);
	}

	$screenshot = apply_filters( 'wporg_showcase_screenshot_src', $screenshot, $post, $width );

	// force screenshot URLs to be https
	$screenshot = str_replace( 'http://', 'https://', $screenshot );

	if ( $echo ) {
		echo $screenshot;
	} else {
		return $screenshot;
	}
}

// build the whole img tag properly for the screenshot, with srcset support
function site_screenshot_tag( $width = '', $classes='screenshot' ) {
	global $post;

	$screenshot = site_screenshot_src( $width, false );
	$srcset = $screenshot;

	if ( '' != $width ) {
		$screenshot = add_query_arg( 'w', $width, $screenshot);
		$srcset = add_query_arg( 'w', $width*2 , $screenshot);
	}

	// mshot images have a 4/3 ratio
	$height = (int)( $width * (3/4) );

	$img = "<img src='{$screenshot}' srcset='$srcset 2x' width='{$width}' height='{$height}' alt='". the_title_attribute(array('echo'=>false)) . "' class='{$classes}' />";

	echo $img;
}

function wp_flavors() {
	global $post;

	echo '<h2 class="heading">' . __( 'Flavor', 'wporg-showcase' ). '</h2>';
	echo '<ul id="flavors">';

	$flavors = array( 'WordPress.org', 'WordPress.com', 'WordPress.com VIP', 'WordPress MS' );

	foreach ( $flavors as $flavor ) {
		if ( in_category( $flavor ) ) {
			echo '<li class="flavor-used"><img src="' . get_template_directory_uri() . '/images/flavor.png" /> ' . $flavor . '</li>';
		} else {
			echo '<li><img src="' . get_template_directory_uri() . '/images/flavor2.png" /> ' . $flavor . '</li>';
		}
	}

	if ( in_category( 'BuddyPress' ) ) {
		echo '<li class="flavor-used"><img src="' . get_template_directory_uri() . '/images/flavor-bp.png" /> ' . __( 'BuddyPress', 'wporg-showcase' ). '</li>';
	} else {
		echo '<li><img src="' . get_template_directory_uri() . '/images/flavor-bp2.png" /> ' . __( 'BuddyPress', 'wporg-showcase' ). '</li>';
	}

	echo '</ul>';
}

function blockquote_style( $content ) {
	if ( is_single() )
		$content = str_replace( '</blockquote>', '<cite>' . __( 'Source:', 'wporg-showcase' ). ' <a href="http://' . get_site_domain( false, false ) . '">' . get_site_domain( false, false, true ) . '</a></cite><div class="clear"></div></blockquote>', $content );

	return $content;
}
add_filter( 'the_content', 'blockquote_style' );

function the_content_limit( $max_char, $more_link_text = '(more...)', $stripteaser = 0, $more_file = '' ) {
	$content = get_the_content( $more_link_text, $stripteaser, $more_file );
	$content = apply_filters( 'the_content', $content );
	$content = str_replace( ']]>', ']]&gt;', $content );
	$content = strip_tags( $content );

	if ( ! empty( $_GET['p'] ) && strlen( $_GET['p'] ) > 0 ) {
		echo "<p>" . $content . "</p>";
	} else if ( ( strlen( $content ) > $max_char ) && ( $espacio = strpos( $content, " ", $max_char ) ) ) {
		$content = substr( $content, 0, $espacio );
		echo "<p>" . $content . "..." . "</p>";
	} else {
		echo "<p>" . $content . "</p>";
	}
}

function popular_tags ($number = 10) {
	$args = array('number' => $number, 'orderby' => 'count', 'order' => 'DESC');
	$tags = get_terms( 'post_tag', $args );

	$out = '<h2 class="heading">' . __( 'Browse Popular Tags', 'wporg-showcase' ). '</h2>';
	$out .= '<ul class="submenu wpsc-popular-tags">';

	foreach ($tags as $tag) {
		$out .= '<li>';
		if ( !is_tag($tag->slug) ) {
			$out .= '<a href="' . get_term_link($tag, 'post_tag') . '" rel="tag">' . $tag->name . ' <span class="tag-count">' . $tag->count . '</span></a>';
		} else {
			$out .= $tag->name;
		}
		$out .= '</li>';
	}

	$out .= '</ul>';
	echo $out;
}

function breadcrumb() { ?>

	<h2><a href="<?php echo home_url( '/' ); ?>" title="<?php esc_attr_e( 'Showcase', 'wporg-showcase' ); ?>"><?php _e( 'Showcase', 'wporg-showcase' ); ?></a>

		<?php if ( is_search() ) : ?>
			<?php
				/* translators: %s: search query */
				printf( __( '&raquo; Search for: %s', 'wporg-showcase' ), get_search_query() );
			?>
		<?php elseif ( strstr( $_SERVER['REQUEST_URI'], '/showcase/archives' ) ) : ?>
			<?php _e( '&raquo; Archives', 'wporg-showcase' ); ?>
		<?php else : ?>
			<?php if ( is_category() ) : ?>
				<?php _e( '&raquo; Flavor', 'wporg-showcase' ); ?>
			<?php elseif ( is_tag() ) : ?>
				<?php _e( '&raquo; Tag', 'wporg-showcase' ); ?>
			<?php endif; // is_category ?>

			<?php
				/* translators: %s: document title */
				printf( __( '&raquo; %s', 'wporg-showcase' ), wp_get_document_title() );
			?>
		<?php endif; // is_search ?>

	</h2>
<?php
}

function sc_feed_author( $author ) {
	if ( is_feed() )
		$author = _x( 'WordPress Showcase', 'Feed Author', 'wporg-showcase' );

	return $author;
}
add_filter('the_author', 'sc_feed_author');

function tags_with_count( $format = 'list', $before = '', $sep = '', $after = '' ) {
	global $post;

	$posttags = get_the_terms($post->ID, 'post_tag');

	if ( $posttags ) {
		foreach ( $posttags as $tag ) {
			if ( $tag->count > 1 && !is_tag($tag->slug) ) {
				$tag_link = sprintf( '<a href="%s" rel="tag">%s</a>',
					get_term_link( $tag, 'post_tag' ),
					/* translators: 1: tag name, 2: tag count */
					sprintf( __( '%1$s (%2$s)', 'wporg-showcase' ), $tag->name, $tag->count )
				);
			} else {
				$tag_link = $tag->name;
			}

			if ( $format == 'list' ) {
				$tag_link = '<li>' . $tag_link . '</li>';
			}

			$tag_links[] = $tag_link;
		}
	} else {
		return;
	}

	echo $before . join( $sep, $tag_links ) . $after;
}

function extras_feed( $is_comments_feed = false ) {
	// Don't do anything for comment feeds.
	if ( $is_comments_feed ) {
		return;
	}
	load_template( get_template_directory() . '/feed-extras.php' );
}

add_action( 'init', 'extras_feed_init' );
function extras_feed_init() {
	add_feed( 'extras', 'extras_feed' );

	// Ran once
	//global $wp_rewrite;
	//$wp_rewrite->flush_rules();
}

// Enqueue jQuery Cycle
function wpsc_scripts() {
	wp_enqueue_script( 'jquery' ); // explicit enqueue
	wp_enqueue_script( 'jquery-cycle', get_template_directory_uri() . '/js/jquery.cycle2.min.js', array( 'jquery' ) );
	wp_enqueue_script( 'wpsc-scripts', get_template_directory_uri() . '/js/scripts.js', array( 'jquery', 'jquery-cycle' ), '20201001' );
}
add_action('wp_enqueue_scripts', 'wpsc_scripts');

// Limits exceprt length
function custom_excerpt_length( $length ) {
	return 40;
}
add_filter( 'excerpt_length', 'custom_excerpt_length', 999 );

// Use ... in excerpts
add_filter( 'excerpt_more', function() {
	return '...';
} );

/**
 * Filters document title to add context based on what is being viewed.
 *
 * @param array  $parts The document title parts.
 * @return array The document title parts.
 */
function wporg_showcase_document_title( $parts ) {
	// wp_get_document_title() is used by the theme in breadcrumb(), whereby it
	// only really needs the title.
	if ( did_action( 'wp_body_open' ) ) {
		return array( 'title' => $parts['title'] );
	}

	if ( is_front_page() ) {
		// Omit page name from the home page.
		$parts['title']   = $parts['tagline'];
		$parts['tagline'] = __( 'WordPress.org', 'wporg-showcase' );
	} else {
		if ( is_category() ) {
			// Prepend 'Flavor: ' to category document titles.
			/* translators: %s: category name */
			$parts['title'] = sprintf( esc_attr__( 'Flavor: %s', 'wporg-showcase' ), esc_attr( $parts['title'] ) );
		} elseif ( is_tag() ) {
			// Prepend 'Tag: ' to tag document titles.
			/* translators: %s: tag name */
			$parts['title'] = sprintf( esc_attr__( 'Tag: %s', 'wporg-showcase' ), esc_attr( $parts['title'] ) );
		} elseif ( is_single() ) {
			// Apend ' Showcase' to single document titles.
			/* translators: %s: Name of showcased site */
			$parts['title'] = sprintf( esc_attr__( '%s Showcase', 'wporg-showcase' ), esc_attr( $parts['title'] ) );
		}

		$parts['site'] = __( 'WordPress.org', 'wporg-showcase' );
	}

	return $parts;
}
add_filter( 'document_title_parts', 'wporg_showcase_document_title' );

// Change the document title separator.
add_filter( 'document_title_separator', function() {
	return "&#124;";
} );

// Potentially change behavior prior to fetching posts.
add_action( 'pre_get_posts', function( $query ) {
	// Redirect comment feeds to associated post.
	if ( $query->is_comment_feed() ) {
		wp_safe_redirect( home_url( $query->query_vars['name'] ) );
		return;
	}

	// Return a 404 response for paginated front page requests.
	if ( $query->is_main_query() && $query->is_page() && $query->is_paged() ) {
		$query->set_404();
		$query->query_vars['page_id'] = '';
		status_header( 404 );
		nocache_headers();
	}
} );

