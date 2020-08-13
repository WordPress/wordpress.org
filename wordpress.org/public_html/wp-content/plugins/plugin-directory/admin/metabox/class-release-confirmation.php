<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

use WordPressdotorg\Plugin_Directory\Template;
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
		echo 'Release Confirmation: <strong>' . ( $confirmations_required ? 'Enabled' : 'Disabled' ) . '</strong>';

		Release_Confirmation_Shortcode::single_plugin_row( $post, $include_header = false );
	}
}
