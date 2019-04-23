<?php

defined( 'WPINC' ) || die();

/** @var string $id */
/** @var string $value */

?>

<div class="camptix-media">
	<div class="camptix-invoice-logo-preview-wrapper" data-imagewrapper>
		<?php
		if ( ! empty( $value ) ) {
			$attachment = wp_get_attachment_image_src( $value, 'full' );
			printf( '<img src="%s" style="max-width:250px;max-height:200px;">', esc_url( $attachment[0] ) );
		}
		?>
	</div>

	<input data-set type="button" class="button button-secondary" value="<?php echo esc_attr__( 'Pick a logo', 'wordcamporg' ); ?>" />
	<input
		data-unset
		type="button"
		class="button button-secondary"
		value="<?php echo esc_attr__( 'Remove logo', 'wordcamporg' ); ?>"
		<?php
		if ( empty( $value ) ) {
			echo 'style="display:none;"';
		}
		?>
	/>
	<input type="hidden" name=camptix_options[<?php echo esc_attr( $id ); ?>] data-field="image_attachment" value="<?php echo esc_attr( $value ); ?>">
	<p class="description"><?php echo esc_html__( 'Expected image size: 250px width, 200px height', 'wordcamporg' ); ?></p>
</div>
