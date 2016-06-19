/* global wporgLocaleBanner */
( function( $ ) {

	$.ajax({
		url : wporgLocaleBanner.apiURL,
		dataType : 'json',
		success : function( response ) {
			if ( ! response.suggest_string ) {
				return;
			}

			var $banner = $( '<div />', {
				'class': 'locale-banner',
				'html': response.suggest_string
			} );

			$( '.plugin-header' ).after( $banner );
		}
	} );

} )( window.jQuery );

