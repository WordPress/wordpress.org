( function( $ ) {
	$('dialog.slug-change').on('submit', function( e ) {
		e.preventDefault();
		var $form = $(e.target),
			$errorNotice = $form.find('.notice-error p'),
			restEndpoint = 'plugins/v1/upload/' + $form.find('input[name="id"]').val(),
			slug = $form.find('input[name="post_name"]').val();

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
		.fail( function( request, statusText ) {
			var errorHtml = request?.responseJSON?.message || statusText;

			$errorNotice.html( errorHtml ).parent().removeClass('hidden');

			$form.find('input').prop('disabled', false);
		} );
	} );
})( jQuery );
