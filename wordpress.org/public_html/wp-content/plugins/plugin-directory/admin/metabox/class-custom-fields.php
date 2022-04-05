<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

/**
 * Displays the Custom Metadata we've stored for the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Custom_Fields {
	static function display() {
		$post   = get_post();
		$fields = array(
			'version'            => 'Latest Plugin Version',
			'stable_tag'         => 'Stable Tag',
			'tested'             => 'Tested With',
			'requires'           => 'Requires',
			'requires_php'       => 'Requires PHP',
			'donate_link'        => 'Donate URL',
			'header_plugin_uri'  => 'Plugin URI',
			'header_author'      => 'Plugin Author',
			'header_author_uri'  => 'Plugin Author URI',
			'header_textdomain'  => 'Plugin TextDomain',
			'header_description' => 'Plugin Description',
			'header_name'        => 'Plugin Name',
		);

		echo '<dl>';
		foreach ( $fields as $field => $text ) {
			if ( ! $value = get_post_meta( $post->ID, $field, true ) ) {
				continue;
			}
			printf( '<dt>%s</dt><dd>%s</dd>', esc_html( $text ), make_clickable( esc_html( $value ) ) );
		}
		echo '</dl>';
	}
}

