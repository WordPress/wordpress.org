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
				self::display_release_hold();
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
	 * Display the release hold status and (for reviewers) a force-release control.
	 *
	 * A current release is held from the update API either by an automated security
	 * review block, which has no expiry, or by the release cooldown while its window
	 * runs. Bails when there's no current release, or when it is neither blocked nor
	 * still in cooldown. A block takes precedence in the message, since it is the hold
	 * that actually withholds the version and it outlasts the cooldown.
	 */
	protected static function display_release_hold() {
		$post = get_post();

		// Resolved from the stable tag, so the hold shows even when the Version header is empty or disagrees.
		$release = API_Update_Updater::get_current_release( $post );
		if ( ! $release ) {
			return;
		}

		$release_version = $release['version'];
		$is_blocked      = API_Update_Updater::is_release_blocked( $release );

		$release_delay  = (int) ( $release['release_delay'] ?? 0 );
		$cooldown_until = $release_delay ? API_Update_Updater::compute_release_time( $post, $release ) + $release_delay : 0;
		$in_cooldown    = $cooldown_until > time();

		if ( ! $is_blocked && ! $in_cooldown ) {
			return;
		}

		// The force-release reason justifies the override; a block is lifted on review, a cooldown bypassed for urgency.
		$reason_placeholder = $is_blocked
			? __( 'e.g. reviewed the findings, not exploitable', 'wporg-plugins' )
			: __( 'e.g. urgent security fix for CVE-…', 'wporg-plugins' );

		?>
		<div class="misc-pub-section misc-pub-release-hold">
			<p>
			<?php
			if ( $is_blocked ) {
				printf(
					/* translators: %s: version */
					esc_html__( 'Version %s is blocked by an automated security review, it is withheld from the update API, and sites keep receiving the previously distributed version.', 'wporg-plugins' ),
					esc_html( $release_version )
				);
			} else {
				printf(
					/* translators: 1: version, 2: relative time until cooldown expires, 3: absolute UTC timestamp */
					esc_html__( 'Version %1$s is in the release cooldown, it will be served to sites in %2$s (at %3$s UTC).', 'wporg-plugins' ),
					esc_html( $release_version ),
					esc_html( human_time_diff( time(), $cooldown_until ) ),
					esc_html( gmdate( 'Y-m-d H:i', $cooldown_until ) )
				);
			}
			?>
			</p>
			<?php if ( current_user_can( 'plugin_review', $post ) ) : ?>
				<p>
					<label for="force_release_reason"><?php esc_html_e( 'Force-release reason (required):', 'wporg-plugins' ); ?></label>
					<textarea
						id="force_release_reason"
						name="force_release_reason"
						rows="2"
						style="width: 100%;"
						placeholder="<?php echo esc_attr( $reason_placeholder ); ?>"
					></textarea>
				</p>
				<p>
					<button type="submit" name="force_release_tag" value="<?php echo esc_attr( $release['tag'] ); ?>" class="button">
						<?php
						printf(
							/* translators: %s: version */
							esc_html__( 'Force-release %s now', 'wporg-plugins' ),
							esc_html( $release_version )
						);
						?>
					</button>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Save handler for reviewer force-release submissions from the Controls metabox.
	 *
	 * @param int $post_id The post being saved.
	 */
	public static function save_post( $post_id ) {
		if ( empty( $_POST['force_release_tag'] ) ) {
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

		// Staleness guard on the release identity: a commit that moved the stable tag since the form rendered lands a different release here.
		$release       = API_Update_Updater::get_current_release( $post );
		$submitted_tag = sanitize_text_field( wp_unslash( $_POST['force_release_tag'] ) );
		if ( ! $release || $submitted_tag !== (string) $release['tag'] ) {
			return;
		}

		$reason = isset( $_POST['force_release_reason'] )
			? trim( sanitize_textarea_field( wp_unslash( $_POST['force_release_reason'] ) ) )
			: '';
		if ( ! $reason ) {
			return;
		}

		API_Update_Updater::force_release( $post->post_name, $reason );
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

				<?php /* translators: %s: Close reason. */ ?>
				<p><?php printf( esc_html__( 'Close Reason: %s', 'wporg-plugins' ), '<strong>' . $close_reason_label . '</strong>' ); ?></p>

			<?php elseif ( 'disabled' === $post->post_status ) : ?>

				<?php /* translators: %s: Disable reason. */ ?>
				<p><?php printf( esc_html__( 'Disable Reason: %s', 'wporg-plugins' ), '<strong>' . $close_reason_label . '</strong>' ); ?></p>

			<?php elseif ( 'rejected' === $post->post_status ) : ?>

				<p><?php printf(
						esc_html__( 'Rejection Reason: %s', 'wporg-plugins' ),
						'<strong>' . $rejection_reason_label . '</strong>'
				); ?></p>

			<?php elseif ( 'publish' === $post->post_status ) : ?>

				<?php if ( $active_installs >= '20000' ) : ?>
					<p><strong><?php esc_html_e( 'Notice:', 'wporg-plugins' ); ?></strong> <?php esc_html_e( 'Due to the large volume of active users, the developers should be warned and their plugin remain open save under extreme circumstances.', 'wporg-plugins' ); ?>.</p>
				<?php endif; ?>

			<?php endif; ?>

			<?php if ( array_intersect( $statuses, [ 'closed', 'disabled' ] ) ) { ?>
				<p>
					<label for="close_reason"><?php esc_html_e( 'Close/Disable Reason:', 'wporg-plugins' ); ?></label>
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
						<label for="rejection_reason"><?php esc_html_e( 'Rejection Reason:', 'wporg-plugins' ); ?></label>
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
				<td><?php esc_html_e( 'Status:', 'wporg-plugins' ); ?></td>
				<td><strong><?php echo esc_html( get_post_status_object( $post->post_status )->label ); ?></strong></td>
			</tr>

			<tr>
				<td><?php esc_html_e( 'Version:', 'wporg-plugins' ); ?></td>
				<td><strong><?php echo esc_html( $post->version ); ?></strong></td>
			</tr>

			<tr>
				<td><?php esc_html_e( 'Updated:', 'wporg-plugins' ); ?></td>
				<td><strong><?php
					printf(
						'<span title="%s">%s ago</span>',
						esc_attr( $post->last_updated ),
						human_time_diff( strtotime( $post->last_updated ) )
					);
				?></strong></td>
			</tr>

			<tr>
				<td><?php esc_html_e( 'Submitted:', 'wporg-plugins' ); ?></td>
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
				<td><?php esc_html_e( 'Installs:', 'wporg-plugins' ); ?></td>
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
				<td><?php esc_html_e( 'Tested With:', 'wporg-plugins' ); ?></td>
				<td><strong><?php printf( 'WordPress %s', esc_html( $post->tested ) ); ?></strong></td>
			</tr>
			<?php endif; ?>
		</table><!-- .misc-pub-section -->
		<?php
	}

}
