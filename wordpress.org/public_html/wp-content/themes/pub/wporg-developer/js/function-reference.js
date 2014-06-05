/**
 * function-reference.js
 *
 * Handles all interactivity on the single function page
 */
( function( $ ) {
	var $sourceContent, $sourceCodeContainer, $sourceCodeTable, $showCompleteSource;

	function showCompleteSource( e ) {
		e.preventDefault();

		var heightGoal = $sourceCodeTable.height() + 17;

		$sourceCodeContainer.animate( { height: heightGoal + 'px' } );

		$showCompleteSource.hide();

	}

	function onLoad() {

		// We only expect one source-content per document
		$sourceContent = $( '.source-content' );
		$sourceCodeContainer = $( '.source-code-container' );

		SyntaxHighlighter.highlight();

		$sourceCodeTable = $sourceContent.find( 'table' );

		if ( 186 < $sourceCodeTable.height() ) {

			// Do this with javascript so javascript-less can enjoy the total sourcecode
			// 1em (margin) + 10 * 17px. Lines are 1.1em which rounds to 17px: calc( 1em + 17px * 10 ).
			$sourceCodeContainer.css( { height: '186px' } );

			$showCompleteSource = $( '.show-complete-source' );

			$showCompleteSource.show();
			$showCompleteSource.on( 'click', showCompleteSource );
		}
	}

	$( onLoad );
} )( jQuery );
