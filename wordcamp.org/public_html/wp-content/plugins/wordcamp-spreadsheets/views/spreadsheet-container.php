<div id="wcss_spreadsheet_container"></div>

<?php if ( is_admin() ) : ?>
	<input id="wcss_spreadsheet_data" name="wcss_spreadsheet_data" type="hidden" value="<?php echo esc_attr( json_encode( $spreadsheet_data ) ); ?>" />
<?php endif; ?>

<script type="text/javascript">
	var wcssSpreadSheetData = <?php echo json_encode( $spreadsheet_data ); ?>;	<?php // todo make sure json_encode() is enough sanitization here and above ?>
</script>
