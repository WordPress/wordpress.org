<?php
/**
 * Template part for displaying the FAQ section of a plugin.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

global $section, $section_slug, $section_content;
?>

<div id="faq" class="plugin-faqs">
	<h2><?php echo $section['title']; ?></h2>
	<?php echo $section_content; ?>
</div>
<script>
	( function( $ ) {
		$( '#faq' ).on( 'click', 'dt', function( event ) {
			var $question = $( event.target );

			if ( ! $question.is( '.open' ) ) {
				$question.siblings( '.open' ).toggleClass( 'open' ).attr( 'aria-expanded', false ).next( 'dd' ).slideToggle( 200 );
			}

			$question.toggleClass( 'open' ).attr( 'aria-expanded', function( index, attribute ) {
				return 'true' !== attribute;
			} ).next( 'dd' ).slideToggle( 200 );
		});
	} )( jQuery );
</script>
