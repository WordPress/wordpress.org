<?php
/**
 * Helper that manages and shows the discussions
 *
 * @package gp-translation-helpers
 * @since 0.0.1
 */
class Helper_Translation_Discussion extends GP_Translation_Helper {

	/**
	 * Helper priority.
	 *
	 * @since 0.0.1
	 * @var int
	 */
	public $priority = 0;

	/**
	 * Helper title.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $title = 'Discussion';

	/**
	 * Indicates whether the helper loads asynchronous content or not.
	 *
	 * @since 0.0.1
	 * @var bool
	 */
	public $has_async_content = true;

	/**
	 * Indicates whether the helper should be loaded inline.
	 *
	 * @since 0.0.2
	 * @var bool
	 */
	public $load_inline = true;

	/**
	 * Indicates whether we're currently using a temporary post.
	 *
	 * @since 0.0.2
	 * @var object|null
	 */
	public static $temporary_post = null;

	/**
	 * The post type used to store the comments.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	const POST_TYPE = 'gth_original';

	/**
	 * The comment post status. Creates it as published.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	const POST_STATUS = 'publish';

	/**
	 * The taxonomy key.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	const LINK_TAXONOMY = 'gp_original_id';

	/**
	 *
	 * @since 0.0.1
	 * @var string
	 */
	const URL_SLUG = 'discuss';

	/**
	 *
	 * @since 0.0.1
	 * @var string
	 */
	const ORIGINAL_ID_PREFIX = 'original-';

	/**
	 * Registers the post type, its taxonomy, the comments' metadata and adds a filter to moderate the comments.
	 *
	 * Method executed just after the constructor.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function after_constructor() {
		$this->register_post_type_and_taxonomy();
		add_filter( 'pre_comment_approved', array( $this, 'comment_moderation' ), 10, 2 );
		add_filter( 'comments_open', array( $this, 'comments_open_override' ), 10, 2 );
		add_filter( 'map_meta_cap', array( $this, 'map_comment_meta_caps' ), 10, 4 );
		add_filter( 'user_has_cap', array( $this, 'give_user_read_cap' ), 10, 3 );
		add_filter( 'post_type_link', array( $this, 'rewrite_original_post_type_permalink' ), 10, 2 );
		add_filter( 'comment_reply_link', array( $this, 'comment_reply_link' ), 10, 4 );
		add_filter( 'wp_ajax_create_shadow_post', array( $this, 'ajax_create_shadow_post' ) );
	}

	/**
	 * Registers the post type with its taxonomy and the comments' metadata.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register_post_type_and_taxonomy() {
		register_taxonomy(
			self::LINK_TAXONOMY,
			array(),
			array(
				'public'       => false,
				'show_ui'      => false,
				'rewrite'      => false,
				'capabilities' => array(
					'assign_terms' => 'read',
				),
			)
		);

		$post_type_args = array(
			'supports'          => array( 'comments' ),
			'show_ui'           => false,
			'show_in_menu'      => false,
			'show_in_admin_bar' => false,
			'show_in_nav_menus' => false,
			'can_export'        => false,
			'has_archive'       => false,
			'show_in_rest'      => true,
			'taxonomies'        => array( self::LINK_TAXONOMY ),
			'rewrite'           => false,
		);

		register_post_type( self::POST_TYPE, $post_type_args );

		register_meta(
			'comment',
			'translation_id',
			array(
				'description'       => 'Translation that was displayed when the comment was posted',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( $this, 'sanitize_translation_id' ),
			)
		);

		register_meta(
			'comment',
			'locale',
			array(
				'description'       => 'Locale slug associated with a string comment',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( $this, 'sanitize_comment_locale' ),
				'rewrite'           => false,
			)
		);

		register_meta(
			'comment',
			'translation_status',
			array(
				'description'       => 'Translation status as at when the comment was made',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( $this, 'sanitize_comment_translation_status' ),
				'rewrite'           => false,
			)
		);

		register_meta(
			'comment',
			'comment_topic',
			array(
				'description'       => 'Reason for the comment',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( $this, 'sanitize_comment_topic' ),
				'rewrite'           => false,
			)
		);
	}

	/**
	 * Give subscribers permission to add our comment metas.
	 *
	 * @param      array  $caps     The capabilities they need to have.
	 * @param      string $cap      The capability we're testing for.
	 * @param      int    $user_id  The user id.
	 * @param      array  $args     Other arguments.
	 *
	 * @return     array  The capabilities they need to have.
	 */
	public function map_comment_meta_caps( $caps, $cap, $user_id, $args ) {
		if ( 'edit_comment_meta' === $cap && isset( $args[1] ) && in_array( $args[1], array( 'translation_id', 'locale', 'comment_topic' ), true ) ) {
			return array( 'read' );
		}
		return $caps;
	}

