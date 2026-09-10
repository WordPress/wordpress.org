<?php
/**
 * Shared translation set form fields, used by both the new and edit templates.
 *
 * @package GlotPress
 */

?>
<dl>
	<dt><label for="set[locale]"><?php esc_html_e( 'Locale', 'glotpress' ); ?></label></dt>
	<dd>
		<?php echo gp_locales_dropdown( 'set[locale]', $set->locale ); ?>
		<a href="#" id="copy"><?php esc_html_e( 'Use as name', 'glotpress' ); ?></a>
	</dd>

	<dt><label for="set[name]"><?php esc_html_e( 'Name', 'glotpress' ); ?></label></dt>
	<dd><input type="text" name="set[name]" value="<?php echo esc_html( $set->name ); ?>" id="set[name]"></dd>

	<!-- TODO: make slug edit WordPress style -->
	<dt><label for="set[slug]"><?php esc_html_e( 'Slug', 'glotpress' ); ?></label></dt>
	<dd><input type="text" name="set[slug]" value="<?php echo esc_html( $set->slug? $set->slug : 'default' ); ?>" id="set[slug]"></dd>

	<dt><label for="set[project_id]"><?php esc_html_e( 'Project (ID)', 'glotpress' ); ?></label></dt>
	<dd><input type="text" name="set[project_id]" value="<?php echo esc_attr( $set->project_id ); ?>" id="set[project_id]">
</dl>
<?php echo gp_js_focus_on( 'set[locale]' ) . "\n"; ?>
<script type="text/javascript">
	jQuery('#copy').click(function() {
		var text = jQuery('#set\\[locale\\] option:selected').html().replace(/^\S+\s+\S+\s+/, '').replace(/&mdash|—/, '');
		jQuery('#set\\[name\\]').val(text);
		return false;
	});
</script>
