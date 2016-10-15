/* global wporgLocaleBanner */
( function( $ ) {

	$.ajax( {
		type: 'POST',
		url: wporgLocaleBanner.apiURL,
		dataType: 'json',
		data: {
			'plugin_slug': wporgLocaleBanner.currentPlugin
		},
		success: function ( response ) {
			if ( ! response.suggest_string ) {
				return;
			}

			var $banner = $( '<div />', {
				'class': 'locale-banner',
				'html': response.suggest_string
			} );

			if ( $( 'body' ).hasClass( 'single-plugin' ) ) {
				$( '.plugin-header' ).after( $banner );
			} else {
				$( '.site-main' ).prepend( $banner );
			}
		}
	} );

} )( window.jQuery );

