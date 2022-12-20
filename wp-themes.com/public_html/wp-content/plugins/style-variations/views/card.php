<?php
/**
 * Global Style template: Front card preview.
 */

namespace WordPressdotorg\Theme_Preview\Style_Variations;

// We need global settings to get palette
$global_settings = wp_get_global_settings();
$palette         = $global_settings['color']['palette'];

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
<div class="wporg-global-style-container">
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

	// Remove the already used colors
	var colors = palette.theme.filter( entry => ! [ h1TextColor, bodyColor ].includes( entry.color.toLowerCase() ) );
	
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
