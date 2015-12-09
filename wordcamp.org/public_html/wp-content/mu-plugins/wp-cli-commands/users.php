<?php

defined( 'WP_CLI' ) or die();

/**
 * WordCamp.org: Manage users.
 */
class WordCamp_CLI_Users extends WP_CLI_Command {
	/**
	 * Reset the rich_editing option for all users with roles on WordCamp.org
	 *
	 * ## DESCRIPTION
	 *
	 * Some users have reported missing the visual editor, and their profile has rich_editing set
	 * to false, even though they never disabled it.
	 *
	 * It was most likely caused by the fact that
	 * wporg-mu-plugins/enable-rich-editing-by-default.php was not being included, so the setting
	 * defaulted to being off, and would be permenantly saved as off when they updated their
	 * profile to change something else.
	 *
	 * enable-rich-editing-by-default.php was enabled in wordcamp:changeset:2069. Now that the
	 * root problem is probably fixed, we can reset the option for all WordCamp.org users. This
	 * will have the side-effect of enabling the editor for those that explicitly turned it off,
	 * but that's unavoidable and will only affect a small percentage of users, who can easily
	 * revert the setting.
	 *
	 * This only runs for users with roles on a site, rather than all users, because we share user
	 * tables with WordPress.org, and we only want to affect users of WordCamp.org.
	 *
	 * See https://wordpress.slack.com/archives/events/p1449201277000795
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show report, but don't perform the changes.
	 *
	 * @subcommand reset-rich-editing
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function reset_rich_editing( $args, $assoc_args ) {
		$dry_run          = isset( $assoc_args[ 'dry-run' ] ) && $assoc_args[ 'dry-run' ];
		$users_with_roles = $this->get_users_with_roles();

		WP_CLI::line();

		foreach ( $users_with_roles as $user_id => $user_login ) {
			/*
			 * Check that it's string 'false' rather than boolean false, because we only want to flip existing
			 * settings, not add new settings, because that leaves us the option of programmatically setting
			 * the value in the future based on whether or not it's already been set.
			 */
			if ( 'false' === get_user_meta( $user_id, 'rich_editing', true ) ) {
				$user_status = sprintf( "Reset %s.", $user_login );

				if ( $dry_run ) {
					$user_status = '(dry-run) ' . $user_status;
				} else {
					update_user_meta( $user_id, 'rich_editing', 'true' );
				}

				WP_CLI::line( $user_status );
			}
		}

		WP_CLI::line();
		WP_CLI::success( 'Reset rich_editing for all users with roles on a site.' );
	}

	/**
	 * Get all of the users in the network who have a role on at least one site
	 *
	 * @return array
	 */
	protected function get_users_with_roles() {
		$sites = wp_get_sites( array( 'limit' => false ) );
		$users = array();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$raw_users = get_users();

			foreach ( $raw_users as $raw_user ) {
				$users[ $raw_user->ID ] = $raw_user->user_login;
			}

			restore_current_blog();
		}

		return $users;
	}
}
