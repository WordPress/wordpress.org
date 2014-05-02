<?php

namespace {

	/**
	 * Custom template tags for this theme.
	 *
	 * Eventually, some of the functionality here could be replaced by core features.
	 *
	 * @package wporg-developer
	 */

	if ( ! function_exists( 'wporg_developer_paging_nav' ) ) :
		/**
		 * Display navigation to next/previous set of posts when applicable.
		 *
		 * @return void
		 */
		function wporg_developer_paging_nav() {
			// Don't print empty markup if there's only one page.
			if ( $GLOBALS['wp_query']->max_num_pages < 2 ) {
				return;
			}
			?>
			<nav class="navigation paging-navigation" role="navigation">
				<h1 class="screen-reader-text"><?php _e( 'Posts navigation', 'wporg' ); ?></h1>

				<div class="nav-links">

					<?php if ( get_next_posts_link() ) : ?>
						<div class="nav-previous"><?php next_posts_link( __( '<span class="meta-nav">&larr;</span> Older posts', 'wporg' ) ); ?></div>
					<?php endif; ?>

					<?php if ( get_previous_posts_link() ) : ?>
						<div class="nav-next"><?php previous_posts_link( __( 'Newer posts <span class="meta-nav">&rarr;</span>', 'wporg' ) ); ?></div>
					<?php endif; ?>

				</div>
				<!-- .nav-links -->
			</nav><!-- .navigation -->
		<?php
		}
	endif;

	if ( ! function_exists( 'wporg_developer_post_nav' ) ) :
		/**
		 * Display navigation to next/previous post when applicable.
		 *
		 * @return void
		 */
		function wporg_developer_post_nav() {
			// Don't print empty markup if there's nowhere to navigate.
			$previous = ( is_attachment() ) ? get_post( get_post()->post_parent ) : get_adjacent_post( false, '', true );
			$next     = get_adjacent_post( false, '', false );

			if ( ! $next && ! $previous ) {
				return;
			}
			?>
			<nav class="navigation post-navigation" role="navigation">
				<h1 class="screen-reader-text"><?php _e( 'Post navigation', 'wporg' ); ?></h1>

				<div class="nav-links">

					<?php previous_post_link( '%link', _x( '<span class="meta-nav">&larr;</span> %title', 'Previous post link', 'wporg' ) ); ?>
					<?php next_post_link( '%link', _x( '%title <span class="meta-nav">&rarr;</span>', 'Next post link', 'wporg' ) ); ?>

				</div>
				<!-- .nav-links -->
			</nav><!-- .navigation -->
		<?php
		}
	endif;

	if ( ! function_exists( 'wporg_developer_comment' ) ) :
		/**
		 * Template for comments and pingbacks.
		 *
		 * Used as a callback by wp_list_comments() for displaying the comments.
		 */
		function wporg_developer_comment( $comment, $args, $depth ) {
			$GLOBALS['comment'] = $comment;

			if ( 'pingback' == $comment->comment_type || 'trackback' == $comment->comment_type ) : ?>

				<li id="comment-<?php comment_ID(); ?>" <?php comment_class(); ?>>
				<div class="comment-body">
					<?php _e( 'Pingback:', 'wporg' ); ?> <?php comment_author_link(); ?> <?php edit_comment_link( __( 'Edit', 'wporg' ), '<span class="edit-link">', '</span>' ); ?>
				</div>

			<?php else : ?>

				<li id="comment-<?php comment_ID(); ?>" <?php comment_class( empty( $args['has_children'] ) ? '' : 'parent' ); ?>>
				<article id="div-comment-<?php comment_ID(); ?>" class="comment-body">
					<footer class="comment-meta">
						<div class="comment-author vcard">
							<?php if ( 0 != $args['avatar_size'] ) {
								echo get_avatar( $comment, $args['avatar_size'] );
							} ?>
							<?php printf( __( '%s <span class="says">says:</span>', 'wporg' ), sprintf( '<cite class="fn">%s</cite>', get_comment_author_link() ) ); ?>
						</div>
						<!-- .comment-author -->

						<div class="comment-metadata">
							<a href="<?php echo esc_url( get_comment_link( $comment->comment_ID ) ); ?>">
								<time datetime="<?php comment_time( 'c' ); ?>">
									<?php printf( _x( '%1$s at %2$s', '1: date, 2: time', 'wporg' ), get_comment_date(), get_comment_time() ); ?>
								</time>
							</a>
							<?php edit_comment_link( __( 'Edit', 'wporg' ), '<span class="edit-link">', '</span>' ); ?>
						</div>
						<!-- .comment-metadata -->

						<?php if ( '0' == $comment->comment_approved ) : ?>
							<p class="comment-awaiting-moderation"><?php _e( 'Your comment is awaiting moderation.', 'wporg' ); ?></p>
						<?php endif; ?>
					</footer>
					<!-- .comment-meta -->

					<div class="comment-content">
						<?php comment_text(); ?>
					</div>
					<!-- .comment-content -->

					<?php
					comment_reply_link( array_merge( $args, array(
						'add_below' => 'div-comment',
						'depth'     => $depth,
						'max_depth' => $args['max_depth'],
						'before'    => '<div class="reply">',
						'after'     => '</div>',
					) ) );
					?>
				</article><!-- .comment-body -->

			<?php
			endif;
		}
	endif; // ends check for wporg_developer_comment()

