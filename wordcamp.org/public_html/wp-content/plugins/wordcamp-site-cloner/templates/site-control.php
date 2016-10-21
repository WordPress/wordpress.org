<?php

/**
 * Top level template for the output of the Site Cloner Customizer Control
 */

namespace WordCamp\Site_Cloner;
defined( 'WPINC' ) or die();

?>

<div id="wcsc-cloner">
	<h3>
		<?php esc_html_e( 'WordCamp Sites', 'wordcamporg' ); ?>
		<span id="wcsc-sites-count" class="title-count wcsc-sites-count"></span>
	</h3>

	<div class="filters"></div>

	<div class="wcsc-search">
		<ul id="wcsc-results"></ul>
	</div>
</div>
