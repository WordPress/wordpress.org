/* global wporgLocaleBanner */
( function( $ ) {

	if ( $( 'body' ).hasClass( 'single-plugin' ) && ! $( 'article' ).hasClass( 'status-publish' ) ) {
		return;
	}

	$.ajax( {
		type: 'GET',
		url: wporgLocaleBanner.apiURL,
		dataType: 'json',
		data: wporgLocaleBanner.currentPlugin ? { 'plugin_slug': wporgLocaleBanner.currentPlugin } : {},
		success: function ( response ) {
			if ( ! response.suggest_string ) {
				return;
			}

			var $banner = $( '<div />', {
				'class': 'locale-banner',
				'dir': 'auto',
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

