<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

use WordPressdotorg\Plugin_Directory\Admin\Status_Transitions;
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
	 */
	static function display() {
		?>
		<div class="submitbox" id="submitpost">
			<div id="misc-publishing-actions">
				<?php
				self::display_meta();
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
			$statuses = Status_Transitions::get_allowed_transitions( $post->post_status );
		}

		$close_reasons   = Template::get_close_reasons();
		$close_reason    = (string) get_post_meta( $post->ID, '_close_reason', true );
		$active_installs = (int) get_post_meta( $post->ID, 'active_installs', true );

		$reason_label   = Template::get_close_reason();
		$reason_unknown = ( _x( 'Unknown', 'unknown close reason', 'wporg-plugins' ) === $reason_label );
		?>
		<div class="misc-pub-section misc-pub-plugin-status">

			<?php if ( 'closed' === $post->post_status ) : ?>

				<p><?php printf( __( 'Close Reason: %s', 'wporg-plugins' ), '<strong>' . $reason_label . '</strong>' ); ?></p>

			<?php elseif ( 'disabled' === $post->post_status ) : ?>

				<p><?php printf( __( 'Disable Reason: %s', 'wporg-plugins' ), '<strong>' . $reason_label . '</strong>' ); ?></p>

			<?php elseif ( 'publish' === $post->post_status ) : ?>

				<?php if ( $active_installs >= '20000' ) : ?>
					<p><strong><?php _e( 'Notice:', 'wporg-plugins' ); ?></strong> <?php _e( 'Due to the large volume of active users, the developers should be warned and their plugin remain open save under extreme circumstances.', 'wporg-plugins' ); ?>.</p>
				<?php endif; ?>

			<?php endif; ?>

			<?php
			if (
					( in_array( 'closed', $statuses, true ) || in_array( 'disabled', $statuses, true ) )
				&&
					( ! in_array( $post->post_status, array( 'closed', 'disabled' ) ) || $reason_unknown )
				) :
				?>

				<p>
					<label for="close_reason"><?php _e( 'Close/Disable Reason:', 'wporg-plugins' ); ?></label>
					<select name="close_reason" id="close_reason">
						<?php foreach ( $close_reasons as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $close_reason ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

			<?php endif; ?>

			<?php foreach ( $statuses as $status ) {
				echo '<p>';

				if ( 'pending' === $status && ! $post->assigned_reviewer ) {
					printf(
						'<p class="pending-assign"><button onclick="%s" type="submit" name="post_status" value="%s" class="button set-plugin-status button-primary">%s</button></p>',
						esc_attr( "document.getElementById('assigned_reviewer').value = userSettings.uid" ),
						esc_attr( $status ),
						esc_attr__( 'Mark as Pending & Assign Review', 'wporg-plugins' ),
					);
				}

				printf(
					'<button type="submit" name="post_status" value="%s" class="button set-plugin-status">%s</button>',
					esc_attr( $status ),
					self::get_status_button_label( $status )
				);

				echo '</p>';

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
				<td><strong><?php printf( '<span title="%s">%s ago</span>', esc_attr( $post->last_updated ), human_time_diff( strtotime( $post->last_updated ) ) ); ?></strong></td>
			</tr>

			<tr>
				<td><?php _e( 'Installs:', 'wporg-plugins' ); ?></td>
				<td><strong><?php echo Template::active_installs( false, $post ); ?></strong></td>
			</tr>

			<?php if ( $post->tested ) : ?>
			<tr>
				<td><?php _e( 'Tested With:', 'wporg-plugins' ); ?></td>
				<td><strong><?php printf( 'WordPress %s', $post->tested ); ?></strong></td>
			</tr>
			<?php endif; ?>
		</table><!-- .misc-pub-section -->
		<?php
	}

}
