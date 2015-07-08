<?php defined( 'WPINC' ) or die(); ?>

<div id="wcsc-site-<?php echo esc_attr( $this->site_id ); ?>" class="wcscSite" data-preview-url="<?php echo esc_url( $preview_url ); ?>">
	<div class="wcsc-site-screenshot">
		<img src="<?php echo esc_url( $this->screenshot_url ); ?>" alt="<?php echo esc_attr( $this->site_name ); ?>" />
	</div>

	<h3 class="wcsc-site-name">
		<?php echo esc_html( $this->site_name ); ?>
	</h3>

	<span id="live-preview-label-<?php echo esc_attr( $this->site_id ); ?>" class="wcsc-live-preview-label">
		<?php _e( 'Live Preview', 'wordcamporg' ); ?>
	</span>
</div>
