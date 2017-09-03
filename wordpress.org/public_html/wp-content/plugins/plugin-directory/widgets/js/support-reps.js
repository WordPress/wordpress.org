(
	/**
	 * @param {Object} $
	 * @param {Object} wp
	 * @param {Object} pluginDir
	 * @param {String} pluginDir.restUrl
	 * @param {String} pluginDir.restNonce
	 * @param {String} pluginDir.pluginSlug
	 */
	function( $, wp, pluginDir ) {
		var logError = function( result ) {
			$( '.spinner' ).removeClass( 'spinner' );
			result = $.parseJSON( result.responseText );
			if ( typeof result.message !== 'undefined' ) {
				alert( result.message );
			}
		};

		$( '#support-rep-list' )
			.on( 'click', '.remove', function() {
				if ( ! window.confirm( pluginDir.removeSupportRepAYS ) ) {
					return;
				}

				var $row = $( this ).addClass( 'spinner' ).parents( 'li' ),
					url = pluginDir.restUrl + 'plugins/v1/plugin/' + pluginDir.pluginSlug + '/support-reps/' + $row.data( 'user' ) + '/?_wpnonce=' + pluginDir.restNonce;

				$.post( {
					url: url,
					method: 'DELETE',
				} ).success( function( result ) {
					if ( true === result ) {
						$row.slideUp( 500, function() {
							$row.remove()
						} );
					}
				} ).fail( logError );
			} )
			.on( 'submit', '#add-support-rep', function( event ) {
				event.preventDefault();

				var $row = $( this ).parents( 'li' ),
					$newUserInput = $row.find( 'input[name="support_rep"]' ),
					$button = $row.find( '.button-small' ).addClass( 'spinner' ),
					url = pluginDir.restUrl + 'plugins/v1/plugin/' + pluginDir.pluginSlug + '/support-reps/?_wpnonce=' + pluginDir.restNonce;

				$.post( {
					url: url,
					dataType: 'json',
					data: {
						support_rep: $newUserInput.val()
					}
				} ).done( function( result ) {
					if ( typeof result.name !== 'undefined' ) {
						$row.before( wp.template( 'new-support-rep' )( result ) );
						$newUserInput.val( '' );
						$button.removeClass( 'spinner' );
					}
				} ).fail( logError );
			} );
	} )( window.jQuery, window.wp, window.supportRepsWidget );
