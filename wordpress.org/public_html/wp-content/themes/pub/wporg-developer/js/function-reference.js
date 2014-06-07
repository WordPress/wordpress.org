/**
 * function-reference.js
 *
 * Handles all interactivity on the single function page
 */
( function( $ ) {
	var $sourceContent, $sourceCodeContainer, $sourceCodeTable, $showCompleteSource, $lessCompleteSource, sourceCollapsedHeight;

	function toggleCompleteSource( e ) {
		e.preventDefault();

		if ( $showCompleteSource.is(':visible') ) {
			var heightGoal = $sourceCodeTable.height() + 45; // takes into consideration potential x-scrollbar
		} else {
			var heightGoal = sourceCollapsedHeight;
		}

		$sourceCodeContainer.animate( { height: heightGoal + 'px' } );

		$showCompleteSource.toggle();
		$lessCompleteSource.toggle();

	}

	function onLoad() {

		// We only expect one source-content per document
		$sourceContent = $( '.source-content' );
		$sourceCodeContainer = $( '.source-code-container' );

		SyntaxHighlighter.highlight();

		$sourceCodeTable = $sourceContent.find( 'table' );

		// 1em (margin) + 10 * 17px + 10. Lines are 1.1em which rounds to 17px: calc( 1em + 17px * 10 + 10 ).
		// Extra 10px added to partially show next line so it's clear there is more.
		sourceCollapsedHeight = 196;

		if ( ( sourceCollapsedHeight - 12 ) < $sourceCodeTable.height() ) {

			// Do this with javascript so javascript-less can enjoy the total sourcecode
			$( '.source-code-container' ).css( { height: sourceCollapsedHeight + 'px' } );

			$showCompleteSource = $( '.show-complete-source' );
			$lessCompleteSource = $( '.less-complete-source' );

			$( '.source-code-links span:first' ).show();
			$showCompleteSource.show();
			$showCompleteSource.on( 'click', toggleCompleteSource );
			$lessCompleteSource.on( 'click', toggleCompleteSource );
		}

	}

	$( onLoad );
} )( jQuery );
