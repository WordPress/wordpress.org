<?php
namespace WordPressdotorg\Make\Badge_Management;
use function WordPressdotorg\Profiles\{ assign_badge, remove_badge, has_badge, get_badges, get_users_with_badge };

if ( ! defined( 'WPINC' ) ) {
	die;
}

function render() {

	echo '<div class="wrap">';
	echo '<h1>Profile Badges</h1>';

	$team_badges = get_option( 'wporg_profile_badges', [] );

	$tabs = [];
	$tabs['manage_badges'] = 'Add/Remove User Badges';

	foreach ( $team_badges as $slug ) {
		$tabs[ 'list_users:' . $slug ] = get_badges()[ $slug ] ?? $slug;
	}

	$tabs['settings'] = 'Settings';

	// Create a set of tabs for managing badges and listing users with badges.
	$active_tab = ( $_GET['tab'] ?? '' ) ?: array_key_first( $tabs );
	$active_tab = array_key_exists( $active_tab, $tabs ) ? $active_tab : array_key_first( $tabs );

	echo '<h2 class="nav-tab-wrapper">';
	foreach ( $tabs as $tab_key => $tab_name ) {
		printf(
			'<a href="%s" class="nav-tab %s">%s</a>',
			esc_url( add_query_arg( 'tab', urlencode( $tab_key ) ) ),
			$active_tab === $tab_key ? 'nav-tab-active' : '',
			esc_html( $tab_name )
		);
	}
	echo '</h2>';

	// Let the user know who else can manage badges on this site.
	if ( 'manage_network' === get_required_cap() ) {
		echo '<div class="notice notice-success"><p>Only super admins can manage badges on this site.</p></div>';
	} else {
		printf(
			'<div class="notice notice-success"><p>Super admins, and users with a role on this site and the <code>%s</code> capability, can manage badges.</p></div>',
			esc_html( get_required_cap() )
		);
	}

	switch ( explode( ':', $active_tab )[0] ) {
		case 'list_users':
			render_list_users_tab( explode( ':', $active_tab )[1] ?? '' );
			break;
		case 'manage_badges':
			render_manage_tab();
			break;
		case 'settings':
			render_settings();
			break;
	}

	echo '</div>';
}

function render_list_users_tab( $slug ) {
	$badges      = get_badges();
	$team_badges = get_option( 'wporg_profile_badges', [] );
	$slug        = $slug ?: array_key_first( $team_badges );

	$users = get_users_with_badge( $slug );

	// List users with badges.
	echo '<h2>Users with Badge "' . ( $badges[ $slug ] ?? '' ) . '" (' . number_format_i18n( count( $users ) ) .')</h2>';
	if ( $users ) {

		// Lots of WP_User objects is extra hungry.
		if ( count( $users ) > 1000 ) {
			ini_set( 'memory_limit', '512M' );
		}

		// Pre-cache the users.
		cache_users( $users );

		$users = array_map( function( $user_id ) {
			return get_user_by( 'ID', $user_id );
		}, $users );

		usort( $users, function( $a, $b ) {
			return strtolower( $a->display_name ?: $a->user_login ) <=> strtolower( $b->display_name ?: $b->user_login );
		} );

		echo '<ul>';
		foreach ( $users as $user ) {
			printf(
				'<li>
					<img
						src="%s"
						alt="%s"
						width="32" height="32"
						class="avatar"
						style="vertical-align:middle;border-radius:50%%;margin-right:8px;"
					>
					<div style="display: inline-block; vertical-align: middle;">
						<div>
							<a href="%s">
								<strong>%s</strong>
							</a>
						</div>
						<div style="font-size: 12px; color: #666;">
							%s
						</div>
					</div>
				</li>',
				esc_url( get_avatar_url( $user->ID, [ 'size' => 32 ] ) ),
				esc_html( $user->display_name ),
				esc_attr( 'https://profiles.wordpress.org/' . $user->user_nicename . '/' ),
				esc_html( $user->display_name ?: $user->user_login ),
				esc_html( $user->user_login )
			);

		}
		echo '</ul>';
		echo '<textarea rows="10" style="width:100%">' . implode( ', ', wp_list_pluck( $users, 'user_login' ) ) . '</textarea>';
	} else {
		echo '<p><em>No users have this badge.</em></p>';
	}
}

