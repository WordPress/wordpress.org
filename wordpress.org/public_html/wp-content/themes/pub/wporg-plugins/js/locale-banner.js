/* global wporgLocaleBanner */
( function( $ ) {

	$.ajax({
		type: 'POST',
		url : wporgLocaleBanner.apiURL,
		dataType : 'json',
		data: {
			'plugin_slug' : wporgLocaleBanner.currentPlugin
		},
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

