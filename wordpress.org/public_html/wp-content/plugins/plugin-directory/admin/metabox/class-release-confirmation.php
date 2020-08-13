<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

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

		$confirmations_required = $post->release_confirmation;
		$confirmed_releases     = get_post_meta( $post->ID, 'confirmed_releases', true ) ?: [];

		echo '<p><select name="release_confirmation" onchange="jQuery(this).next().removeClass(\'hidden\');">';
		foreach ( [
			0 => 'No approval required',
			1 => 'One confirmation required',
			2 => 'Two confirmations required'
		] as $num => $text ) {
			printf(
				'<option value="%s" %s>%s</option>',
				$num,
				selected( $confirmations_required, $num, false ),
				$text
			);
		}
		echo "</select><span class='hidden'>&nbsp;Don't forget to save the changes!</span></p>";

		Release_Confirmation_Shortcode::single_plugin_row( $post, $include_header = false );

	}

	// Save the selection.
	static function save_post( $post_id ) {
		if ( isset( $_REQUEST['release_confirmation'] ) && is_numeric( $_REQUEST['release_confirmation'] ) ) {
			if ( current_user_can( 'plugin_admin_edit', $post_id ) ) {
				if ( update_post_meta( $post_id, 'release_confirmation', (int) $_REQUEST['release_confirmation'] ) ) {
					if ( ! (int) $_REQUEST['release_confirmation'] ) {
						Tools::audit_log( 'Plugin release approval disabled.', $post_id );
					}
					Tools::audit_log( sprintf(
						'Plugin release approval now requires %s confirmations.',
						(int) $_REQUEST['release_confirmation']
					), $post_id );
				}
			}
		}
	}
}
