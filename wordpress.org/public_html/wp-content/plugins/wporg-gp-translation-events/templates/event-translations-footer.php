<?php
namespace Wporg\TranslationEvents\Templates;

use Wporg\TranslationEvents\Templates;
?>

</div>
<div class="clear"></div>
<script type="text/javascript">
jQuery( function($) {
	var hooks_installed = {};
	var current_event_translations_table = false;
<?php
foreach ( $editor_options as $translation_set_id => $options ) {
	?>
	$('#translations_<?php echo esc_html( $translation_set_id ); ?>' ).click( set_translation_table_<?php echo esc_html( $translation_set_id ); ?> );
	$('#translations_<?php echo esc_html( $translation_set_id ); ?>' ).mousemove( function() {
		if ( ! $( '#translations', this ).length ) {
			set_translation_table_<?php echo esc_html( $translation_set_id ); ?>();
		}
	});
	function set_translation_table_<?php echo esc_html( $translation_set_id ); ?>() {
		if ( current_event_translations_table === <?php echo esc_html( $translation_set_id ); ?> ) {
			return;
		}
		current_event_translations_table = <?php echo esc_html( $translation_set_id ); ?>;
		$gp_editor_options = <?php echo wp_json_encode( $options ); ?>;
		$( '#translations' ).attr( 'id', null );
		$( '#translations_<?php echo esc_html( $translation_set_id ); ?> table' ).attr( 'id', 'translations' );
		$gp.editor.table = $( '#translations' );
		if ( typeof hooks_installed[<?php echo esc_html( $translation_set_id ); ?>] === 'undefined' ) {
			$gp.editor.install_hooks();
			hooks_installed[<?php echo esc_html( $translation_set_id ); ?>] = true;
		}
		$gp_translation_helpers_editor = $gp_translation_helpers_editor_<?php echo esc_html( $translation_set_id ); ?>;
		}
<?php } ?>
} );
</script>
<?php
gp_enqueue_script( 'wporg-translate-editor' );
Templates::footer();
