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

	if ( ! function_exists( 'wporg_developer_user_note' ) ) :
		/**
		 * Template for user contributed notes.
		 *
		 * Used as a callback by wp_list_comments() for displaying the notes.
		 */
		function wporg_developer_user_note( $comment, $args, $depth ) {
			$GLOBALS['comment'] = $comment;

			if ( 'pingback' == $comment->comment_type || 'trackback' == $comment->comment_type ) : ?>

				<li id="comment-<?php comment_ID(); ?>" <?php comment_class(); ?>>
				<div class="comment-body">
					<?php _e( 'Pingback:', 'wporg' ); ?> <?php comment_author_link(); ?> <?php edit_comment_link( __( 'Edit', 'wporg' ), '<span class="edit-link">', '</span>' ); ?>
				</div>

			<?php else : ?>

				<li id="comment-<?php comment_ID(); ?>" <?php comment_class( empty( $args['has_children'] ) ? '' : 'parent' ); ?>>
				<article id="div-comment-<?php comment_ID(); ?>" class="comment-body">
					<div class="comment-content">
						<?php comment_text(); ?>
					</div>
					<!-- .comment-content -->

					<footer class="comment-meta">
						<?php DevHub_User_Contributed_Notes_Voting::show_voting(); ?>
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
							<?php edit_comment_link( __( 'Edit', 'wporg' ), '<span class="edit-link">&mdash; ', '</span>' ); ?>
						</div>
						<!-- .comment-metadata -->

						<?php if ( '0' == $comment->comment_approved ) : ?>
							<p class="comment-awaiting-moderation"> &mdash; <?php _e( 'Your note is awaiting moderation.', 'wporg' ); ?></p>
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
	endif; // ends check for wporg_developer_user_note()

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
				<em class="comment-awaiting-moderation"><?php _e( 'Your note is awaiting moderation.', 'wporg' ); ?></em>
				<br />
			<?php endif; ?>

			<pre class="user-note-content"><?php echo htmlentities( get_comment_text() ); ?></pre>

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
	 * For development versions, the development suffix ("-beta1", "-RC1") gets removed.
	 *
	 * @param  boolean $ignore_minor Use the major release version X.Y.0 instead of the actual version X.Y.Z?
	 * @return object
	 */
	function get_current_version_term( $ignore_minor = true ) {
		$current_version = get_current_version();

		// Remove dev version suffix (e.g. 4.1-RC1 => 4.1)
		if ( false !== strpos( $current_version, '-' ) ) {
			list( $current_version, $dev_version ) = explode( '-', $current_version, 2 );
		}

		if ( $ignore_minor ) {
			$version_parts = explode( '.', $current_version, 3 );
			if ( count( $version_parts ) == 2 ) {
				$version_parts[] = '0';
			} else {
				$version_parts[2] = '0';
			}
			$current_version = implode( '-', $version_parts );
		}

		$version = get_terms( 'wp-parser-since', array(
			'number' => '1',
			'order'  => 'DESC',
			'slug'   => $current_version,
		) );

		return $version[0];
	}

	/**
	 * Get an array of all parsed post types.
	 *
	 * @return array
	 */
	function get_parsed_post_types() {
		return array(
			'wp-parser-class',
			'wp-parser-function',
			'wp-parser-hook',
			'wp-parser-method',
		);
	}

	/**
	 * Checks if given post type is one of the parsed post types.
	 *
	 * @param  null|string Optional. The post type. Default null.
	 * @return bool True if post has a parsed post type
	 */
	function is_parsed_post_type( $post_type = null ) {
		$post_type = $post_type ? $post_type : get_post_type();

		return in_array( $post_type, get_parsed_post_types() );
	}

	/**
	 * Get the specific type of hook.
	 *
	 * @param int|WP_Post|null $post Optional. Post ID or post object. Default is global $post.
	 * @return string          Either 'action', 'filter', or an empty string if not a hook post type.
	 */
	function get_hook_type( $post = null ) {
		$hook = '';

		if ( 'wp-parser-hook' === get_post_type( $post ) ) {
			$hook = get_post_meta( get_post_field( 'ID', $post ), '_wp-parser_hook_type', true );
		}

		return $hook;
	}

	/**
	 * Get site section root URL based on URL path.
	 *
	 * @return string
	 */
	function get_site_section_url() {
		$parts = explode( '/', $_SERVER['REQUEST_URI'] );
		switch ( $parts[1] ) {
			case 'reference':
			case 'plugins':
			case 'themes':
				return home_url( '/' . $parts[1] . '/' );
			default:
				return home_url( '/' );
		}
	}

	/**
	 * Get site section title based on URL path.
	 *
	 * @return string
	 */
	function get_site_section_title() {
		$parts = explode( '/', $_SERVER['REQUEST_URI'] );
		switch ( $parts[1] ) {
			case 'resources':
			case 'resource':
				return sprintf( __( 'Developer Resources: %s', 'wporg' ), get_the_title() );
			case 'reference':
				return __( 'Code Reference', 'wporg' );
			case 'plugins':
				return __( 'Plugin Handbook', 'wporg' );
			case 'themes':
				return __( 'Theme Handbook', 'wporg' );
			default:
				return __( 'Developer Resources', 'wporg' );
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
				if ( is_array( $tag ) && 'param' == $tag['name'] ) {
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
				$arg = (array) $arg;
				$arg_string = '';
				if ( ! empty( $arg['name'] ) && ! empty( $types[ $arg['name'] ] ) ) {
					$arg_string .= ' <span class="arg-type">' . $types[ $arg['name'] ] . '</span>';
				}

				if ( ! empty( $arg['name'] ) ) {
					$arg_string .= '&nbsp;<span class="arg-name">' . $arg['name'] . '</span>';
				}

				if ( ! empty( $arg['default'] ) ) {
					$arg_string .= '&nbsp;=&nbsp;<span class="arg-default">' . htmlentities( $arg['default'] ) . "</span>";
				}

				$args_strings[] = $arg_string;
			}
		}

		$signature .= ' (';
		if ( $args = implode( ', ', $args_strings ) ) {
			$signature .= $args . '&nbsp;';
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
			$encountered_optional = false;
			foreach ( $tags as $tag ) {
				// Fix unintended markup introduced by parser.
				$tag = str_replace( array( '<strong>', '</strong>' ), '__', $tag );

				if ( ! empty( $tag['name'] ) && 'param' == $tag['name'] ) {
					$params[ $tag['variable'] ] = $tag;
					$types = array();
					foreach ( $tag['types'] as $i => $v ) {
						$types[ $i ] = "<span class=\"{$v}\">{$v}</span>";
					}

					// Normalize spacing at beginning of hash notation params.
					if ( $tag['content'] && '{' == $tag['content'][0] ) {
						$tag['content'] = '{ ' . trim( substr( $tag['content'], 1 ) );
					}

					$params[ $tag['variable'] ]['types'] = implode( '|', $types );
					if ( strtolower( substr( $tag['content'], 0, 8 ) ) == "optional" ) {
						$params[ $tag['variable'] ]['required'] = 'Optional';
						$params[ $tag['variable'] ]['content'] = substr( $tag['content'], 9 );
						$encountered_optional = true;
					} elseif ( strtolower( substr( $tag['content'], 2, 9 ) ) == "optional." ) { // Hash notation param
						$params[ $tag['variable'] ]['required'] = 'Optional';
						$params[ $tag['variable'] ]['content'] = '{ ' . substr( $tag['content'], 12 );
						$encountered_optional = true;
					} elseif ( $encountered_optional ) {
						$params[ $tag['variable'] ]['required'] = 'Optional';
					} else {
						$params[ $tag['variable'] ]['required'] = 'Required';
					}
					$params[ $tag['variable'] ]['content'] = \DevHub_Formatting::format_param_description( $params[ $tag['variable'] ]['content'] );
				}
			}
		}

		if ( $args ) {
			foreach ( $args as $arg ) {
				if ( ! empty( $arg['name'] ) && ! empty( $params[ $arg['name'] ] ) ) {
					$params[ $arg['name'] ]['default'] = $arg['default'];

					// If a default value was supplied
					if ( ! empty( $arg['default'] ) ) {
						// Ensure the parameter was marked as optional (sometimes they aren't
						// properly and explicitly documented as such)
						$params[ $arg['name'] ]['required'] = 'Optional';

						// If a known default is stated in the parameter's description, try to remove it
						// since the actual default value is displayed immediately following description.
						$default = htmlentities( $arg['default'] );
						$params[ $arg['name'] ]['content'] = str_replace( "default is {$default}.", '', $params[ $arg['name'] ]['content'] );
						$params[ $arg['name'] ]['content'] = str_replace( "Default {$default}.", '', $params[ $arg['name'] ]['content'] );

						// When the default is '', docs sometimes say "Default empty." or similar.
						if ( "''" == $arg['default'] ) {
							$params[ $arg['name'] ]['content'] = str_replace( "Default empty.", '', $params[ $arg['name'] ]['content'] );
							$params[ $arg['name'] ]['content'] = str_replace( "Default empty string.", '', $params[ $arg['name'] ]['content'] );

							// Only a few cases of this. Remove once core is fixed.
							$params[ $arg['name'] ]['content'] = str_replace( "default is empty string.", '', $params[ $arg['name'] ]['content'] );
						}
						// When the default is array(), docs sometimes say "Default empty array." or similar.
						elseif (  'array()' == $arg['default'] ) {
							$params[ $arg['name'] ]['content'] = str_replace( "Default empty array.", '', $params[ $arg['name'] ]['content'] );
							// Not as common.
							$params[ $arg['name'] ]['content'] = str_replace( "Default empty.", '', $params[ $arg['name'] ]['content'] );
						}
					}
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
	 * Retrieve return type and description if available.
	 *
	 * If there is no explicit return value, or it is explicitly "void", then
	 * an empty string is returned. This rules out display of return type for
	 * classes, hooks, and non-returning functions.
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

		// If there is no explicit or non-"void" return value, don't display one.
		if ( empty( $return ) ) {
			return '';
		}

		$return      = array_shift( $return );
		$description = empty( $return['content'] ) ? '' : \DevHub_Formatting::make_doclink_clickable( $return['content'] );
		$type        = empty( $return['types'] ) ? '' : esc_html( implode( '|', $return['types'] ) );

		return "<span class='return-type'>({$type})</span> $description";
	}

	/**
	 * Retrieve changelog data for the current post.
	 *
	 * @param null $post_id Post ID, defaults to the ID of the global $post.
	 *
	 * @return array Associative array of changelog data.
	 */
	function get_changelog_data( $post_id = null ) {
		$post_id = empty( $post_id ) ? get_the_ID() : $post_id;

		// Since terms assigned to the post.
		$since_terms = wp_get_post_terms( $post_id, 'wp-parser-since' );

		// Since data stored in meta.
		$since_meta = get_post_meta( $post_id, '_wp-parser_tags', true );

		$data = array();

		// Pair the term data with meta data.
		foreach ( $since_terms as $since_term ) {
			foreach ( $since_meta as $meta ) {
				if ( is_array( $meta ) && $since_term->name == $meta['content'] ) {
					$description = empty( $meta['description'] ) ? '' : '<span class="since-description">' . \DevHub_Formatting::format_param_description( $meta['description'] ) . '</span>';

					$data[ $since_term->name ] = array(
						'version'     => $since_term->name,
						'description' => $description,
						'since_url'   => get_term_link( $since_term )
					);
				}
			}
		}
		return $data;
	}

	/**
	 * Retrieve URL to a since version archive.
	 *
	 * @param string $name Since version, e.g. 'x.x.x'.
	 *
	 * @return string Since term archive URL.
	 */
	function get_since_link( $name = null ) {

		$since_object = get_term_by( 'name', empty( $name ) ? get_since() : $name, 'wp-parser-since' );

		return empty( $since_object ) ? '' : esc_url( get_term_link( $since_object ) );
	}

	/**
	 * Indicates if a post is deprecated.
	 *
	 * @param int $post_id
	 *
	 * @return bool
	 */
	function is_deprecated( $post_id = null ) {
		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}

		$tags           = get_post_meta( $post_id, '_wp-parser_tags', true );
		$all_deprecated = wp_filter_object_list( $tags, array( 'name' => 'deprecated' ) );

		return !! $all_deprecated;
	}

	/**
	 * Retrieve deprecated notice.
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	function get_deprecated( $post_id = null ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$types          = explode( '-', get_post_type( $post_id ) );
		$type           = array_pop( $types );
		$tags           = get_post_meta( $post_id, '_wp-parser_tags', true );
		$deprecated = wp_filter_object_list( $tags, array( 'name' => 'deprecated' ) );
		$deprecated = array_shift( $deprecated );

		if ( ! $deprecated ) {
			return '';
		}

		$deprecation_info = '';

		$referral = wp_filter_object_list( $tags, array( 'name' => 'see' ) );
		$referral = array_shift( $referral );

		// Construct message pointing visitor to preferred alternative, as provided
		// via @see, if present.
		if ( ! empty( $referral['refers'] ) ) {
			$refers = sanitize_text_field( $referral['refers'] );

			if ( $refers ) {
				// For some reason, the parser may have dropped the parentheses, so add them.
				if ( in_array( $type, array( 'function', 'method' ) ) && false === strpos( $refers, '()' ) ) {
					$refers .= '()';
				}
				/* translators: %s: Linked internal element name */
				$deprecation_info = ' ' . sprintf( __( 'Use %s instead.', 'wporg' ), \DevHub_Formatting::link_internal_element( $refers ) );
			}
		}

		// If no alternative resource was referenced, use the deprecation string, if
		// present.
		if ( ! $deprecation_info && ! empty( $deprecated['content'] ) ) {
			$deprecation_info = ' ' . sanitize_text_field ( $deprecated['content'] );
			// Many deprecation strings use the syntax "Use function()" instead of the
			// preferred "Use function() instead." Add it in if missing.
			if ( false === strpos( $deprecation_info, 'instead' ) ) {
				$deprecation_info .= ' instead.'; // Not making translatable since rest of string is not translatable.
			}
		}

		/* translators: 1: parsed post post, 2: String for alternative function (if one exists) */
		$contents = sprintf( __( 'This %1$s has been deprecated.%2$s', 'wporg' ),
			$type,
			$deprecation_info
		);

		// Use the 'warning' callout box if it's available. Otherwise, fall back to a theme-supported div class.
		if ( class_exists( 'WPorg_Handbook_Callout_Boxes' ) ) {
			$callout = new \WPorg_Handbook_Callout_Boxes();
			$message = $callout->warning_shortcode( array(), $contents );
		} else {
			$message  = '<div class="deprecated">';
			/** This filter is documented in wp-includes/post-template.php */
			$message .= apply_filters( 'the_content', $contents );
			$message .= '</div>';
		}

		return $message;
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
	 * Retrieve either the starting or ending line number.
	 *
	 * @param  int    $post_id Optional. The post ID.
	 * @param  string $type    Optional. Either 'start' for starting line number, or 'end' for ending line number.
	 *
	 * @return int
	 */
	function get_line_number( $post_id = null, $type = 'start' ) {

		$post_id  = empty( $post_id ) ? get_the_ID() : $post_id;
		$meta_key = ( 'end' == $type ) ? '_wp-parser_end_line_num' : '_wp-parser_line_num';

		return (int) get_post_meta( $post_id, $meta_key, true );
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

	function show_usage_info() {
		$p2p_enabled = function_exists( 'p2p_register_connection_type' );

		return $p2p_enabled && post_type_has_usage_info( get_post_type() );
	}

	/**
	 * Does the post type support usage information?
	 *
	 * @param string $post_type Optional. The post type name. If blank, assumes current post type.
	 *
	 * @return boolean
	 */
	function post_type_has_usage_info( $post_type = null ) {
		$post_type             = $post_type ? $post_type : get_post_type();
		$post_types_with_usage = array( 'wp-parser-function', 'wp-parser-method', 'wp-parser-hook' );

		return in_array( $post_type, $post_types_with_usage );
	}

	/**
	 * Does the post type support uses information?
	 *
	 * @param string $post_type Optional. The post type name. If blank, assumes current post type.
	 *
	 * @return boolean
	 */
	function post_type_has_uses_info( $post_type = null ) {
		$post_type             = $post_type ? $post_type : get_post_type();
		$post_types_with_uses  = array( 'wp-parser-function', 'wp-parser-method' );

		return in_array( $post_type, $post_types_with_uses );
	}

	/**
	 * Retrieve a WP_Query object for the posts that the current post uses
	 *
	 * @return WP_Query A WP_Query object for the posts the current post uses
	 */
	function get_uses() {

		if ( 'wp-parser-function' === get_post_type() ) {
			$connection_types = array( 'functions_to_functions', 'functions_to_methods', 'functions_to_hooks' );
		} else {
			$connection_types = array( 'methods_to_functions', 'methods_to_methods', 'methods_to_hooks' );
		}

		$connected = new \WP_Query( array(
			'post_type'           => array( 'wp-parser-function', 'wp-parser-method', 'wp-parser-hook' ),
			'connected_type'      => $connection_types,
			'connected_direction' => array( 'from', 'from', 'from' ),
			'connected_items'     => get_the_ID(),
			'nopaging'            => true,
		) );

		return $connected;
	}

	function get_used_by( $post_id = null ) {

		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}

		switch ( get_post_type() ) {

			case 'wp-parser-function':
				$connection_types = array( 'functions_to_functions', 'methods_to_functions' );
				break;

			case 'wp-parser-method':
				$connection_types = array( 'functions_to_methods', 'methods_to_methods', );
				break;

			case 'wp-parser-hook':
				$connection_types = array( 'functions_to_hooks', 'methods_to_hooks' );
				break;

			default:
				return;

		}

		$connected = new \WP_Query( array(
			'post_type'           => array( 'wp-parser-function', 'wp-parser-method' ),
			'connected_type'      => $connection_types,
			'connected_direction' => array( 'to', 'to' ),
			'connected_items'     => $post_id,
			'nopaging'            => true,
		) );

		return $connected;
	}

	/**
	 * Does the post type have source code?
	 *
	 * @param  null|string $post_type Optional. The post type name. If null, assumes current post type. Default null.
	 *
	 * @return bool
	 */
	function post_type_has_source_code( $post_type = null ) {
		$post_type                   = $post_type ? $post_type : get_post_type();
		$post_types_with_source_code = array( 'wp-parser-class', 'wp-parser-method', 'wp-parser-function' );

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

				// Stop reading file once end_line is reached.
				if ( $line > $end_line ) {
					break;
				}

				// Skip lines until start_line is reached.
				if ( $line < $start_line ) {
					continue;
				}

				// Skip the last line if it is "endif;"; the parser includes the
				// endif of a if/endif wrapping typical of pluggable functions.
				if ( $line === $end_line && 'endif;' === trim( $source_line ) ) {
					continue;
				}

				$source_code .= $source_line;
			}
			fclose( $handle );
		}

		update_post_meta( $post_id, $meta_key, addslashes( $source_code ) );

		return $source_code;
	}

	/**
	 * Indicates if the current user can post a user contibuted note.
	 *
	 * This only affects parsed post types as they are the only things
	 * that can have user contributed notes.
	 *
	 * A custom check can be performed by hooking the filter
	 * 'wporg_devhub-can_user_post_note' and returning a
	 * value other than null.
	 *
	 * By default, the ability to post notes is restricted to members of the
	 * blog.
	 *
	 * @param  bool    $open If the user can post comments in general.
	 * @param  WP_Post $post Post ID or post object.
	 *
	 * @return bool True if the user can post a note.
	 */
	function can_user_post_note( $open, $post ) {
		// Only proceed if for a parsed post type.
		if ( ! is_parsed_post_type( get_post_type( $post ) ) ) {
			return $open;
		}

		// Permit default logic to be overridden via filter that returns value other than null.
		if ( null !== ( $can = apply_filters( 'wporg_devhub-can_user_post_note', null, $post ) ) ) {
			return $can;
		}

		return $open;
	}

	/**
	 * Gets the summary.
	 *
	 * The summary (aka short description) is stored in the 'post_excerpt' field.
	 *
	 * @param  null|WP_Post Optional. The post. Default null.
	 * @return string
	 */
	function get_summary( $post = null ) {
		$post = get_post( $post );

		$summary = $post->post_excerpt;

		if ( $summary ) {
			add_filter( 'the_excerpt', 'htmlentities', 9 ); // Run before wpautop
			$summary = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $summary ) );
			remove_filter( 'the_excerpt', 'htmlentities', 9 );
		}

		return $summary;
	}

	/**
	 * Gets the description.
	 *
	 * The (long) description is stored in the 'post_content' get_post_field.
	 *
	 * @param  null|WP_Post Optiona. The post.
	 * @return string
	 */
	function get_description( $post = null ) {
		$post = get_post( $post );

		$description = $post->post_content;

		if ( $description ) {
			$description = apply_filters( 'the_content', apply_filters( 'get_the_content' , $description ) );
		}

		return $description;
	}

	/**
	 * Formats the output of params defined using hash notation.
	 *
	 * This is a temporary measure until the parser parses the hash notation
	 * into component elements that the theme could then handle and style
	 * properly.
	 *
	 * Also, as a stopgap this is going to begin as a barebones hack to simply
	 * keep the text looking like one big jumble.
	 *
	 * @param  string $text The content for the param.
	 * @return string
	 */
	function param_formatting_fixup( $text ) {
		// Don't do anything if this isn't a hash notation string.
		if ( '{' != $text[0] ) {
			return $text;
		}

		$new_text = '';
		$text     = trim( substr( $text, 1, -1 ) );
		$text     = str_replace( '@type', "\n@type", $text );

		$in_list = false;
		$parts = explode( "\n", $text );
		foreach ( $parts as $part ) {
			$part = preg_replace( '/\s+/', ' ', $part );
			list( $wordtype, $type, $name, $description ) = explode( ' ', $part, 4 );
			$description = trim( $description );

			$skip_closing_li = false;

			// Handle nested hashes.
			if ( '{' === $description[0] || '{' === $name ) {
				$description = ltrim( $description, '{' ) . '<ul class="param-hash">';
				$skip_closing_li = true;
			} elseif ( '}' === substr( $description, -1 ) ) {
				$description = substr( $description, 0, -1 ) . "</li></ul>\n";
			}

			if ( '@type' != $wordtype ) {
				if ( $in_list ) {
					$in_list = false;
					$new_text .= "</li></ul>\n";
				}

				$new_text .= $part;
			} else {
				if ( $in_list ) {
					$new_text .= '<li>';
				} else {
					$new_text .= '<ul class="param-hash"><li>';
					$in_list = true;
				}

				// Normalize argument name.
				if ( $name === '{' ) {
					// No name is specified, generally indicating an array of arrays.
					$name = '';
				} else {
					// The name is defined as a variable, so remove the leading '$'.
					$name = ltrim( $name, '$' );
				}
				if ( $name ) {
					$new_text .= "<b>'{$name}'</b><br />";
				}
				$new_text .= "<i><span class='type'>({$type})</span></i> {$description}";
				if ( ! $skip_closing_li ) {
					$new_text .= '</li>';
				}
				$new_text .= "\n";
			}
		}

		if ( $in_list ) {
			$new_text .= "</li></ul>\n";
		}

		return $new_text;
	}

	/**
	 * Should the search bar be shown?
	 *
	 * @return bool True if search bar should be shown.
	 */
	function should_show_search_bar() {
		$post_types = get_parsed_post_types();
		$taxonomies = array( 'wp-parser-since', 'wp-parser-package', 'wp-parser-source-file' );

		return ( is_singular( $post_types ) || is_post_type_archive( $post_types ) || is_tax( $taxonomies ) );
	}

	/**
	 * Retrieve an explanation for the given post.
	 *
	 * @param int|WP_Post $post      Post ID or WP_Post object.
	 * @param bool        $published Optional. Whether to only retrieve the explanation if it's published.
	 *                               Default false.
	 * @return WP_Post|null WP_Post object for the Explanation, null otherwise.
	 */
	function get_explanation( $post, $published = false ) {
		if ( ! $post = get_post( $post ) ) {
			return null;
		}

		$args = array(
			'post_type'      => 'wporg_explanations',
			'post_parent'    => $post->ID,
			'no_found_rows'  => true,
			'posts_per_page' => 1,
		);

		if ( true === $published ) {
			$args['post_status'] = 'publish';
		}

		$explanation = get_children( $args, OBJECT );

		if ( empty( $explanation ) ) {
			return null;
		}

		$explanation = reset( $explanation );

		if ( ! $explanation ) {
			return null;
		}
		return $explanation;
	}

	/**
	 * Retrieve data from an explanation post field.
	 *
	 * Works only for published explanations.
	 *
	 * @see get_post_field()
	 *
	 * @param string      $field   Post field name.
	 * @param int|WP_Post $post    Post ID or object for the function, hook, class, or method post
	 *                             to retrieve an explanation field for.
	 * @param string      $context Optional. How to filter the field. Accepts 'raw', 'edit', 'db',
	 *                             or 'display'. Default 'display'.
	 * @return string The value of the post field on success, empty string on failure.
	 */
	function get_explanation_field( $field, $post, $context = 'display' ) {
		if ( ! $explanation = get_explanation( $post, $published = true ) ) {
			return '';
		}
		return get_post_field( $field, $explanation, $context );
	}

	/**
	 * Generates a private access message for a private element.
	 *
	 * @param int|WP_Post $post Optional. Post object or ID. Default global `$post`.
	 * @return string Private access message if the given reference is considered "private".
	 */
	function get_private_access_message( $post = null ) {
		if ( ! $post = get_post( $post ) ) {
			return '';
		}

		// Currently only handling private access messages for functions and hooks.
		if ( ! in_array( get_post_type( $post ), array( 'wp-parser-function', 'wp-parser-hook' ) ) ) {
			return '';
		}

		$tags        = get_post_meta( $post->ID, '_wp-parser_tags', true );
		$access_tags = wp_filter_object_list( $tags, array(
			'name'    => 'access',
			'content' => 'private'
		) );

		// Bail if it isn't private.
		if ( empty( $access_tags ) ) {
			return '';
		}

		$referral = wp_filter_object_list( $tags, array( 'name' => 'see' ) );
		$referral = array_shift( $referral );

		if ( ! empty( $referral['refers'] ) ) {
			$refers = sanitize_text_field( $referral['refers'] );

			if ( ! empty( $refers ) ) {
				/* translators: 1: Linked internal element name */
				$alternative_string = sprintf( __( ' Use %s instead.', 'wporg' ), \DevHub_Formatting::link_internal_element( $refers ) );
			}
		} else {
			$alternative_string = '';
		}

		/* translators: 1: String for alternative function (if one exists) */
		$contents = sprintf( __( 'This function&#8217;s access is marked private. This means it is not intended for use by plugin or theme developers, only in other core functions. It is listed here for completeness.%s', 'wporg' ),
			$alternative_string
		);

		// Use the 'alert' callout box if it's available. Otherwise, fall back to a theme-supported div class.
		if ( class_exists( 'WPorg_Handbook_Callout_Boxes' ) ) {
			$callout = new \WPorg_Handbook_Callout_Boxes();
			$message = $callout->alert_shortcode( array(), $contents );
		} else {
			$message  = '<div class="private-access">';
			/** This filter is documented in wp-includes/post-template.php */
			$message .= apply_filters( 'the_content', $contents );
			$message .= '</div>';
		}

		return $message;
	}
}
