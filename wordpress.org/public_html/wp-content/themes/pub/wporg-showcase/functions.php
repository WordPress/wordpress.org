<?php

add_theme_support( 'automatic-feed-links' );

function get_site_domain( $rep_slash = true, $echo = true, $rem_trail_slash = false ) {
	global $post;

	$domain = get_post_meta( $post->ID, 'domain', true );

	//remove trailing slash
	if ( $rem_trail_slash && ( strrpos( $domain, '/' ) == ( strlen( $domain ) - 1 ) ) )
		$domain = substr( $domain, 0, strlen( $domain ) - 1 );

	if ( false !== strpos( $domain, 'http://' ) )
		$domain = substr( $domain, 7 );
	if ( $rep_slash )
		$domain = str_replace('/', '%2F', $domain );

	if ( $echo ) echo $domain;
	else return $domain;
}

function site_screenshot_src( $width = '', $echo = true ) {
	global $post;

	$screenshot = get_post_meta($post->ID, 'screenshot', true);

	$prefix = is_ssl() ? 'https://' : 'http://s.';
	if ( empty( $screenshot ) ) {
		$screenshot = $prefix.'wordpress.com/mshots/v1/http%3A%2F%2F' . get_site_domain( true, false );
	}

	if ( '' != $width ) {
		$screenshot .= '?w=' . $width;
	}

	$screenshot = apply_filters( 'wporg_showcase_screenshot_src', $screenshot, $post, $width );

	if ( $echo ) {
		echo $screenshot;
	} else {
		return $screenshot;
	}
}

// build the whole img tag properly for the screenshot, with srcset support
function site_screenshot_tag( $width = '', $classes='screenshot' ) {
	global $post;

	$screenshot = get_post_meta($post->ID, 'screenshot', true);
	if ( empty( $screenshot ) ) {
		$screenshot = 'https://wordpress.com/mshots/v1/http%3A%2F%2F' . get_site_domain( true, false );
	}
	$srcset = $screenshot;

	if ( '' != $width ) {
		$screenshot .= '?w=' . $width;
		$srcset .= '?w=' . $width*2;
	}

	// mshot images have a 4/3 ratio
	$height = (int)( $width * (3/4) );

	$img = "<img src='{$screenshot}' srcset='$srcset 2x' width='{$width}' height='{$height}' alt='". the_title_attribute(array('echo'=>false)) . "' class='{$classes}' />";

	echo $img;
}

function wp_flavors() {
	global $post;

	echo '<h4>' . __( 'Flavor', 'wporg-showcase' ). '</h4>';
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

	$out = '<h4>' . __( 'Browse Popular Tags', 'wporg-showcase' ). '</h4>';
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

	<h3><a href="<?php echo home_url( '/' ); ?>" title="<?php esc_attr_e( 'Showcase', 'wporg-showcase' ); ?>"><?php _e( 'Showcase', 'wporg-showcase' ); ?></a>

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

	</h3>
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
	if ( $is_comments_feed ) {
		do_feed_rss2( $is_comments_feed ); // Load default for comments
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
	wp_enqueue_script( 'jquery-cycle', get_template_directory_uri() . '/js/jquery.cycle.min.js', array( 'jquery' ) );
	wp_enqueue_script( 'wpsc-scripts', get_template_directory_uri() . '/js/scripts.js', array( 'jquery', 'jquery-cycle' ) );
}
add_action('wp_enqueue_scripts', 'wpsc_scripts');

// Limits exceprt length
function custom_excerpt_length( $length ) {
	return 40;
}
add_filter( 'excerpt_length', 'custom_excerpt_length', 999 );

// Switch ratings image to stars_showcase
function wpsc_postratings_image( $option ) { return 'stars_showcase'; }
	add_filter( 'pre_option_postratings_image', 'wpsc_postratings_image' );

// Use ... in excerpts
add_filter( 'excerpt_more', create_function( '$more', 'return "...";' ) );

/**
 * Filters document title to add context based on what is being viewed.
 *
 * @param array  $parts The document title parts.
 * @return array The document title parts.
 */
function wporg_showcase_document_title( $parts ) {
	// wp_get_document_title() is used by the theme in breadcrumb(), whereby it
	// only really needs the title.
	if ( did_action( 'wp_head' ) ) {
		return array( 'title' => $parts['title'] );
	}

	if ( is_front_page() ) {
		// Omit page name from the home page.
		$parts['title'] = '';
	} elseif ( is_category() ) {
		// Prepend 'Flavor: ' to category document titles.
		/* translators: %s: category name */
		$parts['title'] = sprintf( __( 'Flavor: %s', 'wporg-showcase' ) . $parts['title'] );
	} elseif ( is_tag() ) {
		// Prepend 'Tag: ' to tag document titles.
		/* translators: %s: tag name */
		$parts['title'] = sprintf( __( 'Tag: %s', 'wporg-showcase' ) . $parts['title'] );
	}

	return $parts;
}
add_filter( 'document_title_parts', 'wporg_showcase_document_title' );

// Change the document title separator.
add_filter( 'document_title_separator', create_function( '$separator', 'return "&#8250;";' ) );
