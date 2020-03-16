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
	 * The HTML here matches what Core uses.
	 */
	static function display() {
		?>
		<div class="submitbox" id="submitpost">
			<div id="misc-publishing-actions">
				<?php
				self::display_post_status();

				if ( 'publish' === get_post_status() ) {
					self::display_tested_up_to();
				}
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
			case 'pending':
				$label = __( 'Mark as Pending', 'wporg-plugins' );
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
			<label for="post_status"><?php _e( 'Status:', 'wporg-plugins' ); ?></label>
			<strong id="plugin-status-display"><?php echo esc_html( get_post_status_object( $post->post_status )->label ); ?></strong>

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

			<?php foreach ( $statuses as $status ) : ?>

				<p><button type="submit" name="post_status" value="<?php echo esc_attr( $status ); ?>" class="button set-plugin-status">
					<?php echo self::get_status_button_label( $status ); ?>
				</button></p>

			<?php endforeach; ?>
		</div><!-- .misc-pub-section -->
		<?php
	}

	/**
	 * Displays the Tested Up To control in the Publish metabox.
	 */
	protected static function display_tested_up_to() {
		$post           = get_post();
		$tested_up_to   = (string) get_post_meta( $post->ID, 'tested', true );
		$unknown_string = _x( 'Unknown', 'unknown version', 'wporg-plugins' );
		?>
		<div class="misc-pub-section misc-pub-tested">
			<label for="tested_with"><?php _e( 'Tested With:', 'wporg-plugins' ); ?></label>
			<strong id="tested-with-display"><?php echo ( $tested_up_to ? sprintf( 'WordPress %s', $tested_up_to ) : $unknown_string ); ?></strong>
		</div><!-- .misc-pub-section -->
		<?php
	}

}
