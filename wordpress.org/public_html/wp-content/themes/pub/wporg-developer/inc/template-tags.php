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

	if ( ! function_exists( 'wporg_developer_get_ordered_notes' ) ) :
		/**
		 * Get contibuted notes ordered by vote
		 *
		 * Only the parent notes are ordered by vote count.
		 * Child notes are added to to the parent note 'child_notes' property.
		 * Unapproved notes for the current user are included.
		 * Use `wporg_developer_list_notes()` to display the notes.
		 *
		 * @param integer $post_id Optional. Post id to get comments for
		 * @param array $args Arguments used for get_comments().
		 * @return array Array with comment objects
		 */
		function wporg_developer_get_ordered_notes( $post_id = 0, $args = array() ) {

			$post_id = absint( $post_id );

			if ( ! $post_id ) {
				$post_id = get_the_ID();
			}

			$defaults = array(
				'post__in'           => array( $post_id ),
				'type'               => 'comment',
				'status'             => 'approve',
				'include_unapproved' => array_filter( array( get_current_user_id() ) ),
			);

			if ( is_super_admin() ) {
				$defaults['status'] = 'all';
			}

			$args     = wp_parse_args( $args, $defaults );
			$comments = get_comments( $args );

			if ( ! $comments ) {
				return;
			}

			// Check if the current page is a reply to a note.
			$reply_id = 0;
			if ( isset( $_GET['replytocom'] ) && $_GET['replytocom'] ) {		
				/* Javascript uses preventDefault() when clicking links with '?replytocom={comment_ID}'
				 * We assume Javascript is disabled when visiting a page with this query var.
				 * There are no consequences if Javascript is enabled.
				 */
				$reply_id = absint( $_GET['replytocom'] );
			}

			$order = $children = array();
			$voting = class_exists( 'DevHub_User_Contributed_Notes_Voting' );

			// Remove child notes and add the vote count order for parent notes.
			foreach ( $comments as $key => $comment ) {
				if ( 0 === (int) $comment->comment_parent ) {
					$vote_count    = $voting ? (int) DevHub_User_Contributed_Notes_Voting::count_votes( $comment->comment_ID, 'difference' ) : 0;
					$order[ $key ] = $vote_count;
				} else {
					unset( $comments[ $key ] );
					$children[ $comment->comment_parent ][] = $comment;
				}
			}

			$show_editor = false;

			// Add children notes to their parents.
			foreach ( $comments as $key => $comment ) {
				$comments[ $key ]->child_notes = array();
				$comments[ $key ]->show_editor = false;

				if ( array_key_exists( $comment->comment_ID, $children ) ) {
					$comments[ $key ]->child_notes = array_reverse( $children[ $comment->comment_ID ] );
				}

				if ( ! $show_editor && ( $reply_id && ( $reply_id === (int) $comment->comment_ID ) ) ) {
					/* The query var 'replytocom' is used and the value is the same as the current comment ID.
					 * We show the editor for the current comment because we assume Javascript is disabled.
					 * If Javascript is not disabled the editor is hidden (as normal) by the class 'hide-if-js'.
					 */
					$comments[ $key ]->show_editor = true;
					$show_editor = true;
				}
			}

			// sort the posts by votes
			array_multisort( $order, SORT_DESC, $comments );

			return $comments;
		}
	endif;

	if ( ! function_exists( 'wporg_developer_list_notes' ) ) :
		/**
		 * List user contributed notes.
		 *
		 * @param array   $comments Array with comment objects.
		 * @param array   $args Comment display arguments.
		 */
		function wporg_developer_list_notes( $comments, $args ) {
			$is_user_content    = class_exists( 'DevHub_User_Submitted_Content' );
			$is_user_logged_in  = is_user_logged_in();
			$can_user_post_note = DevHub\can_user_post_note( true, get_the_ID() );
			$is_user_verified   = $is_user_logged_in && $can_user_post_note;
		
			$args['updated_note'] = 0;
			if ( isset( $_GET['updated-note'] ) && $_GET['updated-note'] ) {
				$args['updated_note'] = absint( $_GET['updated-note'] );
			}

			foreach ( $comments as $comment ) {

				$comment_id = $comment->comment_ID;

				// Display parent comment.
				wporg_developer_user_note( $comment, $args, 1 );

				/* Use hide-if-js class to hide the feedback section if Javascript is enabled.
				 * Users can display the section with Javascript.
				 */
				echo "<section id='feedback-{$comment_id}' class='feedback hide-if-js'>\n";

				// Display child comments.
				if ( ! empty( $comment->child_notes ) ) {

					echo "<h4 class='feedback-title'>" . __( 'Feedback', 'wporg' ) . "</h4>\n";
					echo "<ul class='children'>\n";
					foreach ( $comment->child_notes as $child_note ) {
						wporg_developer_user_note( $child_note, $args, 2, $comment->show_editor );
					}
					echo "</ul>\n";
				}

				// Add a feedback form for logged in users.
				if ( $is_user_content && $is_user_verified ) {
					/* Show the feedback editor if we're replying to a note and Javascript is disabled.
					 * If Javascript is enabled the editor is hidden (as normal) by the 'hide-if-js' class.
					 */
					$display = $comment->show_editor ? 'show' : 'hide';
					echo DevHub_User_Submitted_Content::wp_editor_feedback( $comment, $display );
				}
				echo "</section><!-- .feedback -->\n";

				// Feedback links to log in, add feedback or show feedback.
				echo "<footer class='feedback-links' >\n";
				if ( $can_user_post_note ) {
					$feedback_link = trailingslashit( get_permalink() ) . "?replytocom={$comment_id}#feedback-editor-{$comment_id}";
					$display       = '';
					if ( ! $is_user_logged_in ) {
						$class         = 'login';
						$feedback_text = __( 'Log in to add feedback', 'wporg' );
						$feedback_link = 'https://login.wordpress.org/?redirect_to=' . urlencode( $feedback_link );
					} else {
						$class         ='add';
						$feedback_text = __( 'Add feedback to this note', 'wporg' );

						/* Hide the feedback link if the current user is logged in and the
						 * feedback editor is displayed (because Javascript is disabled).
						 * If Javascript is enabled the editor is hidden and the feedback link is displayed (as normal).
						 */
						$display = $is_user_verified && $comment->show_editor ? ' style="display:none"' : '';
					}
					echo '<a role="button" class="feedback-' . $class . '" href="' . esc_url( $feedback_link ) . '"' . $display . ' rel="nofollow">' . $feedback_text . '</a>';
				}

				// close parent list item
				echo "</footer>\n</article><!-- .comment-body -->\n</li>\n";
			}
		}
	endif;


	if ( ! function_exists( 'wporg_developer_user_note' ) ) :
		/**
		 * Template for user contributed notes.
		 *
		 * @param object $comment Comment object.
		 * @param array  $args    Arguments.
		 * @param int    $depth   Nested comment depth.
		 */
		function wporg_developer_user_note( $comment, $args, $depth ) {
			$GLOBALS['comment']       = $comment;
			$GLOBALS['comment_depth'] = $depth;

			static $note_number = 0;

			$approved       = ( 0 < (int) $comment->comment_approved ) ? true : false;
			$is_parent      = ( 0 === (int) $comment->comment_parent ) ? true : false;
			$is_voting      = class_exists( 'DevHub_User_Contributed_Notes_Voting' );
			$count          = $is_voting ? (int)  DevHub_User_Contributed_Notes_Voting::count_votes( $comment->comment_ID, 'difference' ) : 0;
			$curr_user_note = $is_voting ? (bool) DevHub_User_Contributed_Notes_Voting::is_current_user_note( $comment->comment_ID ) : false;
			$edited_note_id = isset( $args['updated_note'] ) ? $args['updated_note'] : 0;
			$is_edited_note = ( $edited_note_id === (int) $comment->comment_ID );
			$note_author    = \DevHub\get_note_author_link( $comment );
			$can_edit_note  = \DevHub\can_user_edit_note( $comment->comment_ID );
			$has_edit_cap   = current_user_can( 'edit_comment', $comment->comment_ID );

			// CSS Classes
			$comment_class = array();

			if ( -1 > $count ) {
				$comment_class[] = 'bad-note';
			}

			if ( $curr_user_note ) {
				$comment_class[] = 'user-submitted-note';
			}

			if ( ! $approved ) {
				$comment_class[] = 'user-note-moderated';
			}

			$date = sprintf( _x( '%1$s ago', '%1$s = human-readable time difference', 'wporg' ),
				human_time_diff( get_comment_time( 'U' ),
				current_time( 'timestamp' ) )
			);
			?>
			<li id="comment-<?php comment_ID(); ?>" data-comment-id="<?php echo $comment->comment_ID;  ?>" <?php comment_class( implode( ' ', $comment_class ) ); ?>>
			<article id="div-comment-<?php comment_ID(); ?>" class="comment-body">

			<?php if ( $is_parent ) : ?>
				<a href="#comment-content-<?php echo $comment->comment_ID; ?>" class="screen-reader-text"><?php printf( __( 'Skip to note %d content', 'wporg' ), ++ $note_number ); ?></a>
				<header class="comment-meta">

				<?php
				if ( $is_voting ) {
					DevHub_User_Contributed_Notes_Voting::show_voting();
				}
				?>
					<div class="comment-author vcard">
						<span class="comment-author-attribution">
						<?php
						if ( 0 != $args['avatar_size'] ) {
							echo get_avatar( $comment, $args['avatar_size'] );
						}

						printf( __( 'Contributed by %s', 'wporg' ), sprintf( '<cite class="fn">%s</cite>', $note_author ) );
						?>

						</span>
						&mdash;
						<a class="comment-date" href="<?php echo esc_url( get_comment_link( $comment->comment_ID ) ); ?>">
							<time datetime="<?php comment_time( 'c' ); ?>">
							<?php echo $date; ?>
							</time>
						</a>

						<?php edit_comment_link( __( 'Edit', 'wporg' ), '<span class="edit-link">&mdash; ', '</span>' ); ?>
						<?php if ( ! $has_edit_cap && $can_edit_note ) : ?>
							&mdash; <span class="comment-author-edit-link">
								<!-- Front end edit comment link -->
								<a class="comment-edit-link" href="<?php echo site_url( "/reference/comment/edit/{$comment->comment_ID}" ); ?>"><?php _e( 'Edit', 'wporg' ); ?></a>
							</span>
						<?php endif; ?>
						<?php if ( $can_edit_note && $is_edited_note ) : ?>
							&mdash; <span class="comment-edited">
							<?php _e( 'edited', 'wporg' ); ?>
							</span>
						<?php endif; ?>
						<?php if ( ! $approved ) : ?>
							&mdash; <span class="comment-awaiting-moderation"><?php _e( 'awaiting moderation', 'wporg' ); ?></span>
						<?php endif; ?>
					</div>
				</header>
				<!-- .comment-metadata -->
			<?php endif; ?>

				<div class="comment-content" id="comment-content-<?php echo $comment->comment_ID; ?>">
				<?php
				if ( $is_parent ) {
					comment_text();
				} else {
					$text = get_comment_text()  . ' &mdash; ';
					$text .= sprintf( __( 'By %s', 'wporg' ), sprintf( '<cite class="fn">%s</cite>', $note_author ) ) . ' &mdash; ';
					$text .= ' <a class="comment-date" href="'. esc_url( get_comment_link( $comment->comment_ID ) ) . '">';
					$text .= '<time datetime="' . get_comment_time( 'c' ) . '">' . $date . '</time></a>';

					if ( $has_edit_cap ) {
						// WP admin edit comment link.
						$text .= ' &mdash; <a class="comment-edit-link" href="' . get_edit_comment_link( $comment->comment_ID ) .'">';
						$text .= __( 'Edit', 'wporg' ) . '</a>';
					} elseif ( $can_edit_note ) {
						// Front end edit comment link.
						$text .= ' &mdash; <a class="comment-edit-link" href="' . site_url( "/reference/comment/edit/{$comment->comment_ID}" ) . '">';
						$text .= __( 'Edit', 'wporg' ) . '</a>';
					}

					if ( $can_edit_note && $is_edited_note ) {
						$text .= ' &mdash; <span class="comment-edited">' . __( 'edited', 'wporg' ) . '</span>';
					}

					if ( ! $approved ) {
						$text .= ' &mdash; <span class="comment-awaiting-moderation">' . __( 'awaiting moderation', 'wporg' ) . '</span>';
					}

					echo apply_filters( 'comment_text', $text );
				}
				?>
				</div><!-- .comment-content -->

			<?php if ( ! $is_parent ) : ?>
			</article>
			</li>
			<?php endif; ?>
		<?php
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
	 * WP_CORE_LATEST_RELEASE constant (set on WordPress.org) though this is not
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
				$current_version = '4.6';
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
	 * @return object|WP_Error
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
			$current_version = implode( '.', $version_parts );
		}

		$version = get_terms( 'wp-parser-since', array(
			'number' => '1',
			'order'  => 'DESC',
			'slug'   => $current_version,
		) );

		return is_wp_error( $version ) ? $version : reset( $version );
	}

	/**
	 * Get an array of all parsed post types.
	 *
	 * @param string  $labels If set to 'labels' post types with their labels are returned.
	 * @return array
	 */
	function get_parsed_post_types( $labels = '' ) {
		$post_types =  array(
			'wp-parser-class'    => __( 'Classes',   'wporg' ),
			'wp-parser-function' => __( 'Functions', 'wporg' ),
			'wp-parser-hook'     => __( 'Hooks',     'wporg' ),
			'wp-parser-method'   => __( 'Methods',   'wporg' ),
		);

		if ( 'labels' !== $labels ) {
			return array_keys( $post_types );
		}

		return $post_types;
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
		$parts = explode( '/', $GLOBALS['wp']->request );
		switch ( $parts[0] ) {
			case 'reference':
			case 'plugins':
			case 'themes':
				return home_url( '/' . $parts[0] . '/' );
			case 'cli':
				return home_url( '/cli/commands/' );
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
		$parts = explode( '/', $GLOBALS['wp']->request );
		switch ( $parts[0] ) {
			case 'resources':
			case 'resource':
				return sprintf( __( 'Developer Resources: %s', 'wporg' ), get_the_title() );
			case 'reference':
				return __( 'Code Reference', 'wporg' );
			case 'plugins':
				return __( 'Plugin Handbook', 'wporg' );
			case 'themes':
				return __( 'Theme Handbook', 'wporg' );
			case 'apis':
				return __( 'Common APIs Handbook', 'wporg' );
			case 'block-editor':
				return __( 'Block Editor Handbook', 'wporg' );
			case 'cli':
				return __( 'WP-CLI Commands', 'wporg' );
			case 'coding-standards':
				return __( 'Coding Standards Handbook', 'wporg' );
			case 'rest-api':
				return __( 'REST API Handbook', 'wporg' );
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

		if ( 'wp-parser-class' === get_post_type( $post_id ) ) {
			return $signature;
		}

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
				if ( 'action_reference' === $hook_type ) {
					$hook_type = 'do_action_ref_array';
				} elseif ( 'action_deprecated' === $hook_type ) {
					$hook_type = 'do_action_deprecated';
				} else {
					$hook_type = 'do_action';
				}
			} else {
				if ( 'filter_reference' === $hook_type ) {
					$hook_type = 'apply_filters_ref_array';
				} elseif ( 'filter_deprecated' === $hook_type ) {
					$hook_type = 'apply_filters_deprecated';
				} else {
					$hook_type = 'apply_filters';
				}
			}

			$delimiter = false !== strpos( $signature, '$' ) ? '"' : "'";
			$signature = $delimiter . $signature . $delimiter;
			$signature = '<span class="hook-func">' . $hook_type . '</span>( ' . $signature;
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

		$signature .= '(';
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
		$params = [];
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
						$types[ $i ] = sprintf( '<span class="%s">%s</span>', $v, apply_filters( 'devhub-parameter-type', $v, $post_id ) );
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
		$description = empty( $return['content'] ) ? '' : \DevHub_Formatting::format_param_description( $return['content'] );
		$type        = empty( $return['types'] ) ? '' : esc_html( implode( '|', $return['types'] ) );
		$type        = apply_filters( 'devhub-function-return-type', $type, $post_id );

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

		$since_tags = wp_filter_object_list( $since_meta, array( 'name' => 'since' ) );
		$deprecated = wp_filter_object_list( $since_meta, array( 'name' => 'deprecated' ) );

		// If deprecated, add the since version to the term and meta lists.
		if ( $deprecated ) {
			$deprecated = array_shift( $deprecated );

			if ( $term = get_term_by( 'name', $deprecated['content'], 'wp-parser-since' ) ) {
				// Terms.
				$since_terms[] = $term;

				// Meta.
				$since_tags[] = $deprecated;
			}
		}

		$data = array();

		// Pair the term data with meta data.
		foreach ( $since_terms as $since_term ) {
			foreach ( $since_tags as $meta ) {
				if ( is_array( $meta ) && $since_term->name == $meta['content'] ) {
					// Handle deprecation notice if deprecated.
					if ( empty( $meta['description'] ) ) {
						if ( $deprecated ) {
							$description = get_deprecated( $post_id, false );
						} else {
							$description = '';
						}
					} else {
						$description = '<span class="since-description">' . \DevHub_Formatting::format_param_description( $meta['description'] ) . '</span>';
					}

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
	 * @param int  $post_id   Optional. Post ID. Default is the ID of the global `$post`.
	 * @param bool $formatted Optional. Whether to format the deprecation message. Default true.
	 * @return string Deprecated notice. If `$formatted` is true, will be output in markup
	 *                for a callout box.
	 */
	function get_deprecated( $post_id = null, $formatted = true ) {
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
		if ( ! $deprecation_info && ! empty( $deprecated['description'] ) ) {
			$deprecation_info = ' ' . sanitize_text_field ( $deprecated['description'] );
			// Many deprecation strings use the syntax "Use function()" instead of the
			// preferred "Use function() instead." Add it in if missing.
			if ( false === strpos( $deprecation_info, 'instead' ) ) {
				$deprecation_info = rtrim( $deprecation_info, '. ' );
				$deprecation_info .= ' instead.'; // Not making translatable since rest of string is not translatable.
			}
		}

		/* translators: 1: parsed post post, 2: String for alternative function (if one exists) */
		$contents = sprintf( __( 'This %1$s has been deprecated.%2$s', 'wporg' ),
			$type,
			$deprecation_info
		);

		if ( true === $formatted ) {
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
		} else {
			$message = $contents;
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
		$post_types_with_usage = array( 'wp-parser-function', 'wp-parser-method', 'wp-parser-hook', 'wp-parser-class' );

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
		$post_types_with_uses  = array( 'wp-parser-function', 'wp-parser-method', 'wp-parser-class' );

		return in_array( $post_type, $post_types_with_uses );
	}

	/**
	 * Retrieves a WP_Query object for the posts that the current post uses.
	 *
	 * @param int|WP_Post|null $post Optional. Post ID or post object. Default is global $post.
	 * @return WP_Query|null   The WP_Query if the post's post type supports 'uses', null otherwise.
	 */
	function get_uses( $post = null ) {
		$post_id   = get_post_field( 'ID', $post );
		$post_type = get_post_type( $post );

		if ( 'wp-parser-class' === $post_type ) {
			$extends = get_post_meta( $post_id, '_wp-parser_extends', true );
			if ( ! $extends ) {
				return;
			}
			$connected = new \WP_Query( array(
				'post_type' => array( 'wp-parser-class' ),
				'name'      => $extends,
			) );
			return $connected;
		} elseif ( 'wp-parser-function' === $post_type ) {
			$connection_types = array( 'functions_to_functions', 'functions_to_methods', 'functions_to_hooks' );
		} else {
			$connection_types = array( 'methods_to_functions', 'methods_to_methods', 'methods_to_hooks' );
		}

		$connected = new \WP_Query( array(
			'post_type'           => array( 'wp-parser-function', 'wp-parser-method', 'wp-parser-hook' ),
			'connected_type'      => $connection_types,
			'connected_direction' => array( 'from', 'from', 'from' ),
			'connected_items'     => $post_id,
			'nopaging'            => true,
		) );

		return $connected;
	}

	/**
	 * Retrieves a WP_Query object for the posts that use the specified post.
	 *
	 * @param int|WP_Post|null $post Optional. Post ID or post object. Default is global $post.
	 * @return WP_Query|null   The WP_Query if the post's post type supports 'used by', null otherwise.
	 */
	function get_used_by( $post = null ) {

		switch ( get_post_type( $post ) ) {

			case 'wp-parser-function':
				$connection_types = array( 'functions_to_functions', 'methods_to_functions' );
				break;

			case 'wp-parser-method':
				$connection_types = array( 'functions_to_methods', 'methods_to_methods', );
				break;

			case 'wp-parser-hook':
				$connection_types = array( 'functions_to_hooks', 'methods_to_hooks' );
				break;

			case 'wp-parser-class':
				$connected = new \WP_Query( array(
					'post_type'  => array( 'wp-parser-class' ),
					'meta_key'   => '_wp-parser_extends',
					'meta_value' => get_post_field( 'post_name', $post ),
				) );
				return $connected;
				break;

			default:
				return;

		}

		$connected = new \WP_Query( array(
			'post_type'           => array( 'wp-parser-function', 'wp-parser-method' ),
			'connected_type'      => $connection_types,
			'connected_direction' => array( 'to', 'to' ),
			'connected_items'     => get_post_field( 'ID', $post ),
			'nopaging'            => true,
		) );

		return $connected;
	}

	/**
	 * Returns the array of post types that have source code.
	 *
	 * @return array
	 */
	function get_post_types_with_source_code() {
		return array( 'wp-parser-class', 'wp-parser-method', 'wp-parser-function' );
	}

	/**
	 * Does the post type have source code?
	 *
	 * @param  null|string $post_type Optional. The post type name. If null, assumes current post type. Default null.
	 *
	 * @return bool
	 */
	function post_type_has_source_code( $post_type = null ) {
		$post_type = $post_type ? $post_type : get_post_type();

		return in_array( $post_type, get_post_types_with_source_code() );
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
				if ( $line >= $end_line ) {
					break;
				}

				// Skip lines until start_line is reached.
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
	 * Indicates if the current user can edit a user contibuted note.
	 *
	 * A user can only edit their own notes if it's in moderation and
	 * if it's a note for a parsed post type.
	 *
	 * Users with the 'edit_comment' capability can edit
	 * all notes from a parsed post type (regardless if it's in moderation).
	 *
	 * @param integer $note_id Note ID.
	 * @return bool True if the current user can edit notes.
	 */
	function can_user_edit_note( $note_id = 0 ) {
		$user = get_current_user_id();
		$note = get_comment( $note_id );
		if ( ! $user || ! $note ) {
			return false;
		}

		$post_id        = isset( $note->comment_post_ID ) ? (int) $note->comment_post_ID : 0;
		$is_note_author = isset( $note->user_id ) && ( (int) $note->user_id === $user );
		$is_approved    = isset( $note->comment_approved ) && ( 0 < (int) $note->comment_approved );
		$can_edit_notes = isset( $note->comment_ID ) && current_user_can( 'edit_comment', $note->comment_ID );
		$is_parsed_type = is_parsed_post_type( get_post_type( $post_id ) );

		if ( $is_parsed_type && ( $can_edit_notes || ( $is_note_author && ! $is_approved ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the note author link to the profiles.wordpress.org author's URL.
	 *
	 * @param WP_Comment|int $comment Comment object or comment ID.
	 * @return string The HTML link to the profiles.wordpress.org author's URL.
	 */
	function get_note_author_link( $comment ) {
		return get_note_author( $comment, true );
	}

	/**
	 * Get the note author nicename.
	 *
	 * @param WP_Comment|int $comment Comment object or comment ID.
	 * @param bool           $link. Whether to return a link to the author's profiles. Default false.
	 * @return string The comment author name or HTML link.
	 */
	function get_note_author( $comment, $link = false ) {
		$comment   = get_comment( $comment );
		$user_id   = isset( $comment->user_id ) ? $comment->user_id : 0;
		$commenter = get_user_by( 'id', $comment->user_id );
		$author    = '';

		if ( $user_id && isset( $commenter->user_nicename ) ) {
			$url    = 'https://profiles.wordpress.org/' . sanitize_key( $commenter->user_nicename ) . '/';
			$author = get_the_author_meta( 'display_name', $comment->user_id );
		} else {
			$url    = isset( $comment->comment_author_url ) ? $comment->comment_author_url : '';
			$author = isset( $comment->comment_author ) ?  $comment->comment_author : '';
		}

		if ( $link && ( $url && $author ) ) {
			$author = sprintf( '<a href="%s" rel="external nofollow" class="url">%s</a>', esc_url( $url ), $author );
		}

		return $author;
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
			// Backticks in excerpts are not automatically wrapped in code tags, so do so.
			// e.g. https://developer.wordpress.org/reference/functions/convert_chars/
			if ( false !== strpos( $summary, '`' ) ) {
				$summary = preg_replace_callback(
					'/`([^`]*)`/',
					function ( $matches ) { return '<code>' . htmlentities( $matches[1] ) . '</code>'; },
					$summary
				);
			}

			// Fix https://developer.wordpress.org/reference/functions/get_extended/
			// until the 'more' delimiter in summary is backticked.
			$summary = str_replace( array( '<!--', '-->' ), array( '<code>&lt;!--', '--&gt;</code>' ), $summary );

			// Fix standalone HTML tags that were not backticked.
			// e.g. https://developer.wordpress.org/reference/hooks/comment_form/
			if ( false !== strpos( $summary, '<' ) ) {
				$summary = preg_replace_callback(
					'/(\s)(<[^ >]+>)(\s)/',
					function ( $matches ) { return $matches[1] . '<code>' . htmlentities( $matches[2] ) . '</code>' . $matches[3]; },
					$summary
				);
			}

			$summary = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $summary, $post ) );
		}

		return $summary;
	}

	/**
	 * Gets the description.
	 *
	 * The (long) description is stored in the 'post_content' get_post_field.
	 *
	 * @param  null|WP_Post Optional. The post.
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
	 * Gets list of additional resources linked via `@see` tags.
	 *
	 * @param  null|WP_Post Optional. The post.
	 * @return array
	 */
	function get_see_tags( $post = null ) {
		$post = get_post( $post );

		$tags = get_post_meta( $post->ID, '_wp-parser_tags', true );

		return wp_list_filter( $tags, array( 'name' => 'see' ) );
	}

	/**
	 * Should the search bar be shown?
	 *
	 * @return bool True if search bar should be shown.
	 */
	function should_show_search_bar() {
		$post_types = get_parsed_post_types();
		$taxonomies = array( 'wp-parser-since', 'wp-parser-package', 'wp-parser-source-file' );

		return ! ( is_search() || is_404() ) && ( is_singular( $post_types ) || is_post_type_archive( $post_types ) || is_tax( $taxonomies ) );
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
	 * Retrieve the post content from an explanation post.
	 *
	 * @param int|WP_Post $_post Post ID or object for the function, hook, class, or method post
	 *                           to retrieve an explanation field for.
	 * @return string The post content of the explanation.
	 */
	function get_explanation_content( $_post ) {
		global $post;

		// Temporarily remove filter.
		remove_filter( 'the_content', array( 'DevHub_Formatting', 'fix_unintended_markdown' ), 1 );

		// Store original global post.
		$orig = $post;

		// Set global post to the explanation post.
		$post = get_explanation( $_post );

		// Get explanation's raw post content.
		$content = '';
		if (
			! empty( $_GET['wporg_explanations_preview_nonce'] )
		&&
			false !== wp_verify_nonce( $_GET['wporg_explanations_preview_nonce'], 'post_preview_' . $post->ID )
		) {
			$preview = wp_get_post_autosave( $post->ID );

			if ( is_object( $preview ) ) {
				$post = $preview;
				$content = get_post_field( 'post_content', $preview, 'display' );
			}
		} else {
			$content = get_explanation_field( 'post_content', $_post );
		}

		// Pass the content through expected content filters.
		$content = apply_filters( 'the_content', apply_filters( 'get_the_content', $content ) );

		// Restore original global post.
		$post = $orig;

		// Restore filter.
		add_filter( 'the_content', array( 'DevHub_Formatting', 'fix_unintended_markdown' ), 1 );

		return $content;
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

		// Currently only handling private access messages for functions, hooks, and methods.
		if ( ! in_array( get_post_type( $post ), array( 'wp-parser-function', 'wp-parser-hook', 'wp-parser-method' ) ) ) {
			return '';
		}

		$tags = get_post_meta( $post->ID, '_wp-parser_tags', true );

		$access_tags = wp_filter_object_list( $tags, array(
			'name'    => 'access',
			'content' => 'private'
		) );
		$is_private = ! empty( $access_tags );

		if ( ! $is_private ) {
			if ( 'private' === get_post_meta( $post->ID, '_wp-parser_visibility', true ) ) {
				$is_private = true;
			}
		}

		// Bail if it isn't private.
		if ( ! $is_private ) {
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

	/**
	 * Displays a post type filter dropdown on taxonomy pages.
	 *
	 * @return string HTML filter form.
	 */
	function taxonomy_archive_filter() {
		global $wp_rewrite;

		$taxonomies = array( 'wp-parser-since', 'wp-parser-package', 'wp-parser-source-file' );
		$taxonomy   = get_query_var( 'taxonomy' );
		$term       = get_query_var( 'term' );

		if ( ! ( is_tax() && in_array( $taxonomy, $taxonomies ) ) ) {
			return;
		}

		$post_types  = get_parsed_post_types( 'labels' );
		$post_types  = array( 'any' => __( 'Any type', 'wporg' ) ) + $post_types;

		$qv_post_type = array_filter( (array) get_query_var( 'post_type' ) );
		$qv_post_type = $qv_post_type ? $qv_post_type : array( 'any' );

		$options = '';
		foreach ( $post_types as $post_type => $label ) {
			$selected = in_array( $post_type, $qv_post_type ) ? " selected='selected'" : '';
			$options .= "\n\t<option$selected value='" . esc_attr( $post_type ) . "'>$label</option>";
		}

		$form = "<form method='get' class='archive-filter-form' action=''>";

		if ( ! $wp_rewrite->using_permalinks() ) {
			// Add taxonomy and term when not using permalinks.
			$form .= "<input type='hidden' name='" . esc_attr( $taxonomy ) . "' value='" . esc_attr( $term ) . "'>";
		}

		$form .= "<label for='archive-filter'>";
		$form .= __( 'Filter by type:', 'wporg' ) . ' ';
		$form .= '<select name="post_type[]" id="archive-filter">';
		$form .= $options . '</select></label>';
		$form .= "<input class='shiny-blue' type='submit' value='Filter' /></form>";

		echo $form;
	}

	/**
	 * Retrieves all content for reference template parts.
	 *
	 * @return string Template part markup retrieved via output buffering.
	 */
	function get_reference_template_parts() {
		// Order dictates order of display.
		$templates = array(
			'description',
			'params',
			'return',
			'explanation',
			'source',
			'related',
			'methods',
			'changelog',
			'notes'
		);

		ob_start();

		foreach ( $templates as $part ) {
			get_template_part( 'reference/template', $part );
		}

		return ob_get_clean();
	}
}
