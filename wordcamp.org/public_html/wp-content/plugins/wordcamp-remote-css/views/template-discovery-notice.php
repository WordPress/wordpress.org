<?php

namespace WordCamp\RemoteCSS;
defined( 'WPINC' ) or die();

/**
 * @var string $plugin_url
 * @var string $notice_text
 */

?>

<img
	src="<?php echo esc_url( $plugin_url ); ?>/images/github-mark.svg"
	alt="GitHub logo"
	style="position: relative; top: 3px; float: left; height: 2em; margin-right: 5px;"
/>

<?php echo wp_kses( $notice_text, wp_kses_allowed_html( 'data' ) ); ?>
