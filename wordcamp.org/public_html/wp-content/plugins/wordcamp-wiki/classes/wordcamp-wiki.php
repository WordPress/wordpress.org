<?php

/*
 * Subscribers can edit any page that is marked as a Wiki, or whose parent/grandparent/etc is marked as a wiki.
 * They can create new pages, but those pages will have to be approved by an Editor before being published.
 */

class WordCamp_Wiki {

	/**
	 * Constructor
	 */
	function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post',      array( $this, 'save_post_meta' ), 10, 2 );
		add_filter( 'user_has_cap',   array( $this, 'allow_subscribers_edit_wiki_pages' ), 10, 4 );
	}

	/**
	 * Grant Subscribers the capabilities to edit pages marked as a wiki.
	 *
	 * @param array $caps
	 * @param array $meta_caps
	 * @param array $args
	 * @param WP_User $user
	 * @return array
	 */
	public function allow_subscribers_edit_wiki_pages( $caps, $meta_caps, $args, $user ) {
		if ( ! in_array( 'subscriber', $user->roles ) ) {
			return $caps;
		}

		$edit_caps = array( 'edit_pages', 'edit_others_pages', 'edit_published_pages' );

		if ( 'edit_post' == $args[0] && ! $this->user_can_edit_page( $args[2] ) ) {
			$grant = false;
		} else {
			$grant = true;
		}

		foreach ( $edit_caps as $edit_cap ) {
			$caps[ $edit_cap ] = $grant;
		}

		return $caps;
	}

	/**
	 * Determines whether or not the current user can edit the given page.
	 *
	 * They're allowed if it (or one of its parents) is marked as a wiki, or if they're the author.
	 *
	 * @param $post_id
	 * @return bool
	 */
	protected function user_can_edit_page( $post_id ) {
		$post = get_post( $post_id );

		if ( get_current_user_id() == $post->post_author ) {
			$can_edit = true;
		} else {
			do {
				$can_edit = (bool) get_post_meta( $post->ID, '_wcw_is_wiki_page', true );

				if ( $post->post_parent ) {
					$post = get_post( $post->post_parent );
				} else {
					break;
				}
			} while ( ! $can_edit );
		}

		return $can_edit;
	}

	/**
	 * Adds a meta box to ask if the current page should be treated as a wiki
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'wcw_wiki',
			'Wiki',
			array( $this, 'render_meta_boxes' ),
			'page',
			'side'
		);
	}

	/**
	 * Renders the markup for all meta boxes
	 *
	 * @param WP_Post $post
	 * @param array $box
	 */
	public static function render_meta_boxes( $post, $box ) {
		switch ( $box['id'] ) {
			case 'wcw_wiki':
				$is_wiki_page = (bool) get_post_meta( $post->ID, '_wcw_is_wiki_page', true );
				$view         = 'metabox-wiki.php';
				break;

			default:
				$view = false;
				break;
		}

		if ( $view ) {
			require_once( dirname( __DIR__ ) . '/views/' . $view );
		}
	}

	/**
	 * Saves meta field values when a page is saved.
	 *
	 * @param int $post_id
	 * @param WP_Post $post
	 */
	public static function save_post_meta( $post_id, $revision ) {
		global $post;
		$ignored_actions = array( 'trash', 'untrash', 'restore' );

		if ( isset( $_GET['action'] ) && in_array( $_GET['action'], $ignored_actions ) ) {
			return;
		}

		if ( ! $post || $post->post_type != 'page' || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft' ) {
			return;
		}

		if ( isset( $_POST['wcw_is_wiki_page'] ) ) {
			update_post_meta( $post_id, '_wcw_is_wiki_page', (bool) $_POST['wcw_is_wiki_page'] );
		} else {
			delete_post_meta( $post_id, '_wcw_is_wiki_page' );
		}
	}
}
