<div class="error">
	<ul>
		<?php foreach ( $inactive_required_modules as $module ) : ?>
			<li>Please activate Jetpack's <?php echo esc_html( $module ); ?> module.</li>
		<?php endforeach; ?>
	</ul>
</div>
