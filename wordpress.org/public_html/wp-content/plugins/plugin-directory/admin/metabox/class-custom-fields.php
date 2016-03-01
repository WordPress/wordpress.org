<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

/**
 * Displays the Custom Metadata we've stored for the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Custom_Fields {
	static function display() {
		$post = get_post();
		$fields = array(
			'version' => 'Latest Plugin Version',
			'stable_tag' => 'Stable Tag',
			'tested' => 'Tested With',
			'requires' => 'Requires',
			'donate_link' => 'Donate URL',
			'header_plugin_uri' => 'Plugin URI',
			'header_author' => 'Plugin Author',
			'header_author_uri' => 'Plugin Author URI',
			'header_textdomain' => 'Plugin TextDomain',
			'header_description' => 'Plugin Description',
		);
		echo '<dl>';
		foreach ( $fields as $field => $text ) {
			if ( ! ($value = get_post_meta( $post->ID, $field, true ) ) ) {
				continue;
			}
			printf( '<dt>%s</dt><dd>%s</dd>', esc_html( $text ), make_clickable( esc_html( $value ) ) );
		}
		// Description is stored in the post_excerpt rather than meta
		printf( '<dt>%s</dt><dd>%s</dd>', esc_html( $fields['header_description'] ), get_the_excerpt() );
		echo '</dl>';
	}
}

