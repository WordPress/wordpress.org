/**
 * Takes the update and delete buttons off the Modules page; the server refuses both.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	$( function () {
		$(
			'.delete-module-trigger, .update-module-trigger, .update-all-trigger'
		)
			.each( function () {
				// The Delete link next to a module's version comes after a "|" separator.
				const separator = this.previousSibling;

				if (
					separator &&
					window.Node.TEXT_NODE === separator.nodeType
				) {
					separator.nodeValue = separator.nodeValue.replace(
						/\|\s*$/,
						''
					);
				}
			} )
			.remove();
	} );
} )( jQuery );
