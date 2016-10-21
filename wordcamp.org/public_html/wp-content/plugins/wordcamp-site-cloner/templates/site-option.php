<?php

/**
 * Template to display a single Site within the Site Cloner Control
 */

namespace WordCamp\Site_Cloner;
defined( 'WPINC' ) or die();

?>

<script id="tmpl-wcsc-site-option" type="text/html">
	<div class="wcsc-site-screenshot">
		<img src="{{ data.screenshot_url }}" alt="{{ data.name }}"/>
	</div>

	<h3 class="wcsc-site-name">
		{{ data.name }}
	</h3>

	<# if ( data.active ) { #>

		<span id="live-previewing-{{ data.site_id }}" class="wcsc-previewing-label">
			<?php _e( 'Viewing', 'wordcamporg' ); ?>
		</span>

	<# } else { #>

		<span id="live-preview-label-{{ data.site_id }}" class="wcsc-live-preview-label">
			<?php _e( 'Live Preview', 'wordcamporg' ); ?>
		</span>

	<# } #>
</script>
