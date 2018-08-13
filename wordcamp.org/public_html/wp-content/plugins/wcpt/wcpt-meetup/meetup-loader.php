<?php
/**
 * Implements Meetup_Loader
 *
 * @package WordCamp Post Type
 */

use WordPress_Community\Applications\Meetup_Application;
define( 'WCPT_MEETUP_SLUG', 'wp_meetup' );


if ( ! class_exists( 'Meetup_Loader' ) ) :

	/**
	 * Class Meetup_Loader
	 */
	class Meetup_Loader extends Event_Loader {


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
				'editor',
				'thumbnail',
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
