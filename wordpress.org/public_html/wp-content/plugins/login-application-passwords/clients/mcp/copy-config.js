document.getElementById( 'copy-mcp-config' ).addEventListener( 'click', function() {
	const textarea = document.getElementById( 'mcp-config' );
	navigator.clipboard.writeText( textarea.value ).then( function() {
		const btn = document.getElementById( 'copy-mcp-config' );
		const original = btn.textContent;
		btn.textContent = btn.dataset.copied;
		setTimeout( function() {
			btn.textContent = original;
		}, 2000 );
	} );
} );
