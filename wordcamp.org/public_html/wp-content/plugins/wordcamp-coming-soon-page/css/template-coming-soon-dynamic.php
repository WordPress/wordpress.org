<!-- BEGIN wordcamp-coming-soon-page -->
<style type="text/css">
	body,
	input[type="text"],
	input[type="email"],
	textarea {
		color: <?php echo esc_attr( $colors['text'] ); ?>;
	}

	label span {
		color: <?php echo esc_attr( $colors['light-text'] ); ?>;
	}

	.wccsp-header {
		<?php if ( $background_url ) : ?>
			background: url('<?php echo esc_url( $background_url ); ?>') no-repeat center;
			background-size: cover;
		<?php else: ?>
			background: <?php echo esc_attr( $colors['main'] ); ?>;
			background: linear-gradient(
				45deg,
				<?php echo esc_attr( $colors['main'] ); ?>,
				<?php echo esc_attr( $colors['lighter'] ); ?>
			);
		<?php endif; ?>
	}

	button,
	input[type="submit"] {
		background: <?php echo esc_attr( $colors['main'] ); ?>;
		border-color: <?php echo esc_attr( $colors['darker'] ); ?>;
	}
</style>
<!-- END wordcamp-coming-soon-page -->
