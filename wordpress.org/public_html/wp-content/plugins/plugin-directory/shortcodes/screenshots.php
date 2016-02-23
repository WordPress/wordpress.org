<?php
add_shortcode( 'wporg-plugins-screenshots', 'wporg_plugins_screenshots' );

function wporg_plugins_screenshots() {
	$plugin = get_post();

	// All indexed from 1
	$screenshot_descriptions = get_post_meta( $plugin->ID, 'screenshots', true );
	$assets_screenshots = get_post_meta( $plugin->ID, 'assets_screenshots', true );

	$output = '';
	foreach ( $screenshot_descriptions as $index => $description ) {
		// Find the image that corresponds with the text.
		// The image numbers are stored within the 'resolution' key.
		$found = false;
		foreach ( $assets_screenshots as $image ) {
			if ( $index == $image['resolution'] ) {
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			continue;
		}

		if ( ! empty( $image['location'] ) && 'plugin' == $image['location'] ) {
			// Screenshot is within the plugin folder
			$url = sprintf(
				'https://s.w.org/plugins/%s/%s?rev=%s',
				$plugin->post_name,
				$image['filename'],
				$image['revision']
			);
		} else {
			// In the /assets/ folder
			$url = sprintf(
				'https://ps.w.org/%s/assets/%s?rev=%s',
				$plugin->post_name,
				$image['filename'],
				$image['revision']
			);
		}

		$output .= sprintf(
			'<li>
				<a href="%1$s" rel="nofollow">
					<img class="screenshot" src="%1$s">
				</a>
				<p>%2$s</p>
			</li>',
			$url,
			$description
		);
	}

	return '<ol class="screenshots">' . $output . '</ol>';

}