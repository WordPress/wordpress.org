<?php
/**
 * Implements Meetup_Loader
 *
 * @package WordCamp Post Type
 */

use WordPress_Community\Applications\Meetup_Application;
define( 'WCPT_MEETUP_SLUG', 'wp_meetup' );
define( 'WCPT_MEETUP_TAG_SLUG', 'meetup_tags' );
define( 'WCPT_WORDPRESS_MEETUP_ID', 72560962 );


if ( ! class_exists( 'Meetup_Loader' ) ) :

	/**
	 * Class Meetup_Loader
	 */
	class Meetup_Loader extends Event_Loader {

		/**
		 * Meetup_Loader constructor.
		 */
		public function __construct() {
			parent::__construct();
			add_action( 'init', array( $this, 'register_meetup_taxonomy' ) );
			add_action( 'set_object_terms', array( $this, 'log_meetup_tags' ), 10, 6 );
		}

		/**
		 * Log when a tag is added or removed in a meetup event
		 *
		 * @param int    $event_id   Meetup post ID.
		 * @param array  $terms      An array of tags.
		 * @param array  $tt_ids     An array of term taxonomy IDs.
		 * @param string $taxonomy   Taxonomy slug.
		 * @param bool   $append     Whether to append new terms to the old terms.
		 * @param array  $old_tt_ids Old array of term taxonomy IDs.
		 */
		public function log_meetup_tags( $event_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {

			// bail if taxonomy is not meetup tags.
			if ( WCPT_MEETUP_TAG_SLUG !== $taxonomy ) {
				return;
			}

			$tt_added_ids = array_diff( $tt_ids, $old_tt_ids );
			$tt_removed_ids = array_diff( $old_tt_ids, $tt_ids );

			if ( count( $tt_added_ids ) > 0 ) {
				$added_terms_query = new WP_Term_Query( array(
						'taxonomy'   => WCPT_MEETUP_TAG_SLUG,
						'object_ids' => $event_id,
						'term_taxonomy_id' => $tt_added_ids,
						'fields' => 'names'
					)
				);

				$added_terms = $added_terms_query->get_terms();

				add_post_meta( $event_id, '_tags_log', array(
					'timestamp' => time(),
					'user_id'   => get_current_user_id(),
					'message'   => 'Tags added: ' . join( ', ', $added_terms ),
				) );
			}

			if ( count( $tt_removed_ids ) > 0 ) {
				$removed_terms = ( new WP_Term_Query( array(
						'taxonomy'         => WCPT_MEETUP_TAG_SLUG,
						'term_taxonomy_id' => $tt_removed_ids,
						'fields'           => 'names',
						'hide_empty'       => false,
					)
				) )->get_terms();

				add_post_meta( $event_id, '_tags_log', array(
					'timestamp' => time(),
					'user_id'   => get_current_user_id(),
					'message'   => 'Tags removed: ' . join( ', ', $removed_terms ),
				) );
			}

		}

		/**
		 * Register custom tags for meetup events.
		 */
		public function register_meetup_taxonomy() {
			register_taxonomy(
				'meetup_tags',
				Meetup_Application::POST_TYPE,
				array(
					'hierarchical' => false,
					'labels'       => array(
						'name'          => __( 'Meetup Tags' ),
						'singular_name' => __( 'Meetup Tag' ),
					),
					'show_admin_column' => true,
				)
			);
		}

		/**
		 * Include files specific for meetup event
		 */
		public function includes() {
		}

		/**
		 * Register meetup custom post type
		 */
		public function register_post_types() {
			// Meetup post type labels
			$wcpt_labels = array(
				'name'               => __( 'Meetups', 'wordcamporg' ),
				'singular_name'      => __( 'Meetup', 'wordcamporg' ),
				'add_new'            => __( 'Add New', 'wordcamporg' ),
				'add_new_item'       => __( 'Create New Meetup', 'wordcamporg' ),
				'edit'               => __( 'Edit', 'wordcamporg' ),
				'edit_item'          => __( 'Edit Meetup', 'wordcamporg' ),
				'new_item'           => __( 'New Meetup', 'wordcamporg' ),
				'view'               => __( 'View Meetup', 'wordcamporg' ),
				'view_item'          => __( 'View Meetup', 'wordcamporg' ),
				'search_items'       => __( 'Search Meetup', 'wordcamporg' ),
				'not_found'          => __( 'No Meetup found', 'wordcamporg' ),
				'not_found_in_trash' => __( 'No Meetup found in Trash', 'wordcamporg' ),
				'parent_item_colon'  => __( 'Parent Meetup:', 'wordcamporg' ),
			);

			// Meetup post type rewrite
			// TODO: Is this necessary?
			$wcpt_rewrite = array(
				'slug'       => WCPT_MEETUP_SLUG,
				'with_front' => false,
			);

			$wcpt_supports = array(
				'title',
				'revisions',
				'author',
			);

			// Register WordCamp post type
			register_post_type(
				Meetup_Application::POST_TYPE, array(
					'labels'          => $wcpt_labels,
					'rewrite'         => $wcpt_rewrite,
					'supports'        => $wcpt_supports,
					'menu_position'   => '100',
					'public'          => true,
					'show_ui'         => true,
					'can_export'      => true,
					'capability_type' => Meetup_Application::POST_TYPE,
					'map_meta_cap'    => true,
					'hierarchical'    => false,
					'has_archive'     => true,
					'query_var'       => true,
					'menu_icon'       => 'dashicons-wordpress',
					'show_in_rest'    => true,
					'rest_base'       => 'meetups',
				)
			);
		}

		/**
		 * Allow some site roles to see WordCamp posts.
		 */
		public function register_post_capabilities() {
			$roles = array(
				'contributor',
				'author',
				'editor',
				'administrator',
			);

			foreach ( $roles as $role ) {
				get_role( $role )->add_cap( 'edit_' . Meetup_Application::POST_TYPE . 's' );
			}
		}

		/**
		 * Get available post statuses
		 *
		 * @return array
		 */
		public static function get_post_statuses() {
			return Meetup_Application::get_post_statuses();
		}

		/**
		 * Get public post statuses
		 *
		 * @return array
		 */
		public static function get_public_post_statuses() {
			return Meetup_Application::get_public_post_statuses();
		}

	}

endif;
