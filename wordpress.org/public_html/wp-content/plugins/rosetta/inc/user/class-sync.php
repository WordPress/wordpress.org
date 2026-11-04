<?php

namespace WordPressdotorg\Rosetta\User;

use WP_Site;

class Sync {

	/**
	 * The site object of the destination site.
	 *
	 * @var WP_Site
	 */
	private $destination_site;

	/**
	 * Array of roles to sync.
	 *
	 * @var array
	 */
	private $roles_to_sync = [];

	/**
	 * Flag to prevent recursion.
	 *
	 * @var bool
	 */
	private $is_syncing = false;

	/**
	 * Constructor.
	 *
	 * @param object|null $data Configuration object.
	 */
	public function __construct( $data = null ) {
		if ( isset( $data->destination_site ) && $data->destination_site instanceof WP_Site ) {
			$this->destination_site = $data->destination_site;
		}

		if ( isset( $data->roles_to_sync ) && is_array( $data->roles_to_sync ) ) {
			$this->roles_to_sync = $data->roles_to_sync;
		}
	}

	/**
	 * Sets the site object of the destination site.
	 *
	 * @param WP_Site $destination_site The site object of the destination site.
	 */
	public function set_destination_site( WP_Site $destination_site ) {
		$this->destination_site = $destination_site;
	}

	/**
	 * Sets the array of roles to sync.
	 *
	 * @param array $roles_to_sync An array of roles to sync. The key of an item
	 *                             is the source role, the value the destination role.
	 */
	public function set_roles_to_sync( array $roles_to_sync ) {
		$this->roles_to_sync = $roles_to_sync;
	}

	/**
	 * Registers the actions.
	 */
	public function setup() {
		if ( ! $this->destination_site instanceof WP_Site || empty( $this->roles_to_sync ) ) {
			return;
		}

		add_action( 'add_user_to_blog', [ $this, 'add_user_to_site' ], 10, 3 );
		add_action( 'add_user_role',    [ $this, 'add_user_to_site' ], 10, 2 );
		add_action( 'set_user_role',    [ $this, 'add_user_to_site' ], 10, 2 );
	}

	/**
	 * Adds a user to another site with the same role if they are not already a member.
	 *
	 * @param int      $user_id User ID.
	 * @param string   $role    User role.
	 * @param int|null $blog_id Blog ID (passed by add_user_to_blog).
	 */
	public function add_user_to_site( $user_id, $role, $blog_id = null ) {
		// Avoid recursion.
		if ( $this->is_syncing ) {
			return;
		}

		// Prevent execution if triggered directly on the destination site.
		$source_id = $blog_id ? (int) $blog_id : get_current_blog_id();
		if ( $source_id === $this->destination_site->id ) {
			return;
		}

		if ( ! isset( $this->roles_to_sync[ $role ] ) ) {
			return;
		}

		if ( is_user_member_of_blog( $user_id, $this->destination_site->id ) ) {
			return;
		}

		$this->is_syncing = true;
		add_user_to_blog( $this->destination_site->id, $user_id, $this->roles_to_sync[ $role ] );
		$this->is_syncing = false;
	}
}