	if ( ! function_exists( 'wporg_developer_posted_on' ) ) :
		/**
		 * Prints HTML with meta information for the current post-date/time and author.
		 */
		function wporg_developer_posted_on() {
			$time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time>';
			if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) {
				$time_string .= '<time class="updated" datetime="%3$s">%4$s</time>';
			}

			$time_string = sprintf( $time_string,
				esc_attr( get_the_date( 'c' ) ),
				esc_html( get_the_date() ),
				esc_attr( get_the_modified_date( 'c' ) ),
				esc_html( get_the_modified_date() )
			);

			printf( __( '<span class="posted-on">Posted on %1$s</span><span class="byline"> by %2$s</span>', 'wporg' ),
				sprintf( '<a href="%1$s" rel="bookmark">%2$s</a>',
					esc_url( get_permalink() ),
					$time_string
				),
				sprintf( '<span class="author vcard"><a class="url fn n" href="%1$s">%2$s</a></span>',
					esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ),
					esc_html( get_the_author() )
				)
			);
		}
	endif;

	/**
	 * Returns true if a blog has more than 1 category.
	 */
	function wporg_developer_categorized_blog() {
		if ( false === ( $all_the_cool_cats = get_transient( 'all_the_cool_cats' ) ) ) {
			// Create an array of all the categories that are attached to posts.
			$all_the_cool_cats = get_categories( array(
				'hide_empty' => 1,
			) );

			// Count the number of categories that are attached to the posts.
			$all_the_cool_cats = count( $all_the_cool_cats );

			set_transient( 'all_the_cool_cats', $all_the_cool_cats );
		}

		if ( '1' != $all_the_cool_cats ) {
			// This blog has more than 1 category so wporg_developer_categorized_blog should return true.
			return true;
		} else {
			// This blog has only 1 category so wporg_developer_categorized_blog should return false.
			return false;
		}
	}

	/**
	 * Flush out the transients used in wporg_developer_categorized_blog.
	 */
	function wporg_developer_category_transient_flusher() {
		// Like, beat it. Dig?
		delete_transient( 'all_the_cool_cats' );
	}

	add_action( 'edit_category', 'wporg_developer_category_transient_flusher' );
	add_action( 'save_post', 'wporg_developer_category_transient_flusher' );
}

namespace DevHub {

	function wp_doc_comment( $comment, $args, $depth ) {
		?>
	<li <?php comment_class(); ?> id="li-comment-<?php comment_ID(); ?>">
		<article id="comment-<?php comment_ID(); ?>" class="comment">

			<?php if ( $comment->comment_approved == '0' ) : ?>
				<em class="comment-awaiting-moderation"><?php _e( 'Your example is awaiting moderation.', 'wporg' ); ?></em>
				<br />
			<?php endif; ?>

			<pre class="example-content"><?php echo htmlentities( get_comment_text() ); ?></pre>

			<footer class="comment-meta">
				<div class="comment-author vcard">
					<?php
					echo get_avatar( $comment );

					/* translators: 1: comment author, 2: date and time */
					printf( __( 'Contributed by %1$s on %2$s', 'wporg' ),
						sprintf( '<span class="fn">%s</span>', get_comment_author_link() ),
						sprintf( '<a href="%1$s"><time datetime="%2$s">%3$s</time></a>',
							esc_url( get_comment_link( $comment->comment_ID ) ),
							get_comment_time( 'c' ),
							/* translators: 1: date, 2: time */
							sprintf( __( '%1$s at %2$s', 'wporg' ), get_comment_date(), get_comment_time() )
						)
					);
					?>

					<?php edit_comment_link( __( 'Edit', 'wporg' ), '<span class="edit-link">', '</span>' ); ?>
				</div>
				<!-- .comment-author .vcard -->

			</footer>

		</article>
		<!-- #comment-## -->

	<?php
	}

