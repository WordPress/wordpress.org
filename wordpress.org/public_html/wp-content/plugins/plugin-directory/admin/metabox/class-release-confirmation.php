<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

use WP_REST_Request;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Shortcodes\Release_Confirmation as Release_Confirmation_Shortcode;

/**
 * Plugin Release Confirmation controls.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Release_Confirmation {
	static function display() {
		global $post;

		echo '<p><select name="release_confirmation" onchange="jQuery(this).next().removeClass(\'hidden\');">';
		foreach ( [
			0 => 'No approval required',
			1 => 'One confirmation required',
			2 => 'Two confirmations required'
		] as $num => $text ) {
			printf(
				'<option value="%s" %s>%s</option>',
				$num,
				selected( $post->release_confirmation, $num, false ),
				$text
			);
		}
		echo "</select><span class='hidden'>&nbsp;Don't forget to save the changes!</span></p>";

		if ( $post->release_confirmation ) {
			Release_Confirmation_Shortcode::single_plugin_row( $post, $include_header = false );
		}
	}

	// Save the selection.
	static function save_post( $post_id ) {
		if (
			isset( $_REQUEST['release_confirmation'] ) &&
			is_numeric( $_REQUEST['release_confirmation'] ) &&
			current_user_can( 'plugin_admin_edit', $post_id )
		) {
			if ( 0 == $_REQUEST['release_confirmation'] ) {
				// Disable
				Tools::audit_log( 'Plugin release approval disabled.', $post_id );
				update_post_meta( $post_id, 'release_confirmation', 0 );

			} else {
				// Enable, re-use the API for this one.
				$request = new WP_REST_Request(
					'POST',
					'/plugins/v1/plugin/' . get_post( $post_id )->post_name . '/release-confirmation'
				);
				$request->set_param(
					'confirmations_required',
					(int) $_REQUEST['release_confirmation']
				);

				// For some reason, this is causing a 502 bad gateway - upstream sent too big header
				// See if it works in production.
				rest_do_request( $request );
			}
		}
	}
}
