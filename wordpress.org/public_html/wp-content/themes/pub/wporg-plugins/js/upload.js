( function( $ ) {
	$('dialog.slug-change').on('submit', function( e ) {
		e.preventDefault();
		var $form = $(e.target),
			$errorNotice = $form.find('.notice-error p'),
			pluginId = $form.find('input[name="id"]').val(),
			slug = $form.find('input[name="post_name"]').val(),
			restEndpoint = 'plugins/v1/upload/' + pluginId + '/slug';

		$form.find('input').prop('disabled', true);

		wp.apiRequest( {
			path: restEndpoint,
			type: 'PATCH',
			data: {
				post_name: slug
			}
		} )
		.done( function() {
			window.location.reload();
		} )
		.fail( function( response, statusText ) {
			var errorHtml = response?.responseJSON?.message || statusText;

			$errorNotice.html( errorHtml ).parent().removeClass('hidden');

			$form.find('input').prop('disabled', false);
		} );
	} );

	// Show the filename on the button when a file is selected.
	$( 'input.plugin-file' )
		.on( 'change', function( e ) {
			var $span = $(this).parent().find('span'),
				fileName = e.target.value.split( '\\' ).pop();

			if ( ! $span.data( 'defaultText' ) ) {
				$span.data( 'defaultText', $span.text() );
			}

			$span.text( fileName || $span.data( 'defaultText' ) );
		} )
		.on( 'focus', function() { $(this).parent().addClass( 'focus' ); } )
		.on( 'blur', function() { $(this).parent().removeClass( 'focus' ); } );

	$( 'a.show-upload-additional').on( 'click', function( e ) {
		e.preventDefault();

		$(this).hide().parents('ul').find('.plugin-upload-form.hidden').removeClass( 'hidden' );
	} );

})( jQuery );