function render_manage_tab() {

	$note_to_team = get_option( 'wporg_profile_badge_note_to_team', '' );
	if ( $note_to_team ) {
		echo '<div class="notice notice-info"><p>' . wp_kses_post( nl2br( $note_to_team ) ) . '</p></div>';
	}

	$team_badges = get_option( 'wporg_profile_badges', [] );

	if ( ! $team_badges ) {
		echo '<p><em>No badges have been configured. Please ask a super-admin via #meta to enable at least one badge.</em></p>';
		return;
	}

	// Render a status message if needed.
	if ( isset( $_GET['updated'] ) ) {
		$messages = [];
		if ( 'true' === $_GET['updated'] ) {
			$messages[] = sprintf(
				'<strong>%d</strong> user(s) processed, <strong>%d</strong> skipped, <strong>%d</strong> unknown, <strong>%d</strong> errors.',
				intval( $_GET['processed'] ?? 0 ),
				intval( $_GET['skipped'] ?? 0 ),
				intval( $_GET['unknown'] ?? 0 ),
				intval( $_GET['errors'] ?? 0 )
			);
		}

		if ( $messages ) {
			echo '<div id="message" class="updated notice is-dismissible"><p>' . implode( '<br>', $messages ) . '</p></div>';
		}
	}

	// Render a form with a textarea for the user, a selection for the action, and a checkbox of each team that this site can manage.
	?>
	<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
		<input type="hidden" name="action" value="wporg_profile_manage_badges">
		<?php wp_nonce_field( 'wporg_profile_badges' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="users">WordPress.org User(s)</label></th>
				<td>
					<textarea name="users" id="users" class="large-text code" rows="5" placeholder="One user per line, commas, etc. Accepts WordPress.org user slugs, profile URLs, email addresses, or user IDs."></textarea>
					<p class="description">You can enter multiple users, one per line, commas, etc.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="action">Action</label></th>
				<td>
					<select name="badge-action" id="action">
						<option value="add">Add Badge</option>
						<option value="remove">Remove Badge</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="badge">Badge</label></th>
				<td>
					<?php
					foreach ( $team_badges as $slug ) {
						$badges = get_badges();
						if ( isset( $badges[ $slug ] ) ) {
							echo '<div><label><input type="checkbox" name="badges[]" value="' . esc_attr( $slug ) . '">' . esc_html( $badges[ $slug ] ) . '</label></div>';
						}
					}
					?>
				</td>
			</tr>
		</table>
		<?php submit_button( 'Make Changes' ); ?>
	</form>
	<?php
}

/**
 * Renders the settings tab.
 */
function render_settings() {

	// Add a note to the team.
	$note_to_team = get_option( 'wporg_profile_badge_note_to_team', '' );

	// The minimum capability required to manage badges on this site.
	$current_cap = get_required_cap();

	// Allow super-admins to set the badges users on this site can assign.
	$allowed_badges = get_option( 'wporg_profile_badges', [] );

	?>
	<h1>Settings</h1>
	<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
		<input type="hidden" name="action" value="badge_settings">
		<?php wp_nonce_field( 'badge_settings' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="note_to_team">Note to Badge Managers</label></th>
				<td>
					<textarea name="note_to_team" id="note_to_team" class="large-text code" rows="5" placeholder="This note will be shown to anyone managing badges on this site."><?php echo esc_textarea( $note_to_team ); ?></textarea>
					<p class="description">This note will be shown to anyone managing badges on this site. Use it to suggest which badges should be granted, or any other reminders.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="required_role">Required Cap to Manage Badges</label></th>
				<td>
					<select name="required_role" id="required_role" <?php disabled( ! current_user_can_change_required_cap() ); ?>>
						<?php foreach ( get_required_cap_choices() as $cap => $desc ) : ?>
							<option
								value="<?php echo esc_attr( $cap ); ?>"
								<?php selected( $current_cap, $cap ); ?>
								<?php disabled( ! current_user_can( $cap ) ); ?>
							>
								<?php echo esc_html( $desc ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">Select the minimum user role required to manage badges on this site. Only users with a role on this site qualify; super admins can always manage badges, and only a super admin can limit it to super admins.</p>
					<?php if ( ! current_user_can_change_required_cap() ) : ?>
						<p class="description"><em>Only administrators can change this setting.</em></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="team">Allowed Badges(s) for this site <span style="color: red">(super-admin only)</span></label></th>
				<td>
					<?php
					echo '<p><em>Only super-admins can change this setting. Please ask someone in #meta to enable a badge if needed.</em></p>';

					foreach ( get_badges() as $slug => $badge ) {
						?>
						<div>
							<label>
								<input
									type="checkbox"
									name="badges[]"
									value="<?php echo esc_attr( $slug ) ?>"
									<?php checked( in_array( $slug, $allowed_badges, true ) ); ?>
									<?php disabled( ! is_super_admin() ); ?>
								>
								<?php echo esc_html( $badge ); ?>
							</label>
						</div>
						<?php
					}
					?>
				</td>
			</tr>
		</table>
		<?php submit_button( 'Save Settings' ); ?>
	</form>
	<?php
}
