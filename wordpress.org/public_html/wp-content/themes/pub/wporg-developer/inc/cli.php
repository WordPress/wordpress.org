<?php

class DevHub_CLI {

	private static $commands_manifest = 'https://raw.githubusercontent.com/wp-cli/handbook/master/bin/commands-manifest.json';
	private static $meta_key = 'wporg_cli_markdown_source';
	private static $supported_post_types = array( 'command' );
	private static $posts_per_page = 350;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'action_init_register_cron_jobs' ) );
		add_action( 'init', array( __CLASS__, 'action_init_register_post_types' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'action_pre_get_posts' ) );
		add_action( 'devhub_cli_manifest_import', array( __CLASS__, 'action_devhub_cli_manifest_import' ) );
		add_action( 'devhub_cli_markdown_import', array( __CLASS__, 'action_devhub_cli_markdown_import' ) );
	}

	public static function action_init_register_cron_jobs() {
		if ( ! wp_next_scheduled( 'devhub_cli_manifest_import' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'devhub_cli_manifest_import' );
		}
		if ( ! wp_next_scheduled( 'devhub_cli_markdown_import' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'devhub_cli_markdown_import' );
		}
	}

	public static function action_init_register_post_types() {
		$supports = array(
			'comments',
			'custom-fields',
			'editor',
			'excerpt',
			'revisions',
			'title',
		);
		register_post_type( 'command', array(
			'has_archive' => 'cli/commands',
			'label'       => __( 'Commands', 'wporg' ),
			'labels'      => array(
				'name'               => __( 'Commands', 'wporg' ),
				'singular_name'      => __( 'Command', 'wporg' ),
				'all_items'          => __( 'Commands', 'wporg' ),
				'new_item'           => __( 'New Command', 'wporg' ),
				'add_new'            => __( 'Add New', 'wporg' ),
				'add_new_item'       => __( 'Add New Command', 'wporg' ),
				'edit_item'          => __( 'Edit Command', 'wporg' ),
				'view_item'          => __( 'View Command', 'wporg' ),
				'search_items'       => __( 'Search Commands', 'wporg' ),
				'not_found'          => __( 'No Commands found', 'wporg' ),
				'not_found_in_trash' => __( 'No Commands found in trash', 'wporg' ),
				'parent_item_colon'  => __( 'Parent Command', 'wporg' ),
				'menu_name'          => __( 'Commands', 'wporg' ),
			),
			'public'      => true,
			'hierarchical'=> true,
			'rewrite'     => array(
				'feeds'      => false,
				'slug'       => 'cli/commands',
				'with_front' => false,
			),
			'supports'    => $supports,
		) );
	}

	public static function action_pre_get_posts( $query ) {
		if ( $query->is_main_query() && $query->is_post_type_archive( 'command' ) ) {
			$query->set( 'post_parent', 0 );
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
			$query->set( 'posts_per_page', 250 );
		}
	}

	public static function action_devhub_cli_manifest_import() {
		$response = wp_remote_get( self::$commands_manifest );
		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'invalid-http-code', 'Markdown source returned non-200 http code.' );
		}
		$manifest = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! $manifest ) {
			return new WP_Error( 'invalid-manifest', 'Manifest did not unfurl properly.' );;
		}
		// Fetch all handbook posts for comparison
		$q = new WP_Query( array(
			'post_type'      => self::$supported_post_types,
			'post_status'    => 'publish',
			'posts_per_page' => self::$posts_per_page,
		) );
		$existing = array();
		foreach( $q->posts as $post ) {
			$cmd_path = rtrim( str_replace( home_url( 'cli/commands/' ), '', get_permalink( $post->ID ) ), '/' );
			$existing[ $cmd_path ] = array(
				'post_id'   => $post->ID,
				'cmd_path'  => $cmd_path,
			);
		}
		$created = 0;
		foreach( $manifest as $doc ) {
			// Already exists
			if ( wp_filter_object_list( $existing, array( 'cmd_path' => $doc['cmd_path'] ) ) ) {
				continue;
			}
			if ( self::process_manifest_doc( $doc, $existing, $manifest ) ) {
				$created++;
			}
		}
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::success( "Successfully created {$created} handbook pages." );
		}
	}

	private static function process_manifest_doc( $doc, &$existing, $manifest ) {
		$post_parent = null;
		if ( ! empty( $doc['parent'] ) ) {
			// Find the parent in the existing set
			$parents = wp_filter_object_list( $existing, array( 'cmd_path' => $doc['parent'] ) );
			if ( empty( $parents ) ) {
				if ( ! self::process_manifest_doc( $manifest[ $doc['parent'] ], $existing, $manifest ) ) {
					return;
				}
				$parents = wp_filter_object_list( $existing, array( 'cmd_path' => $doc['parent'] ) );
			}
			if ( ! empty( $parents ) ) {
				$parent = array_shift( $parents );
				$post_parent = $parent['post_id'];
			}
		}
		$post = self::create_post_from_manifest_doc( $doc, $post_parent );
		if ( $post ) {
			$cmd_path = rtrim( str_replace( home_url( 'cli/commands/' ), '', get_permalink( $post->ID ) ), '/' );
			$existing[ $cmd_path ] = array(
				'post_id'   => $post->ID,
				'cmd_path'  => $cmd_path,
			);
			return true;
		}
		return false;
	}

	public static function action_devhub_cli_markdown_import() {
		$q = new WP_Query( array(
			'post_type'      => self::$supported_post_types,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => self::$posts_per_page,
		) );
		$ids = $q->posts;
		$success = 0;
		foreach( $ids as $id ) {
			$ret = self::update_post_from_markdown_source( $id );
			if ( class_exists( 'WP_CLI' ) ) {
				if ( is_wp_error( $ret ) ) {
					\WP_CLI::warning( $ret->get_error_message() );
				} else {
					\WP_CLI::log( "Updated {$id} from markdown source" );
					$success++;
				}
			}
		}
		if ( class_exists( 'WP_CLI' ) ) {
			$total = count( $ids );
			\WP_CLI::success( "Successfully updated {$success} of {$total} CLI command pages." );
		}
	}

	/**
	 * Create a new handbook page from the manifest document
	 */
	private static function create_post_from_manifest_doc( $doc, $post_parent = null ) {
		$post_data = array(
			'post_type'   => 'command',
			'post_status' => 'publish',
			'post_parent' => $post_parent,
			'post_title'  => sanitize_text_field( wp_slash( $doc['title'] ) ),
			'post_name'   => sanitize_title_with_dashes( $doc['slug'] ),
		);
		$post_id = wp_insert_post( $post_data );
		if ( ! $post_id ) {
			return false;
		}
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::log( "Created post {$post_id} for {$doc['title']}." );
		}
		update_post_meta( $post_id, self::$meta_key, esc_url_raw( $doc['markdown_source'] ) );
		return get_post( $post_id );
	}

	/**
	 * Update a post from its Markdown source
	 */
	private static function update_post_from_markdown_source( $post_id ) {
		$markdown_source = self::get_markdown_source( $post_id );
		if ( is_wp_error( $markdown_source ) ) {
			return $markdown_source;
		}
		if ( ! function_exists( 'jetpack_require_lib' ) ) {
			return new WP_Error( 'missing-jetpack-require-lib', 'jetpack_require_lib() is missing on system.' );
		}

		// Transform GitHub repo HTML pages into their raw equivalents
		$markdown_source = preg_replace( '#https?://github\.com/([^/]+/[^/]+)/blob/(.+)#', 'https://raw.githubusercontent.com/$1/$2', $markdown_source );
		$markdown_source = add_query_arg( 'v', time(), $markdown_source );
		$response = wp_remote_get( $markdown_source );
		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'invalid-http-code', 'Markdown source returned non-200 http code.' );
		}

		$markdown = wp_remote_retrieve_body( $response );
		// Strip YAML doc from the header
		$markdown = preg_replace( '#^---(.+)---#Us', '', $markdown );

		$title = null;
		if ( preg_match( '/^#\s(.+)/', $markdown, $matches ) ) {
			$title = $matches[1];
			$markdown = preg_replace( '/^#\swp\s(.+)/', '', $markdown );
		}

		// Transform to HTML and save the post
		jetpack_require_lib( 'markdown' );
		$parser = new \WPCom_GHF_Markdown_Parser;
		$html = $parser->transform( $markdown );
		$post_data = array(
			'ID'           => $post_id,
			'post_content' => wp_filter_post_kses( wp_slash( $html ) ),
		);
		if ( ! is_null( $title ) ) {
			$post_data['post_title'] = sanitize_text_field( wp_slash( $title ) );
		}
		wp_update_post( $post_data );
		return true;
	}

	/**
	 * Retrieve the markdown source URL for a given post.
	 */
	public static function get_markdown_source( $post_id ) {
		$markdown_source = get_post_meta( $post_id, self::$meta_key, true );
		if ( ! $markdown_source ) {
			return new WP_Error( 'missing-markdown-source', 'Markdown source is missing for post.' );
		}

		return $markdown_source;
	}

}

DevHub_CLI::init();

