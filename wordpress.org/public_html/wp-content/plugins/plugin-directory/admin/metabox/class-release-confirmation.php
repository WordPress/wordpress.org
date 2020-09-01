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
	static function save_post( $post ) {
		$post = get_post( $post );

		if (
			$post &&
			isset( $_REQUEST['release_confirmation'] ) &&
			is_numeric( $_REQUEST['release_confirmation'] ) &&
			current_user_can( 'plugin_admin_edit', $post->ID )
		) {
			$current = (int) $post->release_confirmation;
			$new     = (int) $_REQUEST['release_confirmation'];

			if ( $new == $current ) {
				return;
			}

			if ( 0 == $new ) {
				// Disable
				Tools::audit_log( 'Plugin release confirmation disabled.', $post );
				update_post_meta( $post->ID, 'release_confirmation', $new );

			} else {
				// Enable, re-use the API for this one.
				$request = new WP_REST_Request(
					'POST',
					'/plugins/v1/plugin/' . $post->post_name . '/release-confirmation'
				);
				$request->set_param(
					'confirmations_required',
					$new
				);

				// For some reason, this is causing a 502 bad gateway - upstream sent too big header
				// See if it works in production.
				rest_do_request( $request );
			}
		}
	}
}
