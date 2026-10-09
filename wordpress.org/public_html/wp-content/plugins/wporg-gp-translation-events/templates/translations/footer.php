<?php
namespace Wporg\TranslationEvents\Templates\Translations;

use Wporg\TranslationEvents\Templates;
?>

</div>
<div class="clear"></div>
<?php
$set_scripts = array();
foreach ( $editor_options as $translation_set_id => $options ) {
	$set_scripts[] = sprintf(
		<<<'JS'
	$('#translations_%1$s' ).click( set_translation_table_%1$s );
	$('#translations_%1$s' ).mousemove( function() {
		if ( ! $( '#translations', this ).length ) {
			set_translation_table_%1$s();
		}
	});
	function set_translation_table_%1$s() {
		if ( current_event_translations_table === %1$s ) {
			return;
		}
		current_event_translations_table = %1$s;
		$gp_editor_options = %2$s;
		$( '#translations' ).attr( 'id', null );
		$( '#translations_%1$s table' ).attr( 'id', 'translations' );
		$gp.editor.table = $( '#translations' );
		if ( typeof hooks_installed[%1$s] === 'undefined' ) {
			$gp.editor.install_hooks();
			hooks_installed[%1$s] = true;
		}
		$gp_translation_helpers_editor = $gp_translation_helpers_editor_%1$s;
		}
	JS,
		absint( $translation_set_id ),
		wp_json_encode( $options )
	);
}

wp_print_inline_script_tag(
	sprintf(
		<<<'JS'
jQuery( function($) {
	var hooks_installed = {};
	var current_event_translations_table = false;
%s
} );
JS,
		implode( "\n", $set_scripts )
	)
);
gp_enqueue_script( 'wporg-translate-editor' );
Templates::footer();
