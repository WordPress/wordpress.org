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
	private $roles_to_sync;

	/**
	 * Constructor.
	 *
	 * @param \stdClass $data
	 */
	public function __construct( $data = null ) {
		if ( isset( $data->destination_site ) ) {
			$this->destination_site = $data->destination_site;
		}

		if ( isset( $data->roles_to_sync ) ) {
			$this->destination_site = $data->roles_to_sync;
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
		if ( ! $this->destination_site || ! $this->roles_to_sync ) {
			return;
		}

		add_action( 'add_user_to_blog', [ $this, 'add_user_to_site' ], 10, 2 );
		add_action( 'add_user_role',    [ $this, 'add_user_to_site' ], 10, 2 );
		add_action( 'set_user_role',    [ $this, 'add_user_to_site' ], 10, 2 );
	}

	/**
	 * Adds an user to another site with the same role.
	 *
	 * @param int    $user_id User ID.
	 * @param string $role    User role.
	 */
	public function add_user_to_site( $user_id, $role ) {
		// Avoid recursion.
		remove_action( 'add_user_to_blog', [ $this, 'add_user_to_site' ], 10 );
		remove_action( 'add_user_role',    [ $this, 'add_user_to_site' ], 10 );
		remove_action( 'set_user_role',    [ $this, 'add_user_to_site' ], 10 );

		if ( ! in_array( $role, array_keys( $this->roles_to_sync ), true ) ) {
			return;
		}

		if ( is_user_member_of_blog( $user_id, $this->destination_site->id ) ) {
			return;
		}

		add_user_to_blog( $this->destination_site->id, $user_id, $this->roles_to_sync[ $role ] );
	}
}
