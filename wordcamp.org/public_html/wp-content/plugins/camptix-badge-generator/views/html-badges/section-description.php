<?php

namespace CampTix\Badge_Generator\HTML;
defined( 'WPINC' ) || die();

esc_html_e( 'Create personalized attendee badges with HTML and CSS. ', 'wordcamporg' );

?>

<div id="cbg-firefox-recommended" class="notice notice-warning notice-large hidden">
	<?php esc_html_e(
		'We strongly recommend using Firefox, because other browsers have inconsistent support for CSS page breaks.',
		'wordcamporg'
	); ?>
</div>