	/**
	 * Get current (latest) since version
	 *
	 * @return object
	 */
	function get_current_version() {

		$current_version = defined( 'WP_CORE_LATEST_RELEASE' ) ? WP_CORE_LATEST_RELEASE : '3.9';
		if ( substr_count( $current_version, '.' ) ) {
			$current_version .= '.0';
		}

		$version = get_terms( 'wp-parser-since', array(
			'number' => '1',
			'order'  => 'DESC',
			'slug'   => $current_version,
		) );

		return $version[0];
	}

	/**
	 * Get site section from url path
	 *
	 * @return string
	 */
	function get_site_section_title() {
		$parts = explode( '/', $_SERVER['REQUEST_URI'] );
		switch ( $parts[1] ) {
			case 'reference':
				return 'Code Reference';
			case 'theme-handbook':
				return 'Theme Handbook';
			case 'plugin-handbook':
				return 'Plugin Handbook';
			default:
				return 'Developer Resources';
		}
	}

	/**
	 * Get post type name
	 *
	 * @param string $post_type
	 * @param bool $plural
	 *
	 * @return string
	 */
	function get_post_type_name( $post_type = null, $plural = false ) {
		if ( empty( $post_type ) ) {
			$post_type = get_post_type();
		}

		$name = substr( $post_type, 6 );

		if ( $plural ) {
			$name .= ( 'class' == $name ) ? 'es' : 's';
		}
		return $name;
	}

	/**
	 * Retrieve function name and arguments as signature string
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	function get_signature( $post_id = null ) {

		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}

		$args         = get_post_meta( $post_id, '_wp-parser_args', true );
		$tags 		  = get_post_meta( $post_id, '_wp-parser_tags', true );
		$signature    = get_the_title( $post_id );
		$params       = get_params();
		$args_strings = array();
		$types        = array();

		if ( $tags ) {
			foreach ( $tags as $tag ) {
				if ( 'param' == $tag['name'] ) {
					$types[ $tag['variable'] ] = implode( '|', $tag['types'] );
				}
			}
		}

		// Decorate and return hook arguments.
		if ( 'wp-parser-hook' === get_post_type( $post_id ) ) {
			$hook_args = array();
			foreach ( $types as $arg => $type ) {
				$hook_args[] = ' <nobr><span class="arg-type">' . esc_html( $type ) . '</span> <span class="arg-name">' . esc_html( $arg ) . '</span></nobr>';
			}
			$hook_type = get_post_meta( $post_id, '_wp-parser_hook_type', true );
			$delimiter = false !== strpos( $signature, '$' ) ? '"' : "'";
			$signature = $delimiter . $signature . $delimiter;
			$signature = '<span class="hook-func">' . ( $hook_type === 'action' ? 'do_action' : 'apply_filters' ) . '</span> ( ' . $signature;
			if ( $hook_args ) {
				$signature .= ', ';
				$signature .= implode( ', ', $hook_args );
			}
			$signature .= ' )';
			return $signature;
		}

		// Decorate and return function/class arguments.
		if ( $args ) {
			foreach ( $args as $arg ) {
				$arg_string = '';
				if ( ! empty( $arg['name'] ) && ! empty( $types[ $arg['name'] ] ) ) {
					$arg_string .= ' <span class="arg-type">' . $types[ $arg['name'] ] . '</span>';
				}

				if ( ! empty( $arg['name'] ) ) {
					$arg_string .= '&nbsp;<span class="arg-name">' . $arg['name'] . '</span>&nbsp;';
				}

				if ( is_array( $arg ) && array_key_exists( 'default', $arg ) ) {

					if ( is_null( $arg['default'] ) ) {
						$arg['default'] = 'null';
					}

					$arg_string .= '=&nbsp;<span class="arg-default">' . $arg['default'] . "</span>";
				}

				$args_strings[] = $arg_string;
			}
		}

		$signature .= ' (';
		if ( $args = implode( ', ', $args_strings ) ) {
			$signature .= $args . ' ';
		}
		$signature .= ')';

		return wp_kses_post( $signature );
	}

	/**
	 * Retrieve parameters as an array
	 *
	 * @param int $post_id
	 *
	 * @return array
	 */
	function get_params( $post_id = null ) {

		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}
		$params = '';
		$args = get_post_meta( $post_id, '_wp-parser_args', true );
		$tags = get_post_meta( $post_id, '_wp-parser_tags', true );

