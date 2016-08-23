<?php

namespace WordPressdotorg\Forums;

class Theme_Directory_Compat extends Directory_Compat {

	const COMPAT = 'theme';

	var $slug = '';
	var $theme = '';

	function compat() {
		return self::COMPAT;
	}

	function slug() {
		return $this->slug;
	}

	function title() {
		return ! empty( $this->theme ) ? $this->theme->post_title : '';
	}

	function forum_id() {
		return Plugin::THEMES_FORUM_ID;
	}

	function query_var() {
		return 'wporg_' . self::COMPAT;
	}

	function taxonomy() {
		return 'topic-' . self::COMPAT;
	}

	public function __construct() {
		if ( defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) && get_current_blog_id() == WPORG_SUPPORT_FORUMS_BLOGID ) {
			// We have to add the custom view before bbPress runs its own action
			// on parse_query at priority 2.
			add_action( 'parse_query', array( $this, 'parse_query' ), 1 );

			// Add parent class hooks.
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}
	}

	/**
	 * Add views if the theme query_var is present.
	 */
	public function parse_query() {
		$slug = get_query_var( 'wporg_theme' );
		if ( ! $slug ) {
			return;
		}

		$theme = $this->get_theme_data( $slug );
		if ( ! $theme ) {
			return;
		} else {
			$this->slug  = $slug;
			$this->theme = $theme;
		}

		// Add theme support view.
		bbp_register_view(
			self::COMPAT,
			__( 'Theme Support', 'wporg-forums' ),
			array(
				'post_parent'   => Plugin::THEMES_FORUM_ID,
				'tax_query'     => array( array(
					'taxonomy'  => $this->taxonomy(),
					'field'     => 'slug',
					'terms'     => $slug,
				) ),
				'orderby'       => '',
				'show_stickies' => false,
			)
		);

		// Add theme review view.
		bbp_register_view(
			'reviews',
			__( 'Reviews', 'wporg-forums' ),
			array(
				'post_parent'   => Plugin::REVIEWS_FORUM_ID,
				'tax_query'     => array( array(
					'taxonomy'  => $this->taxonomy(),
					'field'     => 'slug',
					'terms'     => $slug,
				) ),
				'orderby'       => '',
				'show_stickies' => false,
			)
		);
	}

	public function get_theme_data( $slug = '' ) {
		global $wpdb;

		if ( ! empty( $this->theme ) ) {
			return $this->theme;
		}

		$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}%d_posts WHERE post_name = %s AND post_type = 'repopackage' LIMIT 1", WPORG_THEME_DIRECTORY_BLOGID, $slug );
		$row = $wpdb->get_row( $sql );
		if ( ! $row ) {
			return false;
		} else {
			$theme = $row;
			$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}%d_postmeta WHERE post_id = %d AND meta_key NOT LIKE %s", WPORG_THEME_DIRECTORY_BLOGID, $row->ID, '_trac_ticket_%' );
			$results = $wpdb->get_results( $sql );
			if( $results ) {
				foreach ( $results as $row ) {
					if ( ! isset( $theme->{$row->meta_key} ) ) {
						$theme->{$row->meta_key} = maybe_unserialize( $row->meta_value );
					}
				}
			}
		}
		return $theme;
	}
}
