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

		update_post_meta( $post_id, 'assigned_reviewer', $reviewer_id );
		update_post_meta( $post_id, 'assigned_reviewer_time', time() );

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

		update_post_meta( $post_id, 'assigned_reviewer', $reviewer_id );
		update_post_meta( $post_id, 'assigned_reviewer_time', time() );
	}
}