		if ( $tags ) {
			foreach ( $tags as $tag ) {
				if ( 'param' == $tag['name'] ) {
					$params[ $tag['variable'] ] = $tag;
					foreach ( $tag['types'] as $i => $v ) {
						$types[ $i ] = "<span class=\"{$v}\">{$v}</span>";
					}
					$params[ $tag['variable'] ]['types'] = implode( '|', $types );
					if ( strtolower( substr( $tag['content'], 0, 8 ) ) == "optional." ) {
						$params[ $tag['variable'] ]['required'] = 'Optional';
						$params[ $tag['variable'] ]['content'] = substr( $tag['content'], 9 );
					} else {
						$params[ $tag['variable'] ]['required'] = 'Required';
					}
				}
			}
		}

		if ( $args ) {
			foreach ( $args as $arg ) {
				if ( ! empty( $arg['name'] ) && ! empty( $params[ $arg['name'] ] ) ) {
					$params[ $arg['name'] ]['default'] = $arg['default'];
				}

			}
		}

		return $params;
	}

	/**
	 * Retrieve arguments as an array
	 *
	 * @param int $post_id
	 *
	 * @return array
	 */
	function get_arguments( $post_id = null ) {

		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}
		$arguments = array();
		$args = get_post_meta( $post_id, '_wp-parser_args', true );

		if ( $args ) {
			foreach ( $args as $arg ) {
				if ( ! empty( $arg['type'] ) ) {
					$arguments[ $arg['name'] ] = $arg['type'];
				}
			}
		}

		return $arguments;
	}

	/**
	 * Retrieve return type and description if available
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	function get_return( $post_id = null ) {

		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}

		$tags   = get_post_meta( $post_id, '_wp-parser_tags', true );
		$return = wp_filter_object_list( $tags, array( 'name' => 'return' ) );

		if ( empty( $return ) ) {
			$description = '';
			$type        = 'void';
		} else {
			$return      = array_shift( $return );
			$description = empty( $return['content'] ) ? '' : esc_html( $return['content'] );
			$type        = empty( $return['types'] ) ? '' : esc_html( implode( '|', $return['types'] ) );
		}

		return "<span class='return-type'>{$type}</span> $description";
	}

	/**
	 * Retrieve URL to since version archive
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	function get_since_link( $name = null ) {

		$since_object = get_term_by( 'name', empty( $name ) ? get_since() : $name, 'wp-parser-since' );

		return empty( $since_object ) ? '' : esc_url( get_term_link( $since_object ) );
	}

	/**
	 * Retrieve name of since version
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	function get_since( $post_id = null ) {

		$since_object = wp_get_post_terms( empty( $post_id ) ? get_the_ID() : $post_id, 'wp-parser-since', array( 'fields' => 'names' ) );

		return empty( $since_object ) ? '' : esc_html( $since_object[0] );
	}

	/**
	 * Retrieve URL to source file archive
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	function get_source_file_link( $name = null ) {

		$source_file_object = get_term_by( 'name', empty( $name ) ? get_source_file() : $name, 'wp-parser-source-file' );

		return empty( $source_file_object ) ? '' : esc_url( get_term_link( $source_file_object ) );
	}

	/**
	 * Retrieve name of source file
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	function get_source_file( $post_id = null ) {

		$source_file_object = wp_get_post_terms( empty( $post_id ) ? get_the_ID() : $post_id, 'wp-parser-source-file', array( 'fields' => 'names' ) );

		return empty( $source_file_object ) ? '' : esc_html( $source_file_object[0] );
	}

	/**
	 * Compare two objects by name for sorting.
	 *
	 * @param WP_Post $a Post A
	 * @param WP_Post $b Post B
	 *
	 * @return int
	 */
	function compare_objects_by_name( $a, $b ) {
		return strcmp( $a->post_name, $b->post_name );
	}

}