	/**
	 * Ensure that a user has the read capability on translate.wordpress.org.
	 *
	 * @param      array $allcaps  All capabilities of the uer.
	 * @param      array $caps     The capabilities requested.
	 * @param      array $args     Other arguments.
	 *
	 * @return     array  Potentially modified capabilities of the user.
	 */
	public function give_user_read_cap( $allcaps, $caps, $args ) {
		if ( ! defined( 'WPORG_TRANSLATE_BLOGID' ) || get_current_blog_id() !== WPORG_TRANSLATE_BLOGID ) {
			return $allcaps;
		}

		if ( in_array( 'read', $caps, true ) && is_user_logged_in() && ( ! is_admin() || wp_doing_ajax() ) ) {
			$allcaps['read'] = true;
		}

		return $allcaps;
	}

	/**
	 * Gets the permalink and stores in the cache.
	 *
	 * @since 0.0.2
	 *
	 * @param string  $post_link The post's permalink.
	 * @param WP_Post $post      The post in question.
	 *
	 * @return mixed|string
	 */
	public function rewrite_original_post_type_permalink( string $post_link, WP_Post $post ) {
		static $cache = array();

		if ( self::POST_TYPE !== $post->post_type ) {
			return $post_link;
		}

		if ( isset( $cache[ $post->ID ] ) ) {
			return $cache[ $post->ID ];
		}

		// Cache the error case and overwrite it later if we succeed.
		$cache[ $post->ID ] = $post_link;

		$original_id = self::get_original_from_post_id( $post->ID );
		if ( ! $original_id ) {
			return $cache[ $post->ID ];
		}

		$original = GP::$original->get( $original_id );
		if ( ! $original ) {
			return $cache[ $post->ID ];
		}

		$project = GP::$project->get( $original->project_id );
		if ( ! $project ) {
			return $cache[ $post->ID ];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$locale_exists = isset( $_POST['meta']['locale'] ) && ! empty( $this->sanitize_comment_locale( $_POST['meta']['locale'] ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$locale_slug = $locale_exists ? $_POST['meta']['locale'] : null;

		$set_slug = $locale_exists ? 'default' : null;

		// We were able to gather all information, let's put it in the cache.
		$cache[ $post->ID ] = GP_Route_Translation_Helpers::get_permalink( $project->path, $original_id, $set_slug, $locale_slug );

		return $cache[ $post->ID ];
	}

	/**
	 * Enable showing the comment form on non-existing posts.
	 *
	 * @param      boolean $open     Whether the comments are open or not.
	 * @param      int     $post_id  The post id.
	 *
	 * @return     bool    Whether the comments are open or not.
	 */
	public function comments_open_override( $open, $post_id ) {
		if ( self::is_temporary_post_id( $post_id ) ) {
			return true;
		}

		// If we just had to define a temporary post, the post id can also be 0.
		// This is due to a code change in core in this commit:
		// https://github.com/WordPress/WordPress/commit/1069ac4afda821742cf7f600412aacc139013a55
		if ( self::$temporary_post && 0 === $post_id ) {
			return true;
		}
		return $open;
	}

	/**
	 * Updates the comment's approval status before it is set.
	 *
	 * It only updates the approved status if the user has previous translations.
	 *
	 * @since 0.0.1
	 *
	 * @param int|string|WP_Error $approved    The approval status. Accepts 1, 0, 'spam', 'trash',
	 *                                         or WP_Error.
	 * @param array               $commentdata Comment data.
	 *
	 * @return bool|int|string|WP_Error|null
	 */
	public function comment_moderation( $approved, array $commentdata ) {
		global $wpdb;

		// If the comment is already approved, we're good.
		if ( $approved ) {
			return $approved;
		}

		// We only care on comments on our specific post type.
		if ( self::POST_TYPE !== get_post_type( $commentdata['comment_post_ID'] ) ) {
			return $approved;
		}

		// We can't do much if the comment was posted logged out.
		if ( empty( $commentdata['user_id'] ) ) {
			return $approved;
		}

		// If our user has already contributed translations, approve comment.
		$user_current_translations = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->gp_translations WHERE user_id = %s AND status = 'current'", $commentdata['user_id'] ) );
		if ( $user_current_translations ) {
			$approved = true;
		}

		return $approved;
	}

	/**
	 * Gets the slug for the post ID.
	 *
	 * @since 0.0.1
	 *
	 * @param $post_id  The post ID.
	 *
	 * @return false|string
	 */
	public static function get_original_from_post_id( $post_id ) {
		if ( self::is_temporary_post_id( $post_id ) ) {
			return self::get_original_id_from_temporary_post_id( $post_id );
		}

		$terms = wp_get_object_terms( $post_id, self::LINK_TAXONOMY, array( 'number' => 1 ) );
		if ( empty( $terms ) ) {
			return false;
		}

		return $terms[0]->slug;
	}

	/**
	 * Indicates whether the post id is a real one or a temporary one.
	 *
	 * @since 0.0.2
	 *
	 * @param int|string $post_id The post ID.
	 *
	 * @return bool
	 */
	public static function is_temporary_post_id( $post_id ) {
		return self::get_original_id_from_temporary_post_id( $post_id ) > 0;
	}

	/**
	 * Extract the original_id from the temporary post_id.
	 *
	 * @param      int|string $post_id The post ID.
	 *
	 * @return     int|null The original_id or null if the post_id is not a temporary one.
	 */
	public static function get_original_id_from_temporary_post_id( $post_id ) {
		if ( self::POST_TYPE !== substr( $post_id, 0, strlen( self::POST_TYPE ) ) ) {
			return null;
		}
		$original_id = substr( $post_id, strlen( self::POST_TYPE ) );
		if ( is_numeric( $original_id ) && $original_id > 0 ) {
			return intval( $original_id );
		}

		return null;
	}

	/**
	 * Gets the post id of the shadow post and ensure its or create shadow post identifier.
	 *
	 * @param      int $original_id  The original identifier
	 *
	 * @return     <type>  The or create shadow post identifier.
	 */
	public static function get_or_create_shadow_post_id( int $original_id ) {
		return self::get_shadow_post_id( $original_id, true );
	}

	/**
	 * Get a Gth_Temporary_Post or a WP_Post, depending on the given post_id.
	 *
	 * @param      int|string $post_id  The post ID (could be a temporary one).
	 *
	 * @return     WP_Post|Gth_Temporary_Post  An object for use in wp_list_comments.
	 */
	public static function maybe_get_temporary_post( $post_id ) {
		if ( self::is_temporary_post_id( $post_id ) ) {
			self::$temporary_post = new Gth_Temporary_Post( $post_id );
			return self::$temporary_post;
		}

		return get_post( $post_id );
	}

	/**
	 * Gets the post id for the comments and stores it in the cache.
	 *
	 * @since 0.0.1
	 *
	 * @param int  $original_id  The original id for the string to translate. E.g. "2440".
	 * @param bool $create       Whether to create a post if it doesn't exist.
	 *
	 * @return int|WP_Error
	 */
	public static function get_shadow_post_id( int $original_id, $create = false ) {
		$cache_key = self::LINK_TAXONOMY . '_' . $original_id;

		$post_id = wp_cache_get( $cache_key );
		if ( false !== $post_id ) {
			// Something was found in the cache.

			if ( self::is_temporary_post_id( $post_id ) ) {
				// a fake post_id was stored in the cache but we need to create an entry.
				// Let's pretend a cache fail, so that we get a chance to create an entry unless one already exists.
				$post_id = false;
			}
		}

		if ( 'production' !== wp_get_environment_type() || false === $post_id ) {
			$post_id  = null;
			$gp_posts = get_posts(
				array(
					'tax_query'        => array(
						array(
							'taxonomy' => self::LINK_TAXONOMY,
							'terms'    => $original_id,
							'field'    => 'slug',
						),
					),
					'post_type'        => self::POST_TYPE,
					'posts_per_page'   => 1,
					'post_status'      => self::POST_STATUS,
					'suppress_filters' => false,
				)
			);

			if ( ! empty( $gp_posts ) ) {
				$post_id = $gp_posts[0]->ID;
			} elseif ( $create ) {
				$post_id = wp_insert_post(
					array(
						'post_type'      => self::POST_TYPE,
						'tax_input'      => array(
							self::LINK_TAXONOMY => array( strval( $original_id ) ),
						),
						'post_status'    => self::POST_STATUS,
						'post_author'    => 0,
						'comment_status' => 'open',
					)
				);
			} else {
				$post_id = self::POST_TYPE . strval( $original_id );
			}
		}

		wp_cache_set( $cache_key, $post_id );
		return $post_id;
	}

	/**
	 * Gets the comments for the post.
	 *
	 * @since 0.0.1
	 *
	 * @return array
	 */
	public function get_async_content(): array {
		$post_id = self::get_shadow_post_id( $this->data['original_id'] );
		if ( self::is_temporary_post_id( $post_id ) ) {
			return array();
		}

		return get_comments(
			array(
				'post_id'            => $post_id,
				'status'             => 'approve',
				'type'               => 'comment',
				'include_unapproved' => array( get_current_user_id() ),
			)
		);
	}

	/**
	 * Shows the discussion template with the comment form.
	 *
	 * @since 0.0.1
	 *
	 * @param array $comments   The comments to display.
	 *
	 * @return false|string
	 */
	public function async_output_callback( array $comments ) {
		$this->set_count( $comments );

		// Remove comment likes for now (or forever :) ).
		remove_filter( 'comment_text', 'comment_like_button', 12 );

		// Disable subscribe to posts.
		add_filter( 'option_stb_enabled', '__return_false' );

		// Disable subscribe to comments for now.
		add_filter( 'option_stc_disabled', '__return_true' );

		// Link comment author to their profile.
		add_filter(
			'get_comment_author_link',
			function( $return, $author, $comment_id ) {
				$comment = get_comment( $comment_id );
				if ( ! empty( $comment->user_id ) ) {
					$user = get_userdata( $comment->user_id );
					if ( $user ) {
						return gp_link_user( $user );
					}
				}
				return $return;
			},
			10,
			3
		);

		add_filter(
			'comment_form_logged_in',
			function( $logged_in_as, $commenter, $user_identity ) {
				/* translators: Username with which the user is logged in */
				return sprintf( '<p class="logged-in-as">%s</p>', sprintf( __( 'Logged in as %s.' ), $user_identity ) );
			},
			10,
			3
		);

		add_filter(
			'comment_form_fields',
			function( $comment_fields ) {
				$comment_fields['comment'] = str_replace( '</label>', ' (required)</label>', $comment_fields['comment'] );
				return $comment_fields;
			}
		);

		remove_action( 'comment_form_top', 'rosetta_comment_form_support_hint' );

		$post          = self::maybe_get_temporary_post( self::get_shadow_post_id( $this->data['original_id'] ) );
		$mentions_list = apply_filters( 'gp_mentions_list', array(), $comments, $this->data['locale_slug'], $this->data['original_id'] );

		$output = gp_tmpl_get_output(
			'translation-discussion-comments',
			array(
				'comments'             => $comments,
				'post'                 => $post,
				'translation_id'       => isset( $this->data['translation_id'] ) ? $this->data['translation_id'] : null,
				'locale_slug'          => $this->data['locale_slug'],
				'original_permalink'   => $this->data['original_permalink'] ?? false,
				'original_id'          => $this->data['original_id'],
				'project'              => $this->data['project'],
				'translation_set_slug' => $this->data['translation_set_slug'],
				'mentions_list'        => $mentions_list,

			),
			$this->assets_dir . 'templates'
		);
		return $output;
	}

	/**
	 * Gets the content/string to return when a helper has no results.
	 *
	 * @since 0.0.1
	 *
	 * @return false|string
	 */
	public function empty_content() {
		return $this->async_output_callback( array() );
	}

	/**
	 * Gets additional CSS required by the helper.
	 *
	 * @since 0.0.1
	 *
	 * @return bool|string
	 */
	public function get_css() {
		return file_get_contents( $this->assets_dir . 'css/translation-discussion.css' );
	}

	/**
	 * Gets additional JavaScript required by the helper.
	 *
	 * @since 0.0.1
	 *
	 * @return bool|string
	 */
	public function get_js() {
		return file_get_contents( $this->assets_dir . 'js/translation-discussion.js' );
	}

	/**
	 * Sets the comment_topic meta_key as "unknown" if is not in the accepted values.
	 *
	 * Used as sanitize callback in the register_meta for the "comment" object type,
	 * 'comment_topic' meta_key
	 *
	 * @since 0.0.2
	 *
	 * @param string $comment_topic The meta_value for the meta_key "comment_topic".
	 *
	 * @return string
	 */
	public function sanitize_comment_topic( string $comment_topic ): string {
		if ( ! in_array( $comment_topic, array( 'typo', 'context', 'question' ), true ) ) {
			$comment_topic = 'unknown';
		}
		return $comment_topic;

	}

	/**
	 * Sets the comment_topic meta_key as empty ("") if is not in the accepted values.
	 *
	 * Used as sanitize callback in the register_meta for the "comment" object type,
	 * "locale" meta_key
	 *
	 * @since 0.0.2
	 *
	 * @param string $comment_locale     The meta_value for the meta_key "locale".
	 *
	 * @return string
	 */
	public function sanitize_comment_locale( string $comment_locale ): string {
		$gp_locales     = new GP_Locales();
		$all_gp_locales = array_keys( $gp_locales->locales );

		if ( ! in_array( $comment_locale, $all_gp_locales, true ) ) {
			$comment_locale = '';
		}
		return $comment_locale;
	}

	/**
	 * Throws an exception with an error message if the translation id is incorrect.
	 *
	 * Used as sanitize callback in the register_meta for the "comment" object type,
	 * "locale" meta_key
	 *
	 * The string type (input and output) is because it is the type if $translation_id is empty.
	 *
	 * @since 0.0.2
	 *
	 * @param int|string $translation_id   The id for the translation showed when the comment was made.
	 *
	 * @return int|string
	 *
	 * @throws Exception Throws an exception with message if translation_id is invalid.
	 */
	public function sanitize_translation_id( $translation_id ) {
		if ( $translation_id > 0 && ! GP::$translation->get( $translation_id ) ) {
			throw new Exception( 'Invalid translation ID' );
		}
		return $translation_id;
	}

	/**
	 * Sets the translation_status meta_key as "unknown" if is not in the accepted values.
	 *
	 * Used as sanitize callback in the register_meta for the "comment" object type,
	 * 'translation_status' meta_key
	 *
	 * @since 0.0.2
	 *
	 * @param string $translation_status The meta_value for the meta_key "translation_status".
	 *
	 * @return string
	 */
	public function sanitize_translation_status( string $translation_status ): string {
		if ( ! in_array( $translation_status, array( 'approved', 'rejected', 'waiting', 'current', 'fuzzy', 'changesrequested' ), true ) ) {
			$translation_status = 'unknown';
		}
		return $translation_status;

	}

	/**
	 * The comment reply link override.
	 *
	 * @param      string  $link     The link.
	 * @param      array   $args     The arguments.
	 * @param      object  $comment  The comment.
	 * @param      WP_Post $post     The post.
	 *
	 * @return     string  Return the reply link HTML.
	 */
	public function comment_reply_link( $link, $args, $comment, $post ) {
		$data_attributes = array(
			'commentid'      => $comment->comment_ID,
			'postid'         => $post->ID,
			'belowelement'   => $args['add_below'] . '-' . $comment->comment_ID,
			'respondelement' => $args['respond_id'],
			'replyto'        => sprintf( $args['reply_to_text'], $comment->comment_author ),
		);

		if ( get_option( 'page_comments' ) ) {
			$permalink = str_replace( '#comment-' . $comment->comment_ID, '', get_comment_link( $comment ) );
		} else {
			$permalink = get_permalink( $post->ID );
		}

		$data_attribute_string = '';

		foreach ( $data_attributes as $name => $value ) {
			$data_attribute_string .= ' data-' . $name . '="' . esc_attr( $value ) . '"';
		}

		$data_attribute_string = trim( $data_attribute_string );

		$link = sprintf(
			"<a rel='nofollow' class='comment-reply-link button is-primary' href='%s' %s aria-label='%s'>%s</a>",
			esc_url(
				add_query_arg(
					array(
						'replytocom'      => $comment->comment_ID,
						'unapproved'      => false,
						'moderation-hash' => false,
					),
					$permalink
				)
			) . '#' . $args['respond_id'],
			$data_attribute_string,
			esc_attr( sprintf( $args['reply_to_text'], $comment->comment_author ) ),
			$args['reply_text']
		);
		return $args['before'] . $link . $args['after'];
	}

	/**
	 * Ajax callback for creating the shadow post.
	 *
	 * Returns the $post_id back to JS.
	 *
	 * @since 0.0.2
	 */
	public function ajax_create_shadow_post() {
		check_ajax_referer( 'wp_rest', 'nonce' );

		$original_id = self::get_original_id_from_temporary_post_id( $_POST['data']['post'] );
		$post_id     = self::get_or_create_shadow_post_id( $original_id );
		wp_send_json_success( $post_id );
	}

	/**
	 * Throws an exception with an error message if the original id is incorrect.
	 *
	 * Used as callback to validate the original_id passed on updating a string with feedback
	 *
	 * @since 0.0.2
	 *
	 * @param int|string $original_id   The id of the original for the updated translation.
	 *
	 * @return int|string
	 *
	 * @throws Exception Throws an exception with message if original_id is invalid.
	 */
	public function sanitize_original_id( $original_id ) {
		if ( $original_id > 0 && ! GP::$original->get( $original_id ) ) {
			throw new Exception( 'Invalid Original ID' );
		}

		return $original_id;
	}

	/**
	 * Return an array of allowed comment reasons and explanation of each reason.
	 *
	 * @since 0.0.2
	 *
	 * @return array
	 */
	public static function get_comment_reasons( $locale = null ): array {
		$default_reasons = array(
			'style'       => array(
				'name'        => __( 'Style Guide' ),
				'explanation' => __( 'The translation is not following the style guide. It will be interesting to provide a link to the style guide for your locale in the comment.' ),
			),
			'grammar'     => array(
				'name'        => __( 'Grammar' ),
				'explanation' => __( 'The translation has some grammar problems. It will be interesting to provide a link explaining the grammar issue for your locale in the comment.' ),
			),
			'branding'    => array(
				'name'        => __( 'Branding' ),
				'explanation' => __( 'The translation is using incorrectly some brand. E.g. WordPress without the capital P.' ),
			),
			'glossary'    => array(
				'name'        => __( 'Glossary' ),
				'explanation' => __( 'The translation is not using the glossary correctly. It will be interesting to provide some link to the glossary for your locale in the comment.' ),
			),
			'punctuation' => array(
				'name'        => __( 'Punctuation' ),
				'explanation' => __( 'The translation is not using the punctuation marks correctly.' ),
			),
			'typo'        => array(
				'name'        => __( 'Typo' ),
				'explanation' => __( 'The translation has a typo. E.g., it is using the \'apostrope\' word instead of \'apostrophe\'.' ),
			),
		);
		$reasons         = apply_filters( 'gp_custom_reasons', $default_reasons, $locale );
		return $reasons;
	}
}

/**
 * Gets the slug for the post ID.
 *
 * @since 0.0.1
 *
 * @param int $post_id  The id of the post.
 *
 * @return false|string
 */
function gth_discussion_get_original_id_from_post( int $post_id ) {
	return Helper_Translation_Discussion::get_original_from_post_id( $post_id );
}

/**
 * Print a (linked) translation.
 *
 * @param      int    $comment_translation_id  The comment translation identifier.
 * @param      array  $args                    The arguments.
 * @param      string $prefix                  The prefix text.
 */
function gth_print_translation( $comment_translation_id, $args, $prefix = '' ) {
	static $cache = array();
	if ( ! isset( $cache[ $comment_translation_id ] ) ) {
		$cache[ $comment_translation_id ] = GP::$translation->get( $comment_translation_id );
	}
	$translation           = $cache[ $comment_translation_id ];
	$translation_permalink = GP_Route_Translation_Helpers::get_translation_permalink(
		$args['project'],
		$args['locale_slug'],
		$args['translation_set_slug'],
		$args['original_id'],
		$comment_translation_id
	);
	?>
			<em>
			<?php
			echo esc_html( $prefix );
			if ( $translation_permalink ) {
				echo wp_kses( gp_link( $translation_permalink, $translation->translation_0 ), array( 'a' => array( 'href' => true ) ) );
			} else {
				echo esc_html( $translation->translation_0 );
			}
			?>
			</em>
		<?php
}
/**
 * Callback for the wp_list_comments() function in the helper-translation-discussion.php template.
 *
 * @since 0.0.1
 *
 * @param WP_Comment $comment   The comment object.
 * @param array      $args      Formatting options.
 * @param int        $depth     The depth of the new comment.
 *
 * @return void
 */
function gth_discussion_callback( WP_Comment $comment, array $args, int $depth ) {
	$GLOBALS['comment'] = $comment;// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	$is_linking_comment = preg_match( '!^' . home_url( gp_url() ) . '[a-z0-9_/#-]+$!i', $comment->comment_content );

	$comment_locale         = get_comment_meta( $comment->comment_ID, 'locale', true );
	$current_locale         = $args['locale_slug'];
	$current_translation_id = $args['translation_id'];
	$comment_translation_id = get_comment_meta( $comment->comment_ID, 'translation_id', true );

	$comment_reason = get_comment_meta( $comment->comment_ID, 'reject_reason', true );

	$_translation_status = get_comment_meta( $comment->comment_ID, 'translation_status', true );

	$classes = array( 'comment-locale-' . $comment_locale );
	if ( ! empty( $comment_reason ) ) {
		$classes[] = 'rejection-feedback';
		$classes[] = 'rejection-feedback-' . $comment_locale;
	}
	?>
	<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
	<article id="comment-<?php comment_ID(); ?>" class="comment">
	<div class="comment-avatar">
	<?php echo get_avatar( $comment, 25 ); ?>
	</div><!-- .comment-avatar -->
	<?php printf( '<cite class="fn">%s</cite>', get_comment_author_link( $comment->comment_ID ) ); ?>
	<a href="<?php echo esc_url( get_comment_link( $comment->comment_ID ) ); ?>">
	<?php
	// Older than a week, show date; otherwise show __ time ago.
	if ( time() - get_comment_time( 'U', true ) > 604800 ) {
		/* translators: 1: Date , 2: Time */
		$time = sprintf( _x( '%1$s at %2$s', '1: date, 2: time' ), get_comment_date(), get_comment_time() );
	} else {
		/* translators: Human readable time difference */
		$time = sprintf( __( '%1$s ago' ), human_time_diff( get_comment_time( 'U' ), time() ) );
	}
	echo '<time datetime=" ' . get_comment_time( 'c' ) . '">' . esc_html( $time ) . '</time>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	</a>
	<?php if ( $comment_locale ) : ?>
	<div class="comment-locale">Locale:
				<?php if ( ! $current_locale ) : ?>
					<a href="<?php echo esc_attr( $comment_locale . '/default' ); ?>"><?php echo esc_html( $comment_locale ); ?></a>
				<?php elseif ( $current_locale && $current_locale !== $comment_locale ) : ?>
					<a href="<?php echo esc_attr( '../../' . $comment_locale . '/default' ); ?>"><?php echo esc_html( $comment_locale ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $comment_locale ); ?>
	<?php endif; ?>
	</div>
	<?php endif; ?>
	<div class="comment-content" dir="auto">
		<?php
		if ( $is_linking_comment ) :
			$linked_comment = $comment->comment_content;
			$parts          = wp_parse_url( $linked_comment );
			$parts['path']  = rtrim( $parts['path'], '/' );
			$path_parts     = explode( '/', $parts['path'] );

			$linking_comment_set_slug = array_pop( $path_parts );
			$linking_comment_locale   = array_pop( $path_parts );
			if ( $current_locale && $current_locale !== $linking_comment_locale ) {
				$linked_comment = str_replace( $parts['path'], $parts['path'] . '/' . $current_locale . '/default', $linked_comment );
			}

			if ( $comment_reason ) :
				?>
				<p>The translation <?php gth_print_translation( $comment_translation_id, $args ); ?> <a href="<?php echo esc_url( $linked_comment ); ?>"><?php esc_html_e( 'is being discussed here' ); ?></a>.</p>
			<?php else : ?>
				<a href="<?php echo esc_url( $linked_comment ); ?>"><?php esc_html_e( 'Please continue the discussion here' ); ?></a>
			<?php endif; ?>
		<?php else : ?>
			<?php comment_text(); ?>
			<?php if ( $comment_reason ) : ?>
			<p>
				<?php echo esc_html( _n( 'Comment Reason: ', 'Comment Reasons: ', count( $comment_reason ) ) ); ?>
				<?php
				$number_of_items = count( $comment_reason );
				$counter         = 0;
				$comment_reasons = Helper_Translation_Discussion::get_comment_reasons( $comment_locale );
				foreach ( $comment_reason as $reason ) {
					echo wp_kses(
						sprintf(
						/* translators: 1: Title with the explanation of the comment reason , 2: The comment reason */
							__( '<span title="%1$s" class="tooltip">%2$s</span> <span class="tooltip dashicons dashicons-info" title="%1$s"></span>', 'glotpress' ),
							$comment_reasons[ $reason ]['explanation'],
							$comment_reasons[ $reason ]['name'],
						),
						array(
							'span' => array(
								'class' => array(),
								'title' => array(),
							),
						)
					);

					if ( ++$counter < $number_of_items ) {
						echo ', ';
					}
				}
				?>
			</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<footer>
	<div class="comment-author vcard">
			<?php
			if ( $comment->comment_parent ) {
				printf(
					'<a href="%1$s">%2$s</a>',
					esc_url( get_comment_link( $comment->comment_parent ) ),
					/* translators: The author of the current comment */
					sprintf( esc_attr( __( 'in reply to %s' ) ), esc_html( get_comment_author( $comment->comment_parent ) ) )
				);
			}
			if ( $is_linking_comment ) {
				?>
				<span class="alignright">
					<a href="<?php echo esc_url( $comment->comment_content ); ?>"><?php esc_html_e( 'Reply' ); ?></a>
				</span>
				<?php
			} else {
				comment_reply_link(
					array_merge(
						$args,
						array(
							'depth'     => $depth,
							'max_depth' => $args['max_depth'],
							'before'    => '<span class="alignright">',
							'after'     => '</span>',
						)
					)
				);
			}
			?>
			</div><!-- .comment-author .vcard -->
			<?php
			if ( '0' === $comment->comment_approved ) {
				?>
				<p><em><?php esc_html_e( 'Your comment is awaiting moderation.' ); ?></em></p>
				<?php
			}

			if ( $comment_translation_id && $comment_translation_id !== $current_translation_id ) {
				$translation_status = '';
				if ( $_translation_status ) {

					$translation_status = ( is_array( $_translation_status ) && array_key_exists( $comment_translation_id, $_translation_status ) ) ? '(' . $_translation_status[ $comment_translation_id ] . ')' : ' (' . $_translation_status[0] . ')';
				}
				gth_print_translation( $comment_translation_id, $args, 'Translation' . $translation_status . ': ' );
			}
			if ( ! $is_linking_comment ) :

				?>
				<div id="comment-reply-<?php echo esc_attr( $comment->comment_ID ); ?>" style="display: none;">
				<?php
				if ( is_user_logged_in() ) {
					comment_form(
						array(
							'title_reply'         => esc_html__( 'Discuss this string' ),
							/* translators: username */
							'title_reply_to'      => esc_html__( 'Reply to %s' ),
							'title_reply_before'  => '<h5 id="reply-title" class="discuss-title">',
							'title_reply_after'   => '</h5>',
							'id_form'             => 'commentform-' . $comment->comment_post_ID,
							'cancel_reply_link'   => '<span></span>',
							'comment_notes_after' => implode(
								"\n",
								array(
									'<input type="hidden" name="comment_parent" value="' . esc_attr( $comment->comment_ID ) . '" />',
									'<input type="hidden" name="comment_locale" value="' . esc_attr( $args['locale_slug'] ) . '" />',
									'<input type="hidden" name="translation_id" value="' . esc_attr( $args['translation_id'] ) . '" />',
									'<input type="hidden" name="redirect_to" value="' . esc_url( $args['original_permalink'] ) . '" />',
								)
							),
						),
						$comment->comment_post_ID
					);
				} else {
					/* translators: Log in URL. */
					echo sprintf( __( 'You have to be <a href="%s">logged in</a> to comment.' ), esc_html( wp_login_url() ) );
				}
				?>
			</div>
			<?php endif; ?>
		</footer>
	</article><!-- #comment-## -->
</li>
	<?php
}

