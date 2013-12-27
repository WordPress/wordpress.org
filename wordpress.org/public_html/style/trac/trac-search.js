(function($){
	var base = window.location.hostname,
		ajaxurl = 'https://api.wordpress.org/core/trac-search/1.0/';

	$('div#content.ticket input#field-summary').change( function() {
		var keywords,
			related,
			el = $(this);

		var keywords = el.val();
		if ( ! keywords.length ) {
			return;
		}

		related = $('.related-tickets');

		$.ajax({
			url: ajaxurl + '?trac=' + window.location.hostname + '&keywords=' + keywords,
			success: function( data ) {
				var list;
				if ( data && data.results ) {
					if ( ! related.length ) {
						related = $('<div />')
							.addClass('related-tickets')
							.css({ 'margin': '8px 4px 10px', 'white-space': 'normal' })
							.insertAfter( el );
					}
					related.html('Possibly related tickets?');
					list = $('<ul />').appendTo( related );

					list.css({
						margin: '4px',
						'list-style': 'none',
						margin: '4px 0',
						padding: 0
					});

					jQuery.each( data.results, function(index, result ) {
						var li = $( '<li />' ).appendTo( list );
						$( '<a />' )
							.attr( 'href', result.url )
							.addClass( result.status )
							.text( '#' + result.id )
							.appendTo( li );
						$( '<span />' )
							.text( ': ' + result.summary )
							.appendTo( li );
						li.append(' (' + ( result.resolution ? result.resolution : 'open' ) + ')' );
					});
				}
			},
			dataType: 'json'
		});
	})
})(jQuery);

