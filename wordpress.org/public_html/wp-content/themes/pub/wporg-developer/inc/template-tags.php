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
					<div class="comment-content code-example-container">
						<pre class="brush: php; toolbar: false;"><?php echo htmlentities( get_comment_text() ); ?></pre>
					</div>
					<!-- .comment-content -->

					<footer class="comment-meta">
						<div class="comment-author vcard">
							<span class="comment-author-attribution">
							<?php if ( 0 != $args['avatar_size'] ) {
								echo get_avatar( $comment, $args['avatar_size'] );
							} ?>

							<?php
								// This would all be moot if core passed the $comment context for 'get_comment_author_link' filter
								if ( $comment->user_id ) {
									$commenter = get_user_by( 'id', $comment->user_id );
									$url = 'https://profiles.wordpress.org/' . esc_attr( $commenter->user_nicename ) . '/';
									$author = get_the_author_meta( 'display_name', $comment->user_id );
									$comment_author_link = "<a href='$url' rel='external nofollow' class='url'>$author</a>";
								} else {
									$comment_author_link = '';
								}
								printf( __( 'Contributed by %s', 'wporg' ), sprintf( '<cite class="fn">%s</cite>', $comment_author_link ) );
							?>

							</span>
							&mdash;
							Added on <a href="<?php echo esc_url( get_comment_link( $comment->comment_ID ) ); ?>">
								<time datetime="<?php comment_time( 'c' ); ?>">
									<?php printf( _x( '%1$s at %2$s', '1: date, 2: time', 'wporg' ), get_comment_date(), get_comment_time() ); ?>
								</time>
							</a>
							<?php edit_comment_link( __( 'Edit', 'wporg' ), '<span class="edit-link">', '</span>' ); ?>
						</div>
						<!-- .comment-metadata -->

						<?php if ( '0' == $comment->comment_approved ) : ?>
							<p class="comment-awaiting-moderation"><?php _e( 'Your example is awaiting moderation.', 'wporg' ); ?></p>
						<?php endif; ?>
					</footer>
					<!-- .comment-meta -->

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
	 * Get current version of the parsed WP code.
	 *
	 * Prefers the 'wp_parser_imported_wp_version' option value set by more
	 * recent versions of the parser. Failing that, it checks the
	 * WP_CORE_LATEST_RELEASE constant (set on WP.org) though this is not
	 * guaranteed to be the latest parsed version. Failing that, it uses
	 * the WP version of the site, unless it isn't a release version, in
	 * which case a hardcoded value is assumed.
	 *
	 * @return string
	 */
	function get_current_version() {
		global $wp_version;

		// Preference for the value saved as an option.
		$current_version = get_option( 'wp_parser_imported_wp_version' );

		// Otherwise, assume the value stored in a constant (which is set on WP.org), if defined.
		if ( empty( $current_version ) && defined( 'WP_CORE_LATEST_RELEASE' ) && WP_CORE_LATEST_RELEASE ) {
			$current_version = WP_CORE_LATEST_RELEASE;
		}

		// Otherwise, use the version of the running WP instance.
		if ( empty( $current_version ) ) {
			$current_version = $wp_version;

			// However, if the running WP instance appears to not be a release
			// version, assume a hardcoded version that is at least valid.
			if ( false !== strpos( $current_version, '-' ) ) {
				$current_version = '3.9.1';
			}
		}

		return $current_version;
	}

	/**
	 * Get current (latest) version of the parsed WP code as a wp-parser-since
	 * term object.
	 *
	 * By default returns the major version (X.Y.0) term object because minor
	 * releases rarely add enough, if any, new things to feature.
	 *
	 * @param  boolean $ignore_minor Use the major release version X.Y.0 instead of the actual version X.Y.Z?
	 * @return object
	 */
	function get_current_version_term( $ignore_minor = true ) {
		$current_version = get_current_version();

		if ( $ignore_minor ) {
			$version_parts = explode( '.', $current_version, 3 );
			if ( count( $version_parts ) == 2 ) {
				$version_parts[] = '0';
			} else {
				$version_parts[2] = '0';
			}
			$current_version = implode( '.', $version_parts );
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
			if ( false !== strpos( $hook_type, 'action' ) ) {
				$hook_type = ( 'action_reference' === $hook_type ) ? 'do_action_ref_array' : 'do_action';
			} else {
				$hook_type = ( 'filter_reference' === $hook_type ) ? 'apply_filters_ref_array' : 'apply_filters';
			}

			$delimiter = false !== strpos( $signature, '$' ) ? '"' : "'";
			$signature = $delimiter . $signature . $delimiter;
			$signature = '<span class="hook-func">' . $hook_type . '</span> ( ' . $signature;
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
					if ( strtolower( substr( $tag['content'], 0, 9 ) ) == "optional." ) {
						$params[ $tag['variable'] ]['required'] = 'Optional';
						$params[ $tag['variable'] ]['content'] = substr( $tag['content'], 10 );
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
	 * Retrieve URL to source file archive.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	function get_source_file_archive_link( $name = null ) {

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
	 * Retrieve the URL to the actual source file and line.
	 *
	 * @param null $post_id     Post ID.
	 * @param bool $line_number Whether to append the line number to the URL.
	 *                          Default true.
	 * @return string Source file URL with or without line number.
	 */
	function get_source_file_link( $post_id = null, $line_number = true ) {

		$post_id = empty( $post_id ) ? get_the_ID() : $post_id;
		$url     = '';

		// Source file.
		$source_file = get_source_file( $post_id );
		if ( ! empty( $source_file ) ) {
			$url = 'https://core.trac.wordpress.org/browser/tags/' . get_current_version() . '/src/' . $source_file;
			// Line number.
			if ( $line_number = get_post_meta( get_the_ID(), '_wp-parser_line_num', true ) ) {
				$url .= "#L{$line_number}";
			}
		}

		return esc_url( $url );
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

	/**
	 * Does the post type have source code?
	 *
	 * @param  string  Optional. The post type name. If blank, assumes current post type.
	 *
	 * @return boolean
	 */
	function post_type_has_source_code( $post_type = null ) {
		$post_type                   = $post_type ? $post_type : get_post_type();
		$post_types_with_source_code = array( 'wp-parser-method', 'wp-parser-function' );

		return in_array( $post_type, $post_types_with_source_code );
	}

	/**
	 * Retrieve the root directory of the parsed WP code.
	 *
	 * If the option 'wp_parser_root_import_dir' (as set by the parser) is not
	 * set, then assume ABSPATH.
	 *
	 * @return string
	 */
	function get_source_code_root_dir() {
		$root_dir = get_option( 'wp_parser_root_import_dir' );

		return $root_dir ? trailingslashit( $root_dir ) : ABSPATH;
	}

	/**
	 * Retrieve source code for a function or method.
	 *
	 * @param int  $post_id     Optional. The post ID.
	 * @param bool $force_parse Optional. Ignore potential value in post meta and reparse source file for source code?
	 *
	 * @return string The source code.
	 */
	function get_source_code( $post_id = null, $force_parse = false ) {

		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}

		// Get the source code stored in post meta.
		$meta_key = '_wp-parser_source_code';
		if ( ! $force_parse && $source_code = get_post_meta( $post_id, $meta_key, true ) ) {
			return $source_code;
		}

		/* Source code hasn't been stored in post meta, so parse source file to get it. */

		// Get the name of the source file.
		$source_file = get_source_file( $post_id );

		// Get the start and end lines.
		$start_line = intval( get_post_meta( $post_id, '_wp-parser_line_num', true ) ) - 1;
		$end_line   = intval( get_post_meta( $post_id, '_wp-parser_end_line_num', true ) );

		// Sanity check to ensure proper conditions exist for parsing
		if ( ! $source_file || ! $start_line || ! $end_line || ( $start_line > $end_line ) ) {
			return '';
		}

		// Find just the relevant source code
		$source_code = '';
		$handle = @fopen( get_source_code_root_dir() . $source_file, 'r' );
		if ( $handle ) {
			$line = -1;
			while ( ! feof( $handle ) ) {
				$line++;
				$source_line = fgets( $handle );
				if ( $line > $end_line ) {
					break;
				}
				if ( $line < $start_line ) {
					continue;
				}
				$source_code .= $source_line;
			}
			fclose( $handle );
		}

		update_post_meta( $post_id, $meta_key, addslashes( $source_code ) );

		return $source_code;
	}

}