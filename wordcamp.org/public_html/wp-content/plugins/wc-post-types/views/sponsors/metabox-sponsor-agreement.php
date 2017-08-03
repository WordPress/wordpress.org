<?php
/* @var WP_Post $agreement */
?>

<p id="sponsor-agreement-description-container" class="description hidden">
	<?php
	printf(
		wp_kses(
			__( '<strong>Instructions:</strong> Upload a PDF or image file of the signed, dated sponsor agreement. You can generate an agreement for this sponsor <a href="%s">here</a>.', 'wordcamporg' ),
			array(
				'a' => array( 'href' => true ),
				'strong' => true,
			)
		),
		esc_url( add_query_arg( array( 'page' => 'wcdocs' ), admin_url( 'admin.php' ) ) )
	);
	?>
</p>
<p id="sponsor-agreement-upload-container" class="hidden">
	<a id="sponsor-agreement-upload" class="button secondary" href="#"><?php esc_html_e( 'Upload Signed Agreement', 'wordcamporg' ); ?></a>
</p>

<p id="sponsor-agreement-view-container" class="hidden">
	<a id="sponsor-agreement-view" class="button secondary" href="<?php echo esc_url( $agreement_url ); ?>" target="sponsor-agreement"><?php esc_html_e( 'View Agreement', 'wordcamporg' ); ?></a>
</p>
<p id="sponsor-agreement-remove-container" class="hidden">
	<a id="sponsor-agreement-remove" href="#"><?php esc_html_e( 'Remove Agreement', 'wordcamporg' ); ?></a>
</p>

<input id="sponsor-agreement-id" name="_wcpt_sponsor_agreement" type="hidden" value="<?php echo esc_attr( $agreement_id ); ?>" />
