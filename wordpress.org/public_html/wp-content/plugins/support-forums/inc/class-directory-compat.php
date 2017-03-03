<?php

namespace WordPressdotorg\Forums;

abstract class Directory_Compat {

	abstract protected function compat();
	abstract protected function compat_views();
	abstract protected function compat_title();
	abstract protected function reviews_title();
	abstract protected function active_title();
	abstract protected function unresolved_title();
	abstract protected function slug();
	abstract protected function title();
	abstract protected function forum_id();
	abstract protected function query_var();
	abstract protected function taxonomy();
	abstract protected function parse_query();
	abstract protected function do_view_sidebar();
	abstract protected function do_topic_sidebar();
	abstract protected function do_view_header();

	var $loaded       = false;
	var $authors      = null;
	var $contributors = null;
	var $query        = null;
	var $term         = null;

	public function init() {
		if ( defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) && get_current_blog_id() == WPORG_SUPPORT_FORUMS_BLOGID ) {
			// Intercept feed requests prior to bbp_request_feed_trap.
			add_filter( 'bbp_request', array( $this, 'request' ), 9 );

			// Add plugin or theme name to view feed titles.
			add_action( 'bbp_feed', array( $this, 'add_compat_title_to_feed' ) );

			// Define the taxonomy and query vars for this view.
			add_action( 'plugins_loaded', array( $this, 'always_load' ) );

			// We have to add the custom view before bbPress runs its own action
			// on parse_query at priority 2.
			add_action( 'parse_query', array( $this, 'parse_query' ), 0 );

			// And this still needs to happen before priority 2.
			add_action( 'parse_query', array( $this, 'maybe_load' ), 1 );

			// Check to see if an individual topic is compat.
			add_action( 'wp', array( $this, 'check_topic_for_compat' ) );

			// Always check to see if a topic title needs a compat prefix.
			add_filter( 'bbp_get_topic_title', array( $this, 'get_topic_title' ), 9, 2 );

			// Always check to see if a new topic is being posted; this must run
			// before subscriptions go out for `bbp_new_topic` at priority 10.
			add_action( 'bbp_new_topic', array( $this, 'new_topic' ), 9, 4 );

			// Remove new topic form at the bottom of reviews forum.
			add_filter( 'bbp_get_template_part', array( $this, 'noop_reviews_forum_form_topic' ), 10, 3 );
		}
	}

	/**
	 * Handle view feeds for this compat.
	 */
	public function request( $query_vars ) {
		// Redirect some older URLs to the correct location. This can be
		// removed once nginx rules are in place to handle them.
		$redirects = array(
			// RSS: https://wordpress.org/support/rss/plugin/akismet/
			'rss' => 'rss/' . $this->compat() . '/',
			// Reviews: https://wordpress.org/support/view/plugin-reviews/akismet/
			'reviews' => 'view/' . $this->compat() . '-reviews/',
			// Reviews RSS: https://wordpress.org/support/rss/view/plugin-reviews/akismet/
			'reviews_rss' => 'rss/view/' . $this->compat() . '-reviews/',
		);
		if ( array_key_exists( 'pagename', $query_vars ) ) {
			$pagename = $query_vars['pagename'];

			foreach ( $redirects as $r => $base ) {
				$url = false;
				if ( 0 !== strpos( $pagename, $base ) ) {
					continue;
				}
				$ending = str_replace( $base, '', $pagename );
				$slug = explode( '/', $ending );
				if ( $slug ) {
					switch ( $r ) {
						case 'rss' :
							$url = sprintf( home_url( '/%s/%s/feed/' ),
								$this->compat(),
									sanitize_key( $slug[0] ) );
							break;
						case 'reviews' :
							$url = sprintf( home_url( '/%s/%s/reviews/' ),
								$this->compat(),
								sanitize_key( $slug[0] ) );
							break;
						case 'reviews_rss' :
							$url = sprintf( home_url( '/%s/%s/reviews/feed/' ),
								$this->compat(),
								sanitize_key( $slug[0] ) );
					}
					if ( $url ) {
						wp_safe_redirect( esc_url( $url ), 301 );
						exit;
					}
				}
			}
		}

		if ( isset( $query_vars['feed'] ) && isset( $query_vars[ $this->query_var() ] ) ) {

			// Compat views are hooked in a special order, and need help with feed queries.
			if ( isset( $query_vars['bbp_view'] ) && in_array( $query_vars['bbp_view'], $this->compat_views() ) ) {
				$this->query = $query_vars;
				add_filter( 'bbp_get_view_query_args', array( $this, 'get_view_query_args_for_feed' ), 10, 2 );

				// Override bbPress topic pubDate handling to show topic time and not last active time
				add_filter( 'get_post_metadata', array( $this, 'topic_pubdate_correction_for_feed' ), 10, 4 );
			}
		}
		return $query_vars;
	}

	public function topic_pubdate_correction_for_feed( $value, $object_id, $meta_key, $single ) {
		// We only care about _bbp_last_active_time in this particular context
		if ( $meta_key == '_bbp_last_active_time' ) {
			$value = get_post_time( 'Y-m-d H:i:s', true, $object_id );
		}
		return $value;
	}

	/**
	 * Add plugin or theme name to view feed titles.
	 *
	 * bbPress uses 'All Topics' title for view feeds, which isn't useful when
	 * dealing with plugin or theme support.
	 *
	 * @see https://meta.trac.wordpress.org/ticket/2078
	 * @see https://bbpress.trac.wordpress.org/ticket/3064
	 */
	public function add_compat_title_to_feed() {
		if ( empty( $this->query['bbp_view'] ) || empty( $this->query[ $this->query_var() ] ) ) {
			return;
		}

		add_filter( 'gettext', array( $this, 'title_correction_for_feed' ), 10, 3 );
	}

	/**
	 * Replace 'All Topics' feed title with an appropriate view title
	 * that includes the plugin or theme name.
	 *
	 * @see https://meta.trac.wordpress.org/ticket/2078
	 * @see https://bbpress.trac.wordpress.org/ticket/3064
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Text to translate.
	 * @param string $domain      Text domain.
	 * @return string New feed title.
	 */
	public function title_correction_for_feed( $translation, $text, $domain ) {
		if ( 'bbpress' !== $domain || 'All Topics' !== $text ) {
			return $translation;
		}

		remove_filter( 'gettext', array( $this, 'title_correction_for_feed' ), 10, 3 );

		$object = $this->get_object( $this->query[ $this->query_var() ] );
		if ( ! $object ) {
			return $translation;
		}

		$this->{$this->compat()} = $object;

		switch ( $this->query['bbp_view'] ) {
			case $this->compat():
				$translation = $this->compat_title();
				break;
			case 'reviews':
				$translation = $this->reviews_title();
				break;
			case 'active':
				$translation = $this->active_title();
				break;
			case 'unresolved':
				$translation = $this->unresolved_title();
				break;
		}

		return $translation;
	}

	public function get_view_query_args_for_feed( $retval, $view ) {
		switch ( $this->query['bbp_view'] ) {
			// Return new topics from the support forum.
			case $this->compat() :
				return array(
					'post_parent'    => $this->forum_id(),
					'tax_query'      => array( array(
						'taxonomy'   => $this->taxonomy(),
						'field'      => 'slug',
						'terms'      => $this->query[ $this->query_var() ],
					) ),
					'show_stickies'  => false,
					'orderby'        => 'ID',
				);
				break;

			// Return new topics from the reviews forum.
			case 'reviews' :
				return array(
					'post_parent'    => Plugin::REVIEWS_FORUM_ID,
					'tax_query'      => array( array(
						'taxonomy'   => $this->taxonomy(),
						'field'      => 'slug',
						'terms'      => $this->query[ $this->query_var() ],
					) ),
					'show_stickies'  => false,
					'orderby'        => 'ID',
				);
				break;

			// Return active topics from the support forum.
			case 'active' :
				return array(
					'post_parent'    => $this->forum_id(),
					'post_status'    => 'publish',
					'tax_query'      => array( array(
						'taxonomy'   => $this->taxonomy(),
						'field'      => 'slug',
						'terms'      => $this->query[ $this->query_var() ],
					) ),
					'show_stickies'  => false,
				);
				break;

			// Return unresolved topics from the support forum.
			case 'unresolved' :
				return array(
					'post_parent'    => $this->forum_id(),
					'post_status'    => 'publish',
					'tax_query'      => array( array(
						'taxonomy'   => $this->taxonomy(),
						'field'      => 'slug',
						'terms'      => $this->query[ $this->query_var() ],
					) ),
					'meta_key'      => 'topic_resolved',
					'meta_type'     => 'CHAR',
					'meta_value'    => 'no',
					'meta_compare'  => '=',
					'show_stickies'  => false,
					'orderby'        => 'ID',
				);
				break;
		}
		return $retval;
	}

	public function always_load() {
		// Add filters necessary for determining which compat file to use.
		add_action( 'bbp_init',              array( $this, 'register_taxonomy' ) );
		add_filter( 'query_vars',            array( $this, 'add_query_var' ) );
		add_action( 'bbp_add_rewrite_rules', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'term_link',             array( $this, 'get_term_link' ), 10, 3 );
	}

	/**
	 * At this point, compat, slug, and object should be loaded.
	 */
	public function maybe_load() {
		if ( false !== $this->slug() && false == $this->loaded ) {
			// This must run before bbPress's parse_query at priority 2.
			$this->register_views();

			// Set the term for this view so we can reuse it.
			$this->term = get_term_by( 'slug', $this->slug(), $this->taxonomy() );

			// Add plugin- and theme-specific filters and actions.
			add_action( 'wporg_compat_view_sidebar', array( $this, 'do_view_sidebar' ) );
			add_action( 'wporg_compat_before_single_view', array( $this, 'do_view_header' ) );
			add_action( 'wporg_compat_before_single_view', array( $this, 'do_subscription_link' ), 11 );

			// Add output filters and actions.
			add_filter( 'bbp_get_view_link', array( $this, 'get_view_link' ), 10, 2 );
			add_filter( 'bbp_breadcrumbs',   array( $this, 'breadcrumbs' ) );

			// Handle new topic form at the bottom of support view.
			add_action( 'wporg_compat_after_single_view',      array( $this, 'add_topic_form' ) );
			add_action( 'bbp_theme_before_topic_form_content', array( $this, 'add_topic_form_content' ) );
			add_filter( 'bbp_current_user_can_access_create_topic_form', array( $this, 'current_user_can_access_create_topic_form' ) );

			// Instantiate WPORG_Ratings compat mode for reviews.
			if ( class_exists( 'WPORG_Ratings' ) && class_exists( 'WordPressdotorg\Forums\Ratings_Compat' ) ) {
				$this->ratings = new Ratings_Compat( $this->compat(), $this->slug(), $this->taxonomy(), $this->get_object( $this->slug() ) );
			}

			// Instantiate WPORG_Stickies mode for support view.
			if ( class_exists( 'WordPressdotorg\Forums\Stickies_Compat' ) ) {
				$this->stickies = new Stickies_Compat( $this->compat(), $this->slug(), $this->taxonomy(), $this->get_object( $this->slug() ), $this->term );
			}

			$this->loaded = true;
		}
	}

	public function check_topic_for_compat() {
		if ( ( bbp_is_single_topic() || bbp_is_topic_edit() ) && false == $this->loaded ) {
			$terms = get_the_terms( bbp_get_topic_id(), $this->taxonomy() );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$slug = $terms[0]->slug;

				// Basic setup.
				$this->slug              = $slug;
				$this->{$this->compat()} = $this->get_object( $slug );
				$this->authors           = $this->get_authors( $slug );
				$this->contributors      = $this->get_contributors( $slug );
				$this->term              = $terms[0];

				// Add output filters and actions.
				add_action( 'wporg_compat_single_topic_sidebar_pre', array( $this, 'do_topic_sidebar' ) );

				// Handle topic resolution permissions.
				add_filter( 'wporg_bbp_user_can_resolve', array( $this, 'user_can_resolve' ), 10, 3 );

				// Instantiate WPORG_Ratings compat mode for reviews.
				if ( class_exists( 'WPORG_Ratings' ) && class_exists( 'WordPressdotorg\Forums\Ratings_Compat' ) ) {
					$this->ratings = new Ratings_Compat( $this->compat(), $this->slug(), $this->taxonomy(), $this->get_object( $this->slug() ) );
				}

				// Instantiate WPORG_Stickies mode for topic view.
				if ( class_exists( 'WordPressdotorg\Forums\Stickies_Compat' ) ) {
					$this->stickies = new Stickies_Compat( $this->compat(), $this->slug(), $this->taxonomy(), $this->get_object( $this->slug() ), $this->term, $this->authors, $this->contributors );
				}

				$this->loaded = true;
			}
		}
	}

	/**
	 * Allow plugin/theme authors and contributors to resolve a topic on their support forum.
	 *
	 * @param bool $retval If the user can set a topic resolution for the topic
	 * @param int $user_id The user id
	 * @param int $topic_id The topic id
	 * @return bool True if the user can set the topic resolution, otherwise false
	 */
	public function user_can_resolve( $retval, $user_id, $topic_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return $retval;
		}
		if (
			( ! empty( $this->authors ) && in_array( $user->user_login, $this->authors ) )
		||
			( ! empty( $this->contributors ) && in_array( $user->user_login, $this->contributors ) )
		) {
			$retval = true;
		}
		return $retval;
	}

	public function get_topic_title( $title, $topic_id ) {
		if (
			( bbp_is_single_forum() && bbp_get_forum_id() == $this->forum_id() )
		||
			( bbp_is_single_forum() && Plugin::REVIEWS_FORUM_ID == bbp_get_forum_id() )
		||
			( bbp_is_single_view() && ! in_array( bbp_get_view_id(), $this->compat_views() ) )
		) {
			$terms = get_the_terms( $topic_id, $this->taxonomy() );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$term = $terms[0];
				$object = $this->get_object( $term->slug );
				$title = sprintf( "[%s] %s", esc_html( $object->post_title ), esc_html( $title ) );
			}
		}
		return $title;
	}

	public function add_rewrite_rules() {
		$priority          = 'top';

		$root_id           = $this->compat();
		$root_var          = $this->query_var();
		$review_id         = 'reviews';
		$active_id         = 'active';
		$unresolved_id     = 'unresolved';

		$support_rule      = $this->compat() . '/([^/]+)/';
		$reviews_rule      = $this->compat() . '/([^/]+)/' . $review_id . '/';
		$active_rule       = $this->compat() . '/([^/]+)/' . $active_id . '/';
		$unresolved_rule   = $this->compat() . '/([^/]+)/' . $unresolved_id . '/';

		$feed_id    = 'feed';
		$view_id    = bbp_get_view_rewrite_id();
		$paged_id   = bbp_get_paged_rewrite_id();

		$feed_slug  = 'feed';
		$paged_slug = bbp_get_paged_slug();

		$base_rule  = '?$';
		$feed_rule  = $feed_slug  . '/?$';
		$paged_rule = $paged_slug . '/?([0-9]{1,})/?$';

		// Add reviews view rewrite rules.
		add_rewrite_rule( $reviews_rule . $base_rule,  'index.php?' . $view_id . '=' . $review_id . '&' . $root_var . '=$matches[1]',                               $priority );
		add_rewrite_rule( $reviews_rule . $paged_rule, 'index.php?' . $view_id . '=' . $review_id . '&' . $root_var . '=$matches[1]&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $reviews_rule . $feed_rule,  'index.php?' . $view_id . '=' . $review_id . '&' . $root_var . '=$matches[1]&' . $feed_id  . '=$matches[2]', $priority );

		// Add support view rewrite rules.
		add_rewrite_rule( $support_rule . $base_rule,  'index.php?' . $view_id . '=' . $root_id . '&' . $root_var . '=$matches[1]',                               $priority );
		add_rewrite_rule( $support_rule . $paged_rule, 'index.php?' . $view_id . '=' . $root_id . '&' . $root_var . '=$matches[1]&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $support_rule . $feed_rule,  'index.php?' . $view_id . '=' . $root_id . '&' . $root_var . '=$matches[1]&' . $feed_id  . '=$matches[2]', $priority );

		// Add active view rewrite rules.
		add_rewrite_rule( $active_rule . $base_rule,  'index.php?' . $view_id . '=' . $active_id . '&' . $root_var . '=$matches[1]',                               $priority );
		add_rewrite_rule( $active_rule . $paged_rule, 'index.php?' . $view_id . '=' . $active_id . '&' . $root_var . '=$matches[1]&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $active_rule . $feed_rule,  'index.php?' . $view_id . '=' . $active_id . '&' . $root_var . '=$matches[1]&' . $feed_id  . '=$matches[2]', $priority );

		// Add unresolved view rewrite rules.
		add_rewrite_rule( $unresolved_rule . $base_rule,  'index.php?' . $view_id . '=' . $unresolved_id . '&' . $root_var . '=$matches[1]',                               $priority );
		add_rewrite_rule( $unresolved_rule . $paged_rule, 'index.php?' . $view_id . '=' . $unresolved_id . '&' . $root_var . '=$matches[1]&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $unresolved_rule . $feed_rule,  'index.php?' . $view_id . '=' . $unresolved_id . '&' . $root_var . '=$matches[1]&' . $feed_id  . '=$matches[2]', $priority );
	}

	public function add_query_var( $query_vars ) {
		$query_vars[] = $this->query_var();
		return $query_vars;
	}

	public function register_taxonomy() {
		if ( post_type_exists( 'topic' ) ) {
			register_taxonomy( $this->taxonomy(), 'topic', array( 'public' => false ) );
		}
	}

	public function register_views() {

		// Add support view.
		bbp_register_view(
			$this->compat(),
			$this->compat_title(),
			array(
				'post_parent'   => $this->forum_id(),
				'tax_query'     => array( array(
					'taxonomy'  => $this->taxonomy(),
					'field'     => 'slug',
					'terms'     => $this->slug(),
				) ),
				'show_stickies' => true,
				'meta_key'      => null,
				'meta_compare'  => null,
				'orderby'       => 'ID',
			)
		);

		// Add reviews view.
		bbp_register_view(
			'reviews',
			$this->reviews_title(),
			array(
				'post_parent'   => Plugin::REVIEWS_FORUM_ID,
				'tax_query'     => array( array(
					'taxonomy'  => $this->taxonomy(),
					'field'     => 'slug',
					'terms'     => $this->slug(),
				) ),
				'show_stickies' => false,
				'meta_key'      => null,
				'meta_compare'  => null,
				'orderby'       => 'ID',
			)
		);

		// Add recent activity view.
		bbp_register_view(
			'active',
			$this->active_title(),
			array(
				'post_parent'   => $this->forum_id(),
				'post_status'   => 'publish',
				'tax_query'     => array( array(
					'taxonomy'  => $this->taxonomy(),
					'field'     => 'slug',
					'terms'     => $this->slug(),
				) ),
				'show_stickies' => false,
			)
		);

		// Add unresolved topics view.
		bbp_register_view(
			'unresolved',
			$this->unresolved_title(),
			array(
				'post_parent'   => $this->forum_id(),
				'post_status'   => 'publish',
				'tax_query'     => array( array(
					'taxonomy'  => $this->taxonomy(),
					'field'     => 'slug',
					'terms'     => $this->slug(),
				) ),
				'meta_key'      => 'topic_resolved',
				'meta_type'     => 'CHAR',
				'meta_value'    => 'no',
				'meta_compare'  => '=',
				'orderby'       => 'ID',
				'show_stickies' => false,
			)
		);
	}

	/**
	 * Filter view links to provide prettier links for these subforum views.
	 */
	public function get_view_link( $url, $view ) {
		global $wp_rewrite;

		$view = bbp_get_view_id( $view );
		if ( ! in_array( $view, $this->compat_views() ) ) {
			return $url;
		}

		// Pretty permalinks.
		if ( $wp_rewrite->using_permalinks() ) {
			switch ( $view ) {
				case 'reviews' :
				case 'active' :
				case 'unresolved' :
					$url = $wp_rewrite->root . $this->compat() . '/' . $this->slug() . '/' . $view;
					break;

				default :
					$url = $wp_rewrite->root . $this->compat() . '/' . $this->slug();
			}
			$url = home_url( user_trailingslashit( $url ) );

		// Unpretty permalinks.
		} else {
			$url = add_query_arg( array(
				bbp_get_view_rewrite_id() => $view,
				$this->query_var()        => $this->slug(),
			) );
		}

		return $url;
	}

	/**
	 * Filter the breadcrumbs for directory views so we can specify the plugin
	 * or theme in the breadcrumbs.
	 */
	public function breadcrumbs( $r ) {
		if ( ! bbp_is_single_view() ) {
			return $r;
		}

		$view = bbp_get_view_id();
		if ( ! in_array( $view, $this->compat_views() ) ) {
			return $r;
		}

		// Prefix link to plugin/theme support or review forum with context.
		if ( 'plugin' === $this->compat() ) {
			/* translators: %s: link to plugin support or review forum */
			$compat_breadcrumb = __( 'Plugin: %s', 'wporg-forums' );
		} else {
			/* translators: %s: link to theme support or review forum */
			$compat_breadcrumb = __( 'Theme: %s', 'wporg-forums' );
		}

		$r[1] = sprintf( $compat_breadcrumb, esc_html( $this->title() ) );

		if ( in_array( $view, array( 'reviews', 'active', 'unresolved' ) ) ) {
			$r[1] = sprintf( $compat_breadcrumb, sprintf(
				'<a href="%s" class="bbp-breadcrumb-forum">%s</a>',
				esc_url( bbp_get_view_url( $this->compat() ) ),
				esc_html( $this->title() )
			) );
			if ( 'reviews' == $view ) {
				$r[2] = __( 'Reviews', 'wporg-forums' );
			} elseif ( 'active' == $view ) {
				$r[2] = __( 'Active Topics', 'wporg-forums' );
			} else {
				$r[2] = __( 'Unresolved Topics', 'wporg-forums' );
			}
		}
		return $r;
	}

	/**
	 * Add the new topic form at the bottom of appropriate views; the reviews view
	 * form addition is handled by Ratings_Compat.
	 */
	public function add_topic_form() {
		if ( ! bbp_is_single_view() ) {
			return;
		}

		$view = bbp_get_view_id();
		if ( ! in_array( $view, array( $this->compat() ) ) ) {
			return;
		}

		bbp_get_template_part( 'form', 'topic' );
	}

	public function add_topic_form_content() {
		if ( ! bbp_is_single_view() ) {
			return;
		}

		$view = bbp_get_view_id();
		if ( ! in_array( $view, array( $this->compat(), 'reviews' ) ) ) {
			return;
		}

		if ( 'reviews' == $view ) {
			$forum_id = Plugin::REVIEWS_FORUM_ID;
		} else {
			$forum_id = $this->forum_id();
		}
		?>
		<input type="hidden" name="bbp_forum_id" id="bbp_forum_id" value="<?php echo esc_attr( $forum_id ); ?>" />
		<input type="hidden" name="wporg_compat" id="wporg_compat" value="<?php echo esc_attr( $this->compat() ); ?>" />
		<input type="hidden" name="wporg_compat_slug" id="wporg_compat_slug" value="<?php echo esc_attr( $this->slug() ); ?>" />
		<?php
	}

	public function current_user_can_access_create_topic_form( $retval ) {
		if ( bbp_is_single_view() && in_array( bbp_get_view_id(), array( $this->compat(), 'reviews' ) ) ) {
			$retval = bbp_current_user_can_publish_topics();
		}
		return $retval;
	}

	/**
	 * Filter the template fetch to avoid displaying a new topic form in the reviews forum.
	 *
	 * @param array $templates The templates to load
	 * @param string $slug The template slug
	 * @param string $name The template name
	 * @return array|false The templates, or false if nooped
	 */
	public function noop_reviews_forum_form_topic( $templates, $slug, $name ) {
		if (
			'form' == $slug && 'topic' == $name
		&&
			bbp_is_single_forum() && Plugin::REVIEWS_FORUM_ID == bbp_get_forum_id()
		) {
			return false;
		}
		return $templates;
	}

	/**
	 * Add a subscribe/unsubscribe link to the compat views.
	 */
	public function do_subscription_link() {
		if ( ! class_exists( 'WordPressdotorg\Forums\Term_Subscription\Plugin' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! bbp_is_single_view() || bbp_get_view_id() != $this->compat() ) {
			return;
		}

		do_action( 'bbp_template_notices' );

		$term_subscription = '';
		$term = $this->term;
		if ( ! $term ) {
			// New compats won't have any support topics or reviews, so will
			// not yet exist as a compat term.
			$term = wp_insert_term( $this->slug(), $this->taxonomy() );
			$term = get_term( $term['term_id'] );
		}
		if ( $term ) {
			$this->term = $term;
			$subscribe = $unsubscribe = '';
			if ( 'plugin' == $this->compat() ) {
				$subscribe   = esc_html__( 'Subscribe to this plugin', 'wporg-forums' );
				$unsubscribe = esc_html__( 'Unsubscribe from this plugin', 'wporg-forums' );
			} else {
				$subscribe   = esc_html__( 'Subscribe to this theme', 'wporg-forums' );
				$unsubscribe = esc_html__( 'Unsubscribe from this theme', 'wporg-forums' );
			}
			$term_subscription = Term_Subscription\Plugin::get_subscription_link( array(
				'term_id'     => $term->term_id,
				'taxonomy'    => $this->taxonomy(),
				'subscribe'   => $subscribe,
				'unsubscribe' => $unsubscribe,
			) );
		}

		if ( $term_subscription ) {
			echo $term_subscription;
		}
	}

	/**
	 * Term subscriptions use `get_term_link` for the redirect. This needs to be
	 * filtered to redirect to the appropriate theme/plugin support view.
	 *
	 * @param string $termlink The term link
	 * @param object $term The term object
	 * @param string $taxonomy The taxonomy object
	 * @return string The term link, or the support view link
	 */
	public function get_term_link( $termlink, $term, $taxonomy ) {
		if ( ! class_exists( 'WordPressdotorg\Forums\Term_Subscription\Plugin' ) ) {
			return $termlink;
		}

		// Only do this for the non-public compat taxonomies.
		if ( $this->taxonomy() != $taxonomy ) {
			return $termlink;
		}

		// Are we on a view where this needs filtering?
		if (
			( bbp_is_single_view() && $this->compat() == bbp_get_view_id() )
		||
			bbp_is_subscriptions()
		||
			( isset( $_GET['term_id'] ) && isset( $_GET['taxonomy'] ) )
		) {
			// Check that the subscription is to this compat.
			if ( $this->taxonomy() == $taxonomy ) {
				$paged = get_query_var( 'paged' ) > 1 ? 'page/' . absint( get_query_var( 'paged' ) ) . '/' : '';
				$termlink = sprintf( home_url( '/%s/%s/%s' ), $this->compat(), $term->slug, $paged );
			}
		}

		return $termlink;
	}

	/**
	 * Set the compat taxonomy on a topic if that data is provided on new post.
	 *
	 * @param int $topic_id The topic id
	 * @param int $forum_id The forum id
	 * @param int|array $anonymous_data 0 or anonymous author data
	 * @param int $topic_author The topic author id
	 */
	public function new_topic( $topic_id, $forum_id, $anonymous_data, $topic_author ) {
		if (
			( isset( $_POST['wporg_compat'] ) && $_POST['wporg_compat'] == $this->compat() )
		&&
			( isset( $_POST['wporg_compat_slug'] ) && $_POST['wporg_compat_slug'] == $this->slug() )
		) {
			// Check against the canonical plugin/theme records for slug existence.
			$object = $this->get_object( $_POST['wporg_compat_slug'] );

			if ( ! empty( $object ) ) {
				wp_set_object_terms( $topic_id, $this->slug(), $this->taxonomy(), false );
			}
		}
	}

	/**
	 * Set up and cache the plugin or theme details.
	 *
	 * @param string $slug The object slug
	 */
	public function get_object( $slug ) {
		global $wpdb;

		if ( 'theme' == $this->compat() ) {
			if ( ! is_null( $this->theme ) ) {
				return $this->theme;
			}
		} else {
			if ( ! is_null( $this->plugin ) ) {
				return $this->plugin;
			}
		}

		return self::get_object_by_slug_and_type( $slug, $this->compat() );
	}

	/**
	 * Get and cache object based on slug and type (plugin or theme).
	 *
	 * Suitable for use outside of the class.
	 *
	 * @param string $slug The object slug.
	 * @param string $type The type of the object. Either 'plugin' or 'theme'.
	 * @return array
	 */
	public static function get_object_by_slug_and_type( $slug, $type ) {
		global $wpdb;

		// Check the cache.
		$cache_key = $slug;
		$cache_group = $type . '-objects';
		$compat_object = wp_cache_get( $cache_key, $cache_group );
		if ( false === $compat_object ) {

			// Get the object information from the correct table.
			if ( $type == 'theme' ) {
				$compat_object = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}%d_posts WHERE post_name = %s AND post_type = 'repopackage' LIMIT 1", WPORG_THEME_DIRECTORY_BLOGID, $slug ) );
			} elseif ( $type == 'plugin' ) {
				// @todo Update this when the Plugin Directory switches over to WordPress.
				$_compat_topic = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . PLUGINS_TABLE_PREFIX . "topics WHERE topic_slug = %s", $slug ) );
				if ( $_compat_topic ) {
					$_compat_post  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . PLUGINS_TABLE_PREFIX . "posts WHERE topic_id = %d AND post_position = 1 LIMIT 1", $_compat_topic->topic_id ) );
				}
				if ( $_compat_topic && $_compat_post ) {
					$compat_object = (object) array(
						'post_title'   => $_compat_topic->topic_title,
						'post_name'    => $slug,
						'post_author'  => $_compat_topic->topic_poster,
						'post_content' => $_compat_post->post_text,
					);
				}
			}

			wp_cache_set( $cache_key, $compat_object, $cache_group, DAY_IN_SECONDS );
		}
		return $compat_object;
	}

	public function get_authors( $slug ) {
		global $wpdb;

		if ( null !== $this->authors ) {
			return $this->authors;
		}

		// Check the cache.
		$cache_key = $slug;
		$cache_group = $this->compat() . '-authors';
		$authors = wp_cache_get( $cache_key, $cache_group );
		if ( false === $authors ) {

			if ( $this->compat() == 'theme' ) {
				$theme = $this->theme;
				$author = get_user_by( 'id', $this->theme->post_author );
				$authors = array( $author->user_login );
			} else {
				$authors = $wpdb->get_col( $wpdb->prepare( " SELECT user FROM " . PLUGINS_TABLE_PREFIX . "svn_access WHERE `path` = %s", '/' . $slug ) );
			}

			wp_cache_set( $cache_key, $authors, $cache_group, HOUR_IN_SECONDS );
		}
		return $authors;
	}

	public function get_contributors( $slug ) {
		global $wpdb;

		if ( null !== $this->contributors ) {
			return $this->contributors;
		}

		// Themes do not have contributors right now.
		if ( $this->compat() == 'theme' ) {
			$contributors = array();
			return $contributors;
		}

		// Check the cache.
		$cache_key = $slug;
		$cache_group = $this->compat() . '-contributors';
		$contributors = wp_cache_get( $cache_key, $cache_group );
		if ( false === $contributors ) {
			// @todo Update this when the Plugin Directory switches over to WordPress.
			$contributors = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM " . PLUGINS_TABLE_PREFIX . "meta m LEFT JOIN " . PLUGINS_TABLE_PREFIX . "topics t ON m.object_id = t.topic_id WHERE t.topic_slug = %s AND m.object_type = %s AND m.meta_key = %s",
				$slug, 'bb_topic', 'contributors' ) );
			if ( $contributors ) {
				$contributors = unserialize( $contributors );
			}

			wp_cache_set( $cache_key, $contributors, $cache_group, HOUR_IN_SECONDS );
		}
		return $contributors;
	}
}
