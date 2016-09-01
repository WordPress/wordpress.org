<?php

namespace WordPressdotorg\Forums;

abstract class Directory_Compat {

	abstract protected function compat();
	abstract protected function compat_title();
	abstract protected function slug();
	abstract protected function title();
	abstract protected function forum_id();
	abstract protected function query_var();
	abstract protected function taxonomy();
	abstract protected function parse_query();
	abstract protected function do_view_sidebar();
	abstract protected function do_topic_sidebar();
	abstract protected function do_view_header();

	var $authors      = null;
	var $contributors = null;

	public function init() {
		if ( defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) && get_current_blog_id() == WPORG_SUPPORT_FORUMS_BLOGID ) {
			// Define the taxonomy and query vars for this view.
			add_action( 'plugins_loaded', array( $this, 'always_load' ) );

			// We have to add the custom view before bbPress runs its own action
			// on parse_query at priority 2.
			add_action( 'parse_query', array( $this, 'parse_query' ), 0 );

			// And this still needs to happen before priority 2.
			add_action( 'parse_query', array( $this, 'maybe_load' ), 1 );

			// Check to see if an individual topic is compat.
			add_action( 'template_redirect', array( $this, 'check_topic_for_compat' ) );

			// Always check to see if a topic title needs a compat prefix.
			add_filter( 'bbp_get_topic_title', array( $this, 'get_topic_title' ), 9, 2 );

			// Always check to see if a new topic is being posted.
			add_action( 'bbp_new_topic_post_extras', array( $this, 'topic_post_extras' ) );
		}
	}

	public function always_load() {
		// Add filters necessary for determining which compat file to use.
		add_action( 'bbp_init',              array( $this, 'register_taxonomy' ) );
		add_filter( 'query_vars',            array( $this, 'add_query_var' ) );
		add_action( 'bbp_add_rewrite_rules', array( $this, 'add_rewrite_rules' ) );

	}

	public function maybe_load() {
		if ( false !== $this->slug() ) {
			// This must run before bbPress's parse_query at priority 2.
			$this->register_views();

			// Add theme-specific filters and actions.
			add_action( 'wporg_compat_view_sidebar', array( $this, 'do_view_sidebar' ) );

			// Add output filters and actions.
			add_filter( 'bbp_get_view_link', array( $this, 'get_view_link' ), 10, 2 );
			add_filter( 'bbp_breadcrumbs',   array( $this, 'breadcrumbs' ) );

			add_action( 'wporg_compat_before_single_view', array( $this, 'do_view_header' ) );

			// Handle new topic form at the bottom of support view.
			add_action( 'wporg_compat_after_single_view',      array( $this, 'add_topic_form' ) );
			add_action( 'bbp_theme_before_topic_form_content', array( $this, 'add_topic_form_content' ) );
		}
	}

	public function check_topic_for_compat() {
		if ( bbp_is_single_topic() ) {
			$slug = wp_get_object_terms( bbp_get_topic_id(), $this->taxonomy(), array( 'fields' => 'slugs' ) );

			// Match found for this compat.
			if ( ! empty( $slug ) ) {
				$slug = $slug[0];

				// Basic setup.
				$this->slug              = $slug;
				$this->{$this->compat()} = $this->get_object( $slug );
				$this->authors           = $this->get_authors( $slug );
				$this->contributors      = $this->get_contributors( $slug );

				// Add output filters and actions.
				if ( ! empty( $this->authors ) || ! empty( $this->contributors ) ) {
					add_filter( 'bbp_get_topic_author_link', array( $this, 'author_link' ), 10, 2 );
					add_filter( 'bbp_get_reply_author_link', array( $this, 'author_link' ), 10, 2 );
				}
				add_action( 'wporg_compat_single_topic_sidebar_pre', array( $this, 'do_topic_sidebar' ) );
			}
		}
	}

	public function get_topic_title( $title, $topic_id ) {
		if ( bbp_is_single_topic() || ( bbp_is_single_view() && in_array( bbp_get_view_id(), array( $this->compat(), 'reviews', 'active' ) ) ) ) {
			return $title;
		}

		$slug = wp_get_object_terms( $topic_id, $this->taxonomy(), array( 'fields' => 'slugs' ) );
		if ( ! empty( $slug ) ) {
			$slug = $slug[0];
			$object = $this->get_object( $slug );
			$title = sprintf( "[%s] %s", esc_html( $object->post_title ), esc_html( $title ) );
		}
		return $title;
	}

	public function add_rewrite_rules() {
		$priority   = 'top';

		$root_id    = $this->compat();
		$root_var   = $this->query_var();
		$review_id  = 'reviews';
		$active_id  = 'active';

		$support_rule = $this->compat() . '/([^/]+)/';
		$reviews_rule = $this->compat() . '/([^/]+)/' . $review_id . '/';
		$active_rule  = $this->compat() . '/([^/]+)/' . $active_id . '/';

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
				'show_stickies' => false,
				'orderby'       => 'ID',
			)
		);

		// Add reviews view.
		bbp_register_view(
			'reviews',
			__( 'Reviews', 'wporg-forums' ),
			array(
				'post_parent'   => Plugin::REVIEWS_FORUM_ID,
				'tax_query'     => array( array(
					'taxonomy'  => $this->taxonomy(),
					'field'     => 'slug',
					'terms'     => $this->slug(),
				) ),
				'show_stickies' => false,
				'orderby'       => 'ID',
			)
		);

		// Add recent activity view.
		bbp_register_view(
			'active',
			__( 'Recent Activity', 'wporg-forums' ),
			array(
				'post_parent'   => $this->forum_id(),
				'meta_query'    => array( array(
					'key'       => '_bbp_last_active_time',
					'type'      => 'DATETIME',
				) ),
				'tax_query'     => array( array(
					'taxonomy'  => $this->taxonomy(),
					'field'     => 'slug',
					'terms'     => $this->slug(),
				) ),
				'show_stickies' => false,
				'orderby'       => 'meta_value',
			)
		);
	}

	/**
	 * Filter view links to provide prettier links for these subforum views.
	 */
	public function get_view_link( $url, $view ) {
		global $wp_rewrite;

		$view = bbp_get_view_id( $view );
		if ( ! in_array( $view, array( 'active', 'reviews', $this->compat() ) ) ) {
			return $url;
		}

		// Pretty permalinks.
		if ( $wp_rewrite->using_permalinks() ) {
			switch ( $view ) {
				case 'active' :
				case 'reviews' :
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
		if ( ! in_array( $view, array( $this->compat(), 'reviews', 'active' ) ) ) {
			return $r;
		}

		$r[1] = '<a href="' . esc_url( bbp_get_forum_permalink( $this->forum_id() ) ) . '" class="bbp-breadcrumb-forum">' . esc_html( bbp_get_forum_title( $this->forum_id() ) ) . '</a>';
		$r[2] = esc_html( $this->title() );
		if ( 'reviews' == $view ) {
			$r[2] = '<a href="' . esc_url( bbp_get_view_url( $this->compat() ) ) . '" class="bbp-breadcrumb-forum">' . esc_html( $this->title() ) . '</a>';
			$r[3] = __( 'Reviews', 'wporg-forums' );
		}
		return $r;
	}

	/**
	 * Add the new topic form at the bottom of appropriate views.
	 */
	public function add_topic_form() {
		if ( ! bbp_is_single_view() ) {
			return;
		}

		$view = bbp_get_view_id();
		if ( ! in_array( $view, array( $this->compat(), 'reviews', 'active' ) ) ) {
			return;
		}

		bbp_get_template_part( 'form', 'topic' );
	}

	public function add_topic_form_content() {
		if ( ! bbp_is_single_view() ) {
			return;
		}

		$view = bbp_get_view_id();
		if ( ! in_array( $view, array( $this->compat(), 'reviews', 'active' ) ) ) {
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

	public function topic_post_extras( $topic_id ) {
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
	 * @todo Add the author or contributor badge.
	 */
	public function author_link( $author_link, $args ) {
		return $author_link;
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

		// Check the cache.
		$cache_key = "{$slug}";
		$cache_group = $this->compat() . '-objects';
		$compat_object = wp_cache_get( $cache_key, $cache_group );
		if ( false === $compat_object ) {

			// Get the object information from the correct table.
			if ( $this->compat() == 'theme' ) {
				$compat_object = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}%d_posts WHERE post_name = %s AND post_type = 'repopackage' LIMIT 1", WPORG_THEME_DIRECTORY_BLOGID, $slug ) );
			} elseif ( $this->compat() == 'plugin' ) {
				$compat_object = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}%d_posts WHERE post_name = %s AND post_type = 'plugin' LIMIT 1", WPORG_PLUGIN_DIRECTORY_BLOGID, $slug ) );
			}

			wp_cache_set( $cache_key, $compat_object, $cache_group, 86400);
		}
		return $compat_object;
	}

	public function get_authors( $slug ) {
		global $wpdb;

		if ( null !== $this->authors ) {
			return $this->authors;
		}

		// Check the cache.
		$cache_key = "{$slug}";
		$cache_group = $this->compat() . '-authors';
		$authors = wp_cache_get( $cache_key, $cache_group );
		if ( false === $authors ) {

			if ( $this->compat() == 'theme' ) {
				$theme = $this->theme;
				$author = get_user_by( 'id', $this->theme->post_author );
				$authors = array( $author->user_login );
			} else {
				$authors = $wpdb->get_col( $wpdb->prepare( " SELECT user FROM plugin_2_svn_access WHERE `path` = %s", '/' . $slug ) );
			}

			wp_cache_set( $cache_key, $authors, $cache_group, 3600 );
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
		$cache_key = "{$slug}";
		$cache_group = $this->compat() . '-contributors';
		$contributors = wp_cache_get( $cache_key, $cache_group );
		if ( false === $contributors ) {
			$contributors = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->base_prefix}%d_postmeta WHERE post_id = %d AND meta_key = %s LIMIT 1", WPORG_PLUGIN_DIRECTORY_BLOGID, $this->plugin->ID, 'contributors' ) );
			$contributors = maybe_unserialize( $contributors );

			wp_cache_set( $cache_key, $contributors, $cache_group, 3600 );
		}
		return $contributors;
	}
}
