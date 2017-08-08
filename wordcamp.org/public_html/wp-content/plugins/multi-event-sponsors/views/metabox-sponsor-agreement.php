<?php
/* @var int $agreement_id */
/* @var string $agreement_url */
?>

<?php
// Use the WC Post Types template if available.
$template_file = dirname( plugin_dir_path( __FILE__ ), 2 ) . '/wc-post-types/views/sponsors/metabox-sponsor-agreement.php';
if ( is_readable( $template_file ) ) :
	require_once $template_file;
else :
// WC Post Types template unavailable.
?>
<p id="sponsor-agreement-description-container" class="description hidden">
	<?php
	printf(
		wp_kses(
			__( '<strong>Instructions:</strong> You can generate an agreement for this sponsor <a href="%s">here</a>. Upload a PDF or image file of the signed, dated sponsor agreement.', 'wordcamporg' ),
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
	<a id="sponsor-agreement-upload" class="button secondary" href="#"><?php esc_html_e( 'Attach Signed Agreement', 'wordcamporg' ); ?></a>
</p>

<p id="sponsor-agreement-view-container" class="hidden">
	<a id="sponsor-agreement-view" class="button secondary" href="<?php echo esc_url( $agreement_url ); ?>" target="sponsor-agreement"><?php esc_html_e( 'View Agreement', 'wordcamporg' ); ?></a>
</p>
<p id="sponsor-agreement-remove-container" class="hidden">
	<a id="sponsor-agreement-remove" href="#"><?php esc_html_e( 'Remove Agreement', 'wordcamporg' ); ?></a>
</p>

<input id="sponsor-agreement-id" name="_wcpt_sponsor_agreement" type="hidden" value="<?php echo esc_attr( $agreement_id ); ?>" />
<?php endif; ?>
