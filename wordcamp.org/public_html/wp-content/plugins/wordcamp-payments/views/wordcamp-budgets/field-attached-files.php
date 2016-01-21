<?php defined( 'WPINC' ) or die();

?>

<input id="wcb_existing_files_to_attach" name="wcb_existing_files_to_attach" type="hidden" value="" />

<p>
	<a class="button wcb-insert-media" role="button">
		<?php _e( 'Add files', 'wordcamporg' ); ?>
	</a>
</p>

<h4>
	<?php _e( 'Attached files:', 'wordcamporg' ); ?>
</h4>

<ul class="wcb_files_list loading-content">
	<li>
		<span class="spinner is-active"></span>
	</li>
</ul>

<p class="wcb_no_files_uploaded <?php echo esc_attr( $files ? 'hidden' : 'active' ); ?>">
	<?php _e( "You haven't uploaded any files yet.", 'wordcamporg' ); ?>
</p>

<script type="text/html" id="tmpl-wcb-attached-file">
	<a href="{{ data.url }}">{{ data.filename }}</a>
</script>
