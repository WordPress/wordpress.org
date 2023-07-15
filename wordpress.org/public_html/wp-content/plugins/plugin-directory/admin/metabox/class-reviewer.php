<?php
/**
 * The Plugin Reviewer metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */

namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * The Plugin Reviewer metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Reviewer {

	/**
	 * Displays the Assigned Reviewer metabox.
	 */
	public static function display() {
		$post = get_post();

		// Fetch the assigned reviewer.
		$reviewer_id     = (int) ( $post->assigned_reviewer ?? 0 );
		$reviewer_time   = (int) ( $post->assigned_reviewer_time ?? 0 );
		$reviewer        = $reviewer_id ? get_user_by( 'id', $reviewer_id ) : false;
		$is_current_user = $reviewer_id === get_current_user_id();

		// Display the reviewer assignee dropdown.
		wp_dropdown_users( [
			'name'              => 'assigned_reviewer',
			'selected'          => $reviewer_id,
			'show_option_none'  => '— No one —',
			'option_none_value' => 0,
			'role__in'          => [ 'plugin_admin', 'plugin_reviewer' ],
		] );
		wp_nonce_field( 'set_reviewer', '_set_reviewer_nonce' );
		echo '<span class="spinner"></span>';
		echo '<style>#assigned_reviewer { width: 80%; }</style>';

		// Display the "Assign to me" button.
		if ( ! $reviewer_id ) {
			?>
			<p style="text-align: right">
				<button type="button" class="button-primary" id="assign-to-me"><?php esc_html_e( 'Assign to me', 'wporg-plugins' ); ?></button>
			</p>
			<?php
		} else {
			printf( '<p>%s ago</p>', human_time_diff( $reviewer_time ) );
		}

		?>
		<script>
			jQuery( function( $ ) {
				$( '#assign-to-me' ).click( function( e ) {
					e.preventDefault();
					$(this).prop( 'disabled', true );

					$('#assigned_reviewer').val( userSettings.uid ).change();
				} );

				// XHR save it.
				$('#assigned_reviewer').change( function( e ) {
					var $this = $(this),
						$spinner = $(this).parent().find('.spinner'),
						nonce = $('#_set_reviewer_nonce').val();

					e.preventDefault();
					$spinner.addClass('is-active');

					$.post(
						ajaxurl,
						{
							action: 'plugin-set-reviewer',
							_set_reviewer_nonce: nonce,
							post_id: new URL(document.location).searchParams.get('post'),
							assigned_reviewer: $this.val(),
						},
						function() {
							$spinner.removeClass('is-active');
						}
					);
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Set the Reviewer via XHR.
	 */
	public static function xhr_set_reviewer() {
		check_admin_referer( 'set_reviewer', '_set_reviewer_nonce' );

		$post_id     = absint( $_POST['post_id'] ?? 0 );
		$reviewer_id = absint( $_POST['assigned_reviewer'] ?? 0 );

		if ( ! $post_id || ! current_user_can( 'plugin_admin_edit', $post_id ) ) {
			return;
		}

		self::set_reviewer( $post_id, $reviewer_id );

		$assigned_reviewer = get_post_meta( $post_id, 'assigned_reviewer', true );

		die( json_encode( compact( 'assigned_reviewer' ) ) );
	}

	/**
	 * Set the reviewer on save.
	 *
	 * @param int|WP_Post $post Post ID or post object.
	 */
	public static function save_post( $post_id ) {
		if (
			! current_user_can( 'plugin_admin_edit', $post_id ) ||
			! isset( $_POST['_set_reviewer_nonce'] ) ||
			! wp_verify_nonce( $_POST['_set_reviewer_nonce'], 'set_reviewer' )
		) {
			return;
		}

		$reviewer_id = absint( $_POST['assigned_reviewer'] ?? 0 );

		self::set_reviewer( $post_id, $reviewer_id );
	}

	/**
	 * Set the reviewer for a post.
	 *
	 * @param int|WP_Post $post     Post object or ID.
	 * @param int|WP_User $reviewer Reviewer User object or ID.
	 * @param bool        $log_it   Whether to log the change. Optional.
	 * @return bool
	 */
	public static function set_reviewer( $post, $reviewer, $log_it = true ) {
		$post             = get_post( $post );
		$current_reviewer = (int) get_post_meta( $post->ID, 'assigned_reviewer', true );
		$reviewer         = is_object( $reviewer ) ? $reviewer : get_user_by( 'id', $reviewer );
		$reviewer_id      = $reviewer->ID ?? 0;

		if ( $current_reviewer == $reviewer_id || ! $post ) {
			return false;
		}

		if ( ! $reviewer ) {
			delete_post_meta( $post->ID, 'assigned_reviewer' );
			delete_post_meta( $post->ID, 'assigned_reviewer_time' );
		} else {
			update_post_meta( $post->ID, 'assigned_reviewer', $reviewer_id );
			update_post_meta( $post->ID, 'assigned_reviewer_time', time() );
		}

		// Audit logging.
		if ( $log_it ) {
			$message = 'Unassigned.';
			if ( $reviewer ) {
				$message = sprintf(
					'Assigned to <a href="%s">%s</a>.',
					esc_url( 'https://profiles.wordpress.org/' . $reviewer->user_nicename . '/' ),
					$reviewer->display_name ?: $reviewer->user_login
				);
			}

			Tools::audit_log( $message, $post );
		}

		return true;
	}
}
