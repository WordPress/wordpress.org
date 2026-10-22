<?php
/**
 * Global Style template: Front card preview.
 */

namespace WordPressdotorg\Theme_Preview\Style_Variations;

// We need global settings to get palette
$global_settings = wp_get_global_settings();
$palette         = $global_settings['color']['palette'];

$global_styles = wp_get_global_styles();
$has_set_background = isset( $global_styles['color'], $global_styles['color']['background'] );

$classes = 'wporg-global-style-container';

// If the background is not set via theme.json's color.background setting, the background might be set on
// a wrapper element in the template. This will try to find the first child element of the template with
// a background color, and pull out those classes to use on the global style container.
// For example, it should add `has-first-background-color`, or  `has-custom-main-gradiant-gradient-background`.
if ( ! $has_set_background ) {
	$template_html = get_the_block_template_html();

	$tags = new \WP_HTML_Tag_Processor( $template_html );
	if ( $tags->next_tag( [ 'class_name' => 'has-background' ] ) ) {
		$classes .= ' ' . $tags->get_attribute( 'class' );
	}
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<?php wp_head(); ?>

	<style>
		.wporg-global-style-container {
			height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.wporg-global-style-container > div {
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.wporg-global-style-container > div > div:first-child {
			margin-right: 90px;
		}

		.wporg-global-style-container > div > div:last-child {
			margin-left: 90px;
		}

		.wporg-global-style-container h1 {
			margin: 0;
			font-size: 300px;
		}

		#wporg-global-style-circles > div {
			display: block;
			height: 140px;
			width: 140px;
			border-radius: 50%;
			margin: 4vw 0;
		}

		#wporg-global-style-circles > div:first-child {
			margin-bottom: 60px;
		}

		#wporg-global-style-circles > div:last-child {
			margin-top: 60px;
		}

	</style>

</head>

<body <?php body_class(); ?>>

<div class="<?php echo esc_attr( $classes ); ?>">
	<div>
		<div><h1 id="wporg-global-style-heading">Aa</h1></div>
		<div id="wporg-global-style-circles"></div>
	</div>
</div>
<script>
	// Thanks: https://stackoverflow.com/questions/1740700/how-to-get-hex-color-value-rather-than-rgb-value
	var rgba2hex = ( rgba ) => `#${rgba.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+\.{0,1}\d*))?\)$/).slice(1).map( ( n, i ) => ( i === 3 ? Math.round( parseFloat( n ) * 255 ) : parseFloat( n ) ).toString( 16 ).padStart( 2, '0' ).replace( 'NaN', '' ) ).join( '' )}`
	
	/**
	 * We need to display 2 circles with ideally the primary and secondary color provided 
	 * they are not used for the background or h1 text color.
	 * 
	 * There is currently no easy way to get these two colors from the theme.json.
	 * 
	 * For now, we let the styles get applied by the page, grab the 2 colors from 
	 * elements (body & h1), remove them from the palette array and display the next 2 colors.
	 * 
	 * For added difficulty, sometimes they don't exist and nothing shoes up. 
	 * We are okay with that for now.
	 * 
	 */
	var palette = <?php echo json_encode( $palette ); ?>;

	var h1Element = document.getElementById( 'wporg-global-style-heading' );
	var circleContainer = document.getElementById( 'wporg-global-style-circles' );

	// Get these hex colors so we can exclude them when looping through the palette
	var h1TextColor = rgba2hex( window.getComputedStyle( h1Element ).color ).toLowerCase();
	var bodyColor = rgba2hex( window.getComputedStyle( document.body ).backgroundColor ).toLowerCase();

	// If no background is set on this element, it's still a valid value (#0000).
	var divColor = rgba2hex( window.getComputedStyle( document.querySelector( '.wporg-global-style-container' ) ).backgroundColor ).toLowerCase();

	// Remove the already used colors
	var colors = palette.theme.filter( entry => ! [ h1TextColor, bodyColor, divColor ].includes( entry.color.toLowerCase() ) );
	
	// Create circles for the first 2 colors.
	colors.slice( 0, 2 ).forEach( entry => {
		var circle = document.createElement( 'div' );
		circle.style.backgroundColor = entry.color;
		circleContainer.appendChild( circle );
	} );
</script>
<?php wp_footer(); ?>
</body>
</html>
