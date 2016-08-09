/**
 * function-reference.js
 *
 * Handles all interactivity on the single function page
 */
var wporg_developer = ( function( $ ) {
	'use strict';

	var $sourceCollapsedHeight;

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

		SyntaxHighlighter.highlight();

		// 1em (margin) + 10 * 17px + 10. Lines are 1.1em which rounds to 17px: calc( 1em + 17px * 10 + 10 ).
		// Extra 10px added to partially show next line so it's clear there is more.
		$sourceCollapsedHeight = 196;
		sourceCodeDisplay();
	}

	function sourceCodeDisplay( element ) {
		 
		if ( element !== undefined ) {
			// Find table inside a specific source code element if passed.
			var sourceCode = $( '.source-content', element ).find( 'table' );
		} else {
			// Find table inside all source code elements.
			var sourceCode = $( '.source-content' ).find( 'table' );
		}

		if ( !sourceCode.length ) {
			return;
		}

		sourceCode.each( function( t ) {
			if ( ( $sourceCollapsedHeight - 12 ) < $( this ).height() ) {

				var sourceContent = $( this ).closest( '.source-content' );

				// Do this with javascript so javascript-less can enjoy the total sourcecode
				sourceContent.find( '.source-code-container' ).css( {
					height: $sourceCollapsedHeight + 'px'
				} );

				sourceContent.find( '.source-code-links' ).find( 'span:first' ).show();
				sourceContent.find( '.show-complete-source' ).show();
				sourceContent.find( '.show-complete-source' ).off( 'click.togglesource' ).on( 'click.togglesource', toggleCompleteSource );
				sourceContent.find( '.less-complete-source' ).off( 'click.togglesource' ).on( 'click.togglesource', toggleCompleteSource );
			}
		} );
	}

	function toggleCompleteSource( e ) {
		e.preventDefault();

		var sourceContent = $( this ).closest( '.source-content' );

		if ( $( this ).parent().find( '.show-complete-source' ).is( ':visible' ) ) {
			var heightGoal = sourceContent.find( 'table' ).height() + 45; // takes into consideration potential x-scrollbar
		} else {
			var heightGoal = $sourceCollapsedHeight;
		}

		sourceContent.find( '.source-code-container:first' ).animate( { height: heightGoal + 'px' } );

		$( this ).parent().find( 'a' ).toggle();
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

	// Expose the sourceCodeDisplay() function for usage outside of this function.
	return {
		sourceCodeDisplay: sourceCodeDisplay
	};

} )( jQuery );
