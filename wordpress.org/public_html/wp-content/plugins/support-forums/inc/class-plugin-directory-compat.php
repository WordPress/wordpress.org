<?php

namespace WordPressdotorg\Forums;

class Plugin_Directory_Compat extends Directory_Compat {

	const COMPAT = 'plugin';

	var $slug = '';
	var $plugin = '';

	function compat() {
		return self::COMPAT;
	}

	function slug() {
		return $this->slug;
	}

	function title() {
		return $this->plugin->post_title;
	}

	function forum_id() {
		return Plugin::PLUGINS_FORUM_ID;
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
	 * Add views if the plugin query_var is present.
	 */
	public function parse_query() {
		$slug = get_query_var( 'wporg_plugin' );
		if ( ! $slug ) {
			return;
		}

		$plugin = $this->get_plugin_data( $slug );
		if ( ! $plugin ) {
			return;
		} else {
			$this->slug  = $slug;
			$this->plugin = $plugin;
		}

		// Add plugin support view.
		bbp_register_view(
			self::COMPAT,
			__( 'Plugin Support', 'wporg-forums' ),
			array(
				'post_parent'   => Plugin::PLUGINS_FORUM_ID,
				'tax_query'     => array( array(
					'taxonomy'  => $this->taxonomy(),
					'field'     => 'slug',
					'terms'     => $slug,
				) ),
				'orderby'       => '',
				'show_stickies' => false,
			)
		);

		// Add plugin review view.
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

	public function get_plugin_data( $slug = '' ) {
		global $wpdb;

		if ( ! empty( $this->plugin ) ) {
			return $this->plugin;
		}

		$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}%d_posts WHERE post_name = %s AND post_type = 'plugin' LIMIT 1", WPORG_PLUGIN_DIRECTORY_BLOGID, $slug );
		$row = $wpdb->get_row( $sql );
		if ( ! $row ) {
			return false;
		} else {
			$plugin = $row;
			$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}%d_postmeta WHERE post_id = %d AND meta_key NOT LIKE %s", WPORG_PLUGIN_DIRECTORY_BLOGID, $row->ID, '_trac_ticket_%' );
			$results = $wpdb->get_results( $sql );
			if( $results ) {
				foreach ( $results as $row ) {
					if ( ! isset( $plugin->{$row->meta_key} ) ) {
						$plugin->{$row->meta_key} = maybe_unserialize( $row->meta_value );
					}
				}
			}
		}
		return $plugin;
	}
}
