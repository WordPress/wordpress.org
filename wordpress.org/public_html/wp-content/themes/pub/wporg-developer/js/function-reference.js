/**
 * function-reference.js
 *
 * Handles all interactivity on the single function page
 */
( function( $ ) {
	var $sourceContent, $sourceCodeContainer, $sourceCodeTable, $showCompleteSource;

	function showCompleteSource( e ) {
		e.preventDefault();

		var heightGoal = $sourceCodeTable.height() + 47; // takes into consideration potential x-scrollbar

		$sourceCodeContainer.animate( { height: heightGoal + 'px' } );

		$showCompleteSource.hide();

	}

	function onLoad() {

		// We only expect one source-content per document
		$sourceContent = $( '.source-content' );
		$sourceCodeContainer = $( '.source-code-container' );

		SyntaxHighlighter.highlight();

		$sourceCodeTable = $sourceContent.find( 'table' );

		if ( 188 < $sourceCodeTable.height() ) {

			// Do this with javascript so javascript-less can enjoy the total sourcecode
			// 1em (margin) + 20 * 17px. Lines are 1.1em which rounds to 17px: calc( 1em + 17px * 20 ).
			// Extra 10px added to partially show next line so it's clear there is more.
			$( '.source-code-container' ).css( { height: '196px' } );

			$showCompleteSource = $( '.show-complete-source' );

			$showCompleteSource.show();
			$showCompleteSource.on( 'click', showCompleteSource );
		}

	}

	$( onLoad );
} )( jQuery );
