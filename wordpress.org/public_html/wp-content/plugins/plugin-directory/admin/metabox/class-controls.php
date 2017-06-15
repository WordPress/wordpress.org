<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;
use WordPressdotorg\Plugin_Directory\Admin\Status_Transitions;

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
		echo '<div class="submitbox" id="submitpost">';
			echo '<div id="misc-publishing-actions">';
				self::display_post_status();

				if ( 'publish' === get_post_status() ) {
					self::display_tested_up_to();
				}
			echo '</div>';

			echo '<div id="major-publishing-actions"><div id="publishing-action">';
				echo '<span class="spinner"></span>';
				printf( '<input type="submit" name="save_changes" id="publish" class="button button-primary button-large" value="%s">', __( 'Save Changes', 'wporg-plugins' ) );
			echo '</div><div class="clear"></div></div>';
		echo '</div>';
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
				$label = __( 'Approve' );
				break;
			case 'rejected':
				$label = __( 'Reject' );
				break;
			case 'pending':
				$label = __( 'Mark as Pending' );
				break;
			case 'publish':
				$label = __( 'Open' );
				break;
			case 'disabled':
				$label = __( 'Disable' );
				break;
			case 'closed':
				$label = __( 'Close' );
				break;
			default:
				$label = __( 'Mark as Pending' );
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
		?>
		<div class="misc-pub-section misc-pub-plugin-status">
			<label for="post_status"><?php _e( 'Status:', 'wporg-plugins' ); ?></label>
			<strong id="plugin-status-display"><?php echo esc_html( get_post_status_object( $post->post_status )->label ); ?></strong>

			<p>
			<?php foreach ( $statuses as $status ) : ?>
				<button type="submit" name="post_status" value="<?php echo esc_attr( $status ); ?>" class="button set-plugin-status">
					<?php echo self::get_status_button_label( $status ); ?>
				</button>
			<?php endforeach; ?>
			</p>
		</div><!-- .misc-pub-section --><?php
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

		</div><!-- .misc-pub-section --><?php
	}

}
