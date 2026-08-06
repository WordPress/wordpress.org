<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

use WordPressdotorg\Plugin_Directory\Admin\Status_Transitions;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * The Plugin Controls / Publish metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Controls {

	/**
	 * Displays the Publish metabox for plugins.
	 * The HTML here mostly matches what Core uses.
	 *
	 * NOTE: The input[type="submit"].screen-reader-text is such that the default
	 *       form submit method is a button whose submission causes no action
	 *       (such as approval/rejection/assign). This is used for submit-by-enter.
	 *       See https://meta.trac.wordpress.org/ticket/6635.
	 */
	static function display() {
		?>
		<div class="submitbox" id="submitpost">
			<input type="submit" name="save_changes" class="screen-reader-text" />
			<div id="misc-publishing-actions">
				<?php
				self::display_meta();
				self::display_release_cooldown();
				self::display_post_status();
				?>
			</div>

			<div id="major-publishing-actions">
				<div id="publishing-action">
					<span class="spinner"></span>
					<input type="submit" name="save_changes" id="publish" class="button button-primary button-large" value="<?php esc_attr_e( 'Save Changes', 'wporg-plugins' ); ?>">
				</div>
				<div class="clear"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display the release hold status and (for reviewers) force-release and block controls.
	 *
	 * Shows one of three messages: a countdown while a release is cooling down, a block notice
	 * when the release is being held (which outlasts the cooldown window), or a plain notice
	 * that the version hasn't reached sites yet. Reviewers can force-release in any of them,
	 * and can block any version that isn't already served. Bails when there's no current
	 * release, or when the version is being served and isn't held.
	 */
	protected static function display_release_cooldown() {
		$post = get_post();

		// Only plugins the update API serves from have a release to gate.
		if ( ! in_array( $post->post_status, array( 'publish', 'disabled', 'closed' ), true ) ) {
			return;
		}

		$version = get_post_meta( $post->ID, 'version', true );
		if ( ! $version ) {
			return;
		}

		$release = Plugin_Directory::get_release( $post, $version );
		if ( ! $release ) {
			return;
		}

		$blocked        = API_Update_Updater::is_release_blocked( $release );
		$release_delay  = (int) ( $release['release_delay'] ?? 0 );
		$cooldown_until = API_Update_Updater::compute_release_time( $post, $release ) + $release_delay;
		$in_cooldown    = $release_delay && $cooldown_until > time();

		/*
		 * A version can be held right up until it's written to `update_source`, which lags the
		 * end of the cooldown by however long the deferred event takes to run. The controls key
		 * on whether it's actually being served — the same condition block_release() applies —
		 * so they don't disappear during that window.
		 */
		$unserved = ( API_Update_Updater::get_served_version( $post->post_name ) !== (string) $version );

		// Nothing to surface once the version is being served and isn't held.
		if ( ! $blocked && ! $unserved ) {
			return;
		}

		?>
		<div class="misc-pub-section misc-pub-release-cooldown">
			<p>
			<?php
			if ( $blocked ) {
				printf(
					/* translators: %s: version */
					esc_html__( 'Version %s is blocked and is being held from sites. Force-releasing overrides the block.', 'wporg-plugins' ),
					esc_html( $version )
				);
			} elseif ( $in_cooldown ) {
				printf(
					/* translators: 1: version, 2: relative time until cooldown expires, 3: absolute UTC timestamp */
					esc_html__( 'Version %1$s is in the release cooldown — it will be served to sites in %2$s (at %3$s UTC).', 'wporg-plugins' ),
					esc_html( $version ),
					esc_html( human_time_diff( time(), $cooldown_until ) ),
					esc_html( gmdate( 'Y-m-d H:i', $cooldown_until ) )
				);
			} else {
				printf(
					/* translators: %s: version */
					esc_html__( 'Version %s has not been served to sites yet.', 'wporg-plugins' ),
					esc_html( $version )
				);
			}
			?>
			</p>
			<?php if ( current_user_can( 'plugin_review', $post ) ) : ?>
				<p>
					<label for="release_action_reason"><?php esc_html_e( 'Reason (required):', 'wporg-plugins' ); ?></label>
					<textarea
						id="release_action_reason"
						name="release_action_reason"
						rows="2"
						style="width: 100%;"
						placeholder="<?php esc_attr_e( 'e.g. urgent security fix for CVE-…', 'wporg-plugins' ); ?>"
					></textarea>
				</p>
				<p>
					<button type="submit" name="force_release_version" value="<?php echo esc_attr( $version ); ?>" class="button">
						<?php
						printf(
							/* translators: %s: version */
							esc_html__( 'Force-release %s now', 'wporg-plugins' ),
							esc_html( $version )
						);
						?>
					</button>
					<?php if ( $unserved && ! $blocked ) : ?>
						<button type="submit" name="block_release_version" value="<?php echo esc_attr( $version ); ?>" class="button">
							<?php
							printf(
								/* translators: %s: version */
								esc_html__( 'Block %s from sites', 'wporg-plugins' ),
								esc_html( $version )
							);
							?>
						</button>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Save handler for reviewer force-release and block submissions from the Controls metabox.
	 *
	 * @param int $post_id The post being saved.
	 */
	public static function save_post( $post_id ) {
		$is_force_release = ! empty( $_POST['force_release_version'] );
		$is_block         = ! empty( $_POST['block_release_version'] );
		if ( ! $is_force_release && ! $is_block ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'plugin' !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'plugin_review', $post ) ) {
			return;
		}

		// Re-verify the post.php form nonce that core already checked, to satisfy phpcs
		// and to make the security boundary explicit.
		check_admin_referer( 'update-post_' . $post_id );

		$version           = get_post_meta( $post->ID, 'version', true );
		$submitted_version = sanitize_text_field( wp_unslash( $is_force_release ? $_POST['force_release_version'] : $_POST['block_release_version'] ) );
		if ( $submitted_version !== $version ) {
			// Submitted version doesn't match current — a newer commit landed since the form was rendered.
			self::add_notice(
				sprintf(
					/* translators: 1: submitted version, 2: current version */
					__( 'No action taken: version %1$s is no longer the current version — %2$s has since been committed.', 'wporg-plugins' ),
					$submitted_version,
					$version
				)
			);

			return;
		}

		$reason = isset( $_POST['release_action_reason'] )
			? trim( sanitize_textarea_field( wp_unslash( $_POST['release_action_reason'] ) ) )
			: '';
		if ( ! $reason ) {
			self::add_notice( __( 'No action taken: a reason is required to block or force-release a version.', 'wporg-plugins' ) );

			return;
		}

		if ( $is_force_release ) {
			self::handle_force_release( $post, $version, $reason );
		} else {
			self::handle_block( $post, $version, $reason );
		}
	}

	/**
	 * Force-release the current version and report the outcome to the reviewer.
	 *
	 * @param \WP_Post $post    The plugin post.
	 * @param string   $version The version being released.
	 * @param string   $reason  The reason recorded in the audit log.
	 */
	protected static function handle_force_release( $post, $version, $reason ) {
		if ( API_Update_Updater::force_release( $post->post_name, $reason ) ) {
			self::add_notice(
				sprintf(
					/* translators: %s: version */
					__( 'Version %s has been released and is being served to sites.', 'wporg-plugins' ),
					$version
				),
				'updated'
			);

			return;
		}

		self::add_notice(
			sprintf(
				/* translators: %s: version */
				__( 'Version %s could not be released. Nothing was changed — please try again.', 'wporg-plugins' ),
				$version
			)
		);
	}

	/**
	 * Hold the current version back and report the outcome to the reviewer.
	 *
	 * A refusal is indistinguishable from a success from the edit screen alone — the release
	 * section disappears either way once the version is served — so every outcome says which
	 * it was.
	 *
	 * @param \WP_Post $post    The plugin post.
	 * @param string   $version The version being held.
	 * @param string   $reason  The reason recorded against the block and in the audit log.
	 */
	protected static function handle_block( $post, $version, $reason ) {
		$held = API_Update_Updater::block_release(
			$post->post_name,
			array(
				'reason'     => $reason,
				'blocked_by' => wp_get_current_user()->user_login,
			)
		);

		if ( $held ) {
			self::add_notice(
				sprintf(
					/* translators: %s: version */
					__( 'Version %s is now held from sites. Force-releasing it overrides the block.', 'wporg-plugins' ),
					$version
				),
				'updated'
			);

			return;
		}

		if ( API_Update_Updater::get_served_version( $post->post_name ) === (string) $version ) {
			self::add_notice(
				sprintf(
					/* translators: %s: version */
					__( 'Version %s is already being served to sites and can no longer be held back. Close or disable the plugin if it needs to stop being offered.', 'wporg-plugins' ),
					$version
				)
			);

			return;
		}

		self::add_notice(
			sprintf(
				/* translators: %s: version */
				__( 'Version %s could not be held. Nothing was changed — please try again.', 'wporg-plugins' ),
				$version
			)
		);
	}

	/**
	 * Queue a notice for the reviewer, to be shown after the post editor redirects.
	 *
	 * Reuses the `wporg-plugins` settings-errors group that Admin\Customizations renders on
	 * `all_admin_notices`; the redirect is flagged so `settings_errors()` picks the transient
	 * up on the way back.
	 *
	 * @param string $message The message to display.
	 * @param string $type    Notice type, 'error' or 'updated'. Default 'error'.
	 */
	protected static function add_notice( $message, $type = 'error' ) {
		set_transient(
			'settings_errors',
			array(
				array(
					'setting' => 'wporg-plugins',
					'code'    => 'release-action',
					'message' => $message,
					'type'    => $type,
				),
			)
		);

		add_filter( 'redirect_post_location', array( __CLASS__, 'flag_settings_updated' ) );
	}

	/**
	 * Mark the post-editor redirect so queued notices are displayed on arrival.
	 *
	 * @param string $location The redirect destination.
	 * @return string
	 */
	public static function flag_settings_updated( $location ) {
		return add_query_arg( 'settings-updated', 'true', $location );
	}

	/**
	 * Get button label for setting the plugin status.
	 *
	 * @param string $post_status Plugin post status.
	 * @return string Status button label.
	 */
	public static function get_status_button_label( $post_status ) {
		switch ( $post_status ) {
			case 'approved':
				$label = __( 'Approve', 'wporg-plugins' );
				break;
			case 'rejected':
				$label = __( 'Reject', 'wporg-plugins' );
				break;
			case 'publish':
				$label = __( 'Open', 'wporg-plugins' );
				break;
			case 'disabled':
				$label = __( 'Disable', 'wporg-plugins' );
				break;
			case 'closed':
				$label = __( 'Close', 'wporg-plugins' );
				break;
			case 'new':
				$label = __( 'Mark as Pending Initial Review', 'wporg-plugins' );
				break;
			case 'pending':
			default:
				$label = __( 'Mark as Pending', 'wporg-plugins' );
				break;
		}

		return $label;
	}

	/**
	 * Displays the Plugin Status control in the Publish metabox.
	 */
	protected static function display_post_status() {
		$post = get_post();

		// Bail if the current user can't review plugins.
		if ( ! current_user_can( 'plugin_approve', $post ) && ! current_user_can( 'plugin_review', $post ) ) {
			return;
		}

		$statuses = array( 'new', 'pending' );

		if ( current_user_can( 'plugin_approve', $post ) ) {
			$statuses = Status_Transitions::get_allowed_transitions( $post->post_status, $post );
		}

		$close_reasons     = Template::get_close_reasons();
		$close_reason      = (string) get_post_meta( $post->ID, '_close_reason', true );
		$rejection_reasons = Template::get_rejection_reasons();
		$rejection_reason  = (string) get_post_meta( $post->ID, '_rejection_reason', true );
		$active_installs   = (int) get_post_meta( $post->ID, 'active_installs', true );

		$close_reason_label     = Template::get_close_reason();
		$close_reason_unknown   = ( _x( 'Unknown', 'unknown close reason', 'wporg-plugins' ) === $close_reason_label );
		$rejection_reason_label = $rejection_reasons[ $rejection_reason ] ?? $rejection_reasons[ 'other' ];
		?>
		<div class="misc-pub-section misc-pub-plugin-status">

			<?php if ( 'closed' === $post->post_status ) : ?>

				<p><?php printf( __( 'Close Reason: %s', 'wporg-plugins' ), '<strong>' . $close_reason_label . '</strong>' ); ?></p>

			<?php elseif ( 'disabled' === $post->post_status ) : ?>

				<p><?php printf( __( 'Disable Reason: %s', 'wporg-plugins' ), '<strong>' . $close_reason_label . '</strong>' ); ?></p>

			<?php elseif ( 'rejected' === $post->post_status ) : ?>

				<p><?php printf(
						__( 'Rejection Reason: %s', 'wporg-plugins' ),
						'<strong>' . $rejection_reason_label . '</strong>'
				); ?></p>

			<?php elseif ( 'publish' === $post->post_status ) : ?>

				<?php if ( $active_installs >= '20000' ) : ?>
					<p><strong><?php _e( 'Notice:', 'wporg-plugins' ); ?></strong> <?php _e( 'Due to the large volume of active users, the developers should be warned and their plugin remain open save under extreme circumstances.', 'wporg-plugins' ); ?>.</p>
				<?php endif; ?>

			<?php endif; ?>

			<?php if ( array_intersect( $statuses, [ 'closed', 'disabled' ] ) ) { ?>
				<p>
					<label for="close_reason"><?php _e( 'Close/Disable Reason:', 'wporg-plugins' ); ?></label>
					<select name="close_reason" id="close_reason">
						<?php foreach ( $close_reasons as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $close_reason ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php }

			foreach ( $statuses as $status ) {
				if ( 'pending' === $status && ! $post->assigned_reviewer ) {
					printf(
						'<p class="pending-assign"><button type="submit" name="post_status" value="%s" class="button set-plugin-status pending-and-assign button-primary">%s</button></p>',
						esc_attr( $status ),
						esc_attr__( 'Mark as Pending & Assign Review', 'wporg-plugins' ),
					);
				}

				if ( $status === 'rejected' ) { ?>
					<p>
						<label for="rejection_reason"><?php _e( 'Rejection Reason:', 'wporg-plugins' ); ?></label>
						<select name="rejection_reason" id="rejection_reason">
							<?php foreach ( $rejection_reasons as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $rejection_reason ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
				<?php }

				printf(
					'<p><button type="submit" name="post_status" value="%s" class="button set-plugin-status">%s</button></p>',
					esc_attr( $status ),
					self::get_status_button_label( $status )
				);
			} ?>
		</div><!-- .misc-pub-section -->
		<?php
	}

	/**
	 * Displays the most important plugin meta in the Publish metabox.
	 */
	protected static function display_meta() {
		$post = get_post();
		?>
		<table class="misc-pub-section misc-pub-meta">
			<tr>
				<td><?php _e( 'Status:', 'wporg-plugins' ); ?></td>
				<td><strong><?php echo esc_html( get_post_status_object( $post->post_status )->label ); ?></strong></td>
			</tr>

			<tr>
				<td><?php _e( 'Version:', 'wporg-plugins' ); ?></td>
				<td><strong><?php echo esc_html( $post->version ); ?></strong></td>
			</tr>

			<tr>
				<td><?php _e( 'Updated:', 'wporg-plugins' ); ?></td>
				<td><strong><?php
					printf(
						'<span title="%s">%s ago</span>',
						esc_attr( $post->last_updated ),
						human_time_diff( strtotime( $post->last_updated ) )
					);
				?></strong></td>
			</tr>

			<tr>
				<td><?php _e( 'Submitted:', 'wporg-plugins' ); ?></td>
				<td><strong><?php
					$submitted_date = min( array_filter( [
						$post->_submitted_date,           // Submitted date stored since 2017-04-11
						$post->_approved,                 // The approval date is the next best thing.
						strtotime( $post->post_date_gmt ) // Fallback to the post_date, which should be similar to approval date.
					] ) );

					printf(
						'<span title="%s">%s ago</span>',
						esc_attr( gmdate( 'Y-m-d H:i:s', $submitted_date ) ),
						human_time_diff( $submitted_date )
					);
				?></strong></td>
			</tr>

			<tr>
				<td><?php _e( 'Installs:', 'wporg-plugins' ); ?></td>
				<td><strong><?php echo Template::active_installs( false, $post ); ?></strong></td>
			</tr>

			<?php if (
				function_exists( 'WordPressdotorg\Stats\plugin_active_installs' ) &&
				$post->version &&
				'publish' === $post->post_status
			): ?>
			<tr>
				<td><?php echo esc_html( "Installs of {$post->version}:" ); ?></td>
				<td><strong><?php
					echo Template::format_active_installs_for_display(
						Template::sanitize_active_installs(
							\WordPressdotorg\Stats\plugin_active_installs( $post->post_name, $post->version )
						)
					);
				?></strong></td>
			</tr>
			<?php endif; ?>

			<?php if ( $post->tested ) : ?>
			<tr>
				<td><?php _e( 'Tested With:', 'wporg-plugins' ); ?></td>
				<td><strong><?php printf( 'WordPress %s', esc_html( $post->tested ) ); ?></strong></td>
			</tr>
			<?php endif; ?>
		</table><!-- .misc-pub-section -->
		<?php
	}

}
