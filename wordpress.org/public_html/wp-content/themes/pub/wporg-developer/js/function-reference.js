/**
 * function-reference.js
 *
 * Handles all interactivity on the single function page
 */
( function( $ ) {
	'use strict';

	var $sourceContent, $sourceCodeContainer, $sourceCodeTable, $showCompleteSource, $lessCompleteSource, sourceCollapsedHeight;

	var $usesList, $usedByList, $showMoreUses, $hideMoreUses, $showMoreUsedBy, $hideMoreUsedBy;

	function onLoad() {
		sourceCodeHighlightInit();

		toggleUsageListInit();
	}

	function sourceCodeHighlightInit() {

		// We require the SyntaxHighlighter javascript library
		if ( undefined === window.SyntaxHighlighter ) {
			return;
		}

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

	function toggleUsageListInit() {

		// We only expect one used_by and uses per document
		$usedByList = $( '.used-by' ).find( 'li' );
		$usesList   = $( '.uses' ).find( 'li' );

		if ( $usedByList.length > 5 ) {
			$usedByList = $usedByList.slice( 5 ).hide();

			$showMoreUsedBy = $( '.used-by .show-more' ).show().on( 'click', toggleMoreUsedBy );
			$hideMoreUsedBy = $( '.used-by .hide-more' ).on( 'click', toggleMoreUsedBy );
		}

		if ( $usesList.length > 5 ) {
			$usesList = $usesList.slice( 5 ).hide();

			$showMoreUses = $( '.uses .show-more' ).show().on( 'click', toggleMoreUses );
			$hideMoreUses = $( '.uses .hide-more' ).on( 'click', toggleMoreUses );
		}
	}

	function toggleMoreUses( e ) {
		e.preventDefault();

		$usesList.toggle();

		$showMoreUses.toggle();
		$hideMoreUses.toggle();
	}

	function toggleMoreUsedBy( e ) {
		e.preventDefault();

		$usedByList.toggle();

		$showMoreUsedBy.toggle();
		$hideMoreUsedBy.toggle();
	}

	$( onLoad );
} )( jQuery );
