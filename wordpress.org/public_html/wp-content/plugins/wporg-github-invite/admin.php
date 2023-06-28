<?php
namespace WordPressdotorg\GitHub\MakeInviter;

function render() {
	$allowed_teams = get_allowed_teams();
	$all_teams     = get_teams();
	$teams         = [];

	foreach ( $allowed_teams as $id ) {
		$team = wp_list_filter( $all_teams, [ 'id' => $id ] );
		if ( ! $team ) {
			continue;
		}
		$team = array_shift( $team );

		// Add the parent..
		if ( isset( $team->parent ) ) {
			$teams[ $team->parent->id ] = $team->parent;
		}

		$teams[ $team->id ] = $team;
	}

	// Add any sub-teams that are not allowed to be selected..
	foreach ( $teams as $team ) {
		foreach ( $all_teams as $t ) {
			if ( $t->parent && $t->parent->id === $team->id && ! in_array( $t->id, $allowed_teams, true ) ) {
				$teams[ $t->id ] = clone $t;
			}
		}
	}

	// Mark any as disabled as needed.
	foreach ( $teams as $team ) {
		$team->disabled = ! in_array( $team->id, $allowed_teams, true );
	}

	if ( isset( $_GET['updated'] ) ) {
		$class   = 'success';
		$message = '';
		switch ( $_GET['updated'] ) {
			case 'success':
				$message = 'Success, invitation sent!';
				break;
			case 'canceled':
				$message = 'Invitation canceled';
				break;
			case 'error':
				$class   = 'error';
				$message = 'An error occurred inviting this collaborator!';
				break;
			case 'settings':
				$message = 'Settings updated';
				break;
			case 'no-github':
				$class   = 'error';
				$message = 'The specified WordPress.org account does not have a linked GitHub account.';
				break;
		}

		if ( $message && isset( $_GET['message'] ) ) {
			$message .= '<br><em>' . esc_html( $_GET['message'] ) . '</em>';
		}

		if ( $message ) {
			printf(
				'<div class="notice notice-%s is-dismissable"><p>%s</p></div>',
				$class,
				$message
			);
		}
	}

	?>
	<div class="wrap" id="wp_learn_admin">
	<h1>Invite GitHub Member</h1>
	<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
		<input type="hidden" name="action" value="github_invite">
		<?php wp_nonce_field( 'github_invite' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="invite">GitHub Email, GitHub URL, WordPress.org user slug, or WordPress.org Profile URL</label></th>
				<td><input type="text" name="invite" id="invite" class="regular-text" placeholder="https://profiles.wordpress.org/<?php echo wp_get_current_user()->user_nicename; ?>/"></td>
			</tr>
			<tr>
				<th scope="row"><label for="team">Teams</label></th>
				<td>
					<?php
					if ( ! $teams ) {
						echo '<em>No teams have been configured. Please ask a super-admin via #meta to enable at least one team.</em>';
					}

					render_team_list( $teams );
					?>
				</td>
			</tr>
		</table>
		<?php submit_button( 'Invite Collaborator' ); ?>
	</form>

	<h1>Pending Invites</h1>
	<form>
		<table class="form-table">
			<tr>
				<th scope="row">Pending Invitations</th>
				<td>
					<?php
					$pending_invites = get_pending_invites();
					if ( ! $pending_invites ) {
						echo '<em>No pending invitations</em>';
					}

					foreach ( $pending_invites as $pending ) {
						$can_cancel = in_array( $pending->id, get_option( 'invited_gh_users', [] ), true ) || is_super_admin();
						$cancel_url = $can_cancel ? wp_nonce_url( admin_url( 'admin-post.php?action=github_cancel_invite&invite=' . $pending->id ), 'github_cancel_invite_' . $pending->id ) : false;
						printf(
							'<p>
								<strong><code>%s</code></strong>
								<em>%s ago</em>
								%s
							</p>',
							$pending->login ?: $pending->email,
							human_time_diff( strtotime( $pending->created_at ) ),
							$cancel_url ? '<a class="button" href="' . esc_url( $cancel_url ) . '">Cancel</a>' : ''
						);
					}
					?>
				</td>
			</tr>
		</table>
	</form>
	<?php

	// Allow super-admins to set the teams the site users can invite for.
	if ( is_super_admin() ) {
		?>
		<hr>
		<h1>Settings</h1>
		<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
			<input type="hidden" name="action" value="github_invite_settings">
			<?php wp_nonce_field( 'github_invite_settings' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="team">Allowed Team(s) for this site <span style="color: red">(super-admin only)</span></label></th>
					<td>
						<?php render_team_list( $all_teams, $allowed_teams ); ?>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Settings' ); ?>
		</form>
		<?php
	}
}

/**
 * Render the team list.
 */
function render_team_list( $teams, $checked = array(), $for_parent = 0 ) {
	if ( $for_parent ) {
		$teams = array_filter( $teams, function( $t ) use ( $for_parent ) {
			return $for_parent === $t->parent->id ?? 0;
		} );

		if ( ! $teams ) {
			return false;
		}

		echo '<div class="childen" style="margin-left: 1em">';
	}

	foreach ( $teams as $team ) {
		if ( isset( $team->parent ) && ! $for_parent ) {
			continue;
		}

		?>
		<div>
			<label>
				<input
					type="checkbox"
					name="team_id[]"
					value="<?php echo esc_attr( $team->id ) ?>"
					<?php
						checked( in_array( $team->id, $checked ) );
						disabled( ! empty( $team->disabled ) || in_array( $team->id, get_never_teams() ) );
					?>
				/>
				<?php echo esc_html( $team->name ) ?>
			</label>
			<?php
			// Any child teams of this team?
			render_team_list( $teams, $checked, $team->id );
			?>
		</div>
	<?php }

	if ( $for_parent ) {
		echo '</div>';
	}
}
