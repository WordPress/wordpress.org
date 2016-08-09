<div class="notice notice-error notice-large">
	<ul>
		<?php foreach ( $inactive_required_modules as $module ) : ?>
			<li>
				<?php // translators: %s is the name of the jetpack module ?>
				<?php printf( __( "Please activate Jetpack's %s module.", 'wordcamporg' ), esc_html( $module ) ); ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
