<?php
/**
 * Shared project form fields, used by both the new-project and edit-project templates.
 *
 * @package GlotPress
 */

?>
<dl>
	<dt><label for="project[name]"><?php esc_html_e( 'Name', 'glotpress' ); ?></label></dt>
	<dd><input type="text" name="project[name]" value="<?php echo esc_html( $project->name ); ?>" id="project[name]"></dd>

	<!-- TODO: make slug edit WordPress style -->
	<dt><label for="project[slug]"><?php esc_html_e( 'Slug', 'glotpress' ); ?></label></dt>
	<dd>
		<input type="text" name="project[slug]" value="<?php echo esc_html( $project->slug ); ?>" id="project[slug]">
		<small><?php esc_html_e( 'If you leave the slug empty, it will be derived from the name.', 'glotpress' ); ?></small>
	</dd>

	<dt><label for="project[description]"><?php esc_html_e( 'Description', 'glotpress' ); ?></label> <span class="ternary"><?php esc_html_e( 'can include HTML', 'glotpress' ); ?></span></dt>
	<dd><textarea name="project[description]" rows="4" cols="40" id="project[description]"><?php echo esc_html( $project->description ); ?></textarea></dd>

	<dt><label for="project[source_url_template]"><?php esc_html_e( 'Source file URL', 'glotpress' ); ?></label></dt>
	<dd>
		<input type="text" value="<?php echo esc_html( $project->source_url_template ); ?>" name="project[source_url_template]" id="project[source_url_template]" style="width: 30em;" />
		<span class="ternary"><?php printf(
			/* translators: 1: %file%, 2: %line%, 3: https://trac.example.org/browser/%file%#L%line% */
			esc_html__( 'Public URL to a source file in the project. You can use %1$s and %2$s. Ex. %3$s', 'glotpress' ),
			'<code>%file%</code>',
			'<code>%line%</code>',
			'<code>https://trac.example.org/browser/%file%#L%line%</code>'
		); ?></span>
	</dd>

	<dt><label for="project[parent_project_id]"><?php esc_html_e( 'Parent Project (ID)', 'glotpress' ); ?></label></dt>
	<dd><input type="text" name="project[parent_project_id]" value="<?php echo esc_attr( $project->parent_project_id ); ?>" id="project[parent_project_id]">

	<dt><label for="project[active]"><?php esc_html_e( 'Active', 'glotpress' ); ?></label> <input type="checkbox" id="project[active]" name="project[active]" <?php gp_checked( $project->active ); ?> /></dt>
</dl>

<?php echo gp_js_focus_on( 'project[name]' ); ?>
