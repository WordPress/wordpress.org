<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

use WordPressdotorg\Plugin_Directory\Template;

/**
 * Plugin Release Confirmation controls.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Release_Confirmation {
	static function display() {
		global $post;

		echo 'Release Confirmation: <strong>' . ( $post->release_confirmation_enabled ? 'Enabled' : 'Disabled' ) . '</strong>';

		var_dump( get_post_meta( $post->ID, 'confirmed_releases', true ) );

	}
}
