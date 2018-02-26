<?php

/**
 * @var int    $agreement_id
 * @var string $agreement_url
 * @var int    $mes_id
 */

?>

<?php if ( $mes_id ) : ?>

	<p class="description"><?php _e( 'This sponsor has already signed a global sponsorship agreement.', 'wordcamporg' ); ?></p>

	<?php if ( $agreement_url ) : ?>
		<p id="sponsor-agreement-view-container">
			<a id="sponsor-agreement-view" class="button secondary" href="<?php echo esc_url( $agreement_url ); ?>" target="sponsor-agreement">
				<?php esc_html_e( 'View Agreement', 'wordcamporg' ); ?>
			</a>
		</p>
	<?php endif; ?>

<?php else : ?>

	<p id="sponsor-agreement-description-container" class="description hidden">
		<?php

		printf(
			wp_kses_data( __(
				'<strong>Instructions:</strong> You can generate an agreement for this sponsor <a href="%s">here</a>. Upload a PDF or image file of the signed, dated sponsor agreement.', 'wordcamporg'
			) ),
			esc_url( add_query_arg( array( 'page' => 'wcdocs' ), admin_url( 'admin.php' ) ) )
		);

		?>
	</p>

	<p id="sponsor-agreement-upload-container" class="hidden">
		<a id="sponsor-agreement-upload" class="button secondary" href="#">
			<?php esc_html_e( 'Attach Signed Agreement', 'wordcamporg' ); ?>
		</a>
	</p>

	<p id="sponsor-agreement-view-container" class="hidden">
		<a id="sponsor-agreement-view"
		   class="button secondary <?php if ( ! $agreement_url ) { echo ' hidden'; } ?>"
		   href="<?php echo esc_url( $agreement_url ); ?>"
		   target="sponsor-agreement">
			<?php esc_html_e( 'View Agreement', 'wordcamporg' ); ?>
		</a>
	</p>

	<p id="sponsor-agreement-remove-container" class="hidden">
		<a id="sponsor-agreement-remove" href="#">
			<?php esc_html_e( 'Remove Agreement', 'wordcamporg' ); ?>
		</a>
	</p>

	<input id="sponsor-agreement-id" name="_wcpt_sponsor_agreement" type="hidden" value="<?php echo esc_attr( $agreement_id ); ?>" />

<?php endif;
