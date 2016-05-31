<?php

class WCB_Sessions extends WCB_Loader {
	var $meta_manager;

	function constants() {
		wcb_maybe_define( 'WCB_SESSION_POST_TYPE', 'wcb_session', 'wcb_session_post_type' );
		wcb_maybe_define( 'WCB_SESSION_SLUG',      'session',     'wcb_session_slug'      );
		wcb_maybe_define( 'WCB_TRACK_TAXONOMY',    'wcb_track',   'wcb_track_taxonomy'    );
	}

	function includes() {
		require_once "class-wcb-session-template.php";
	}

	function loaded() {
		$this->meta_manager = new WCB_Post_Meta_Manager( array(
			'prefix'    => 'wcb_session',
			'keys'      => array('speakers'),
		) );

		if ( is_admin() ) {
			$meta_fields = array(
				'speakers'  => array(
					'type'      => 'text',
					'label'     => __('Speakers', 'wordcamporg'),
				)
			);

			$box = wcb_get_metabox( 'WCB_Post_Metabox' );
			$box->add_instance( WCB_SESSION_POST_TYPE, array(
				'title'          => __('Speakers', 'wordcamporg'),
				'meta_manager'   => $this->meta_manager,
				'meta_fields'    => $meta_fields,
				'context'        => 'normal',
				'priority'       => 'high',
			) );
		}
	}

	function register_post_types() {
		// Session post type labels
		$labels = array (
			'name'                  => __( 'Sessions', 'wordcamporg' ),
			'singular_name'         => __( 'Session', 'wordcamporg' ),
			'add_new'               => __( 'Add New', 'wordcamporg' ),
			'add_new_item'          => __( 'Create New Session', 'wordcamporg' ),
			'edit'                  => __( 'Edit', 'wordcamporg' ),
			'edit_item'             => __( 'Edit Session', 'wordcamporg' ),
			'new_item'              => __( 'New Session', 'wordcamporg' ),
			'view'                  => __( 'View Session', 'wordcamporg' ),
			'view_item'             => __( 'View Session', 'wordcamporg' ),
			'search_items'          => __( 'Search Sessions', 'wordcamporg' ),
			'not_found'             => __( 'No sessions found', 'wordcamporg' ),
			'not_found_in_trash'    => __( 'No sessions found in Trash', 'wordcamporg' ),
			'parent_item_colon'     => __( 'Parent Session:', 'wordcamporg' )
		);

		// Session post type rewrite
		$rewrite = array (
			'slug'        => WCB_SESSION_SLUG,
			'with_front'  => false,
		);

		// Session post type supports
		$supports = array (
			'title',
			'editor',
			'revisions',
			'thumbnail',
		);

		$menu_icon = wcb_menu_icon( WCB_SESSION_POST_TYPE, WCB_URL . '/images/sessions.png' );

		// Register session post type
		register_post_type (
			WCB_SESSION_POST_TYPE,
			apply_filters( 'wcb_session_register_post_type',
				array (
					'labels'            => $labels,
					'rewrite'           => $rewrite,
					'supports'          => $supports,
					'menu_position'     => 21,
					'public'            => true,
					'show_ui'           => true,
					'can_export'        => true,
					'capability_type'   => 'post',
					'hierarchical'      => false,
					'query_var'         => true,
					'menu_icon'         => $menu_icon,
				)
			)
		);
	}

	function register_taxonomies() {

		// Labels
		$labels = array (
			'name'              => __( 'Tracks', 'wordcamporg'),
			'singular_name'     => __( 'Track', 'wordcamporg'),
			'search_items'      => __( 'Search Tracks', 'wordcamporg'),
			'popular_items'     => __( 'Popular Tracks','wordcampbase'),
			'all_items'         => __( 'All Tracks', 'wordcamporg'),
			'edit_item'         => __( 'Edit Track', 'wordcamporg'),
			'update_item'       => __( 'Update Track', 'wordcamporg'),
			'add_new_item'      => __( 'Add Track', 'wordcamporg'),
			'new_item_name'     => __( 'New Track', 'wordcamporg'),
		);

		// Rewrite
		$rewrite = array (
			'slug' => 'track'
		);

		// Register the taxonomy
		register_taxonomy (
			WCB_TRACK_TAXONOMY,             // The tax ID
			WCB_SESSION_POST_TYPE,          // The post type ID
			apply_filters( 'wcb_track_taxonomy_register',
				array (
					'labels'                => $labels,
					'rewrite'               => $rewrite,
					'query_var'             => 'track',
					'hierarchical'          => true,
					'public'                => true,
					'show_ui'               => true,
				)
			)
		);
	}

}

?>