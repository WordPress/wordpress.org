var betterAssert = require( 'better-assert' ),
	assert = require( 'assert' ),
	Locale = require( '../lib/locale' ),
	TranslationPair = require( '../lib/translation-pair' ),
	html = '<html><head></head><body></body></html>',
	jsdom = require( 'jsdom' );


// Set up jQuery related global parameters
var dom = new jsdom.JSDOM( html, {
	resources: 'usable',
	runScripts: 'dangerously',
} );
var jquery = require( 'jquery' )( dom.window );
global.window = dom.window;
global.document = dom.window.document;
global.jQuery = global.$ = jquery;

// Module level vars that depend on jQuery
var batcher = require( '../lib/batcher.js' ),
	batchedTestFunctionFoo = batcher( testFunctionFoo );

function testFunctionFoo( arrayArg, callback ) {
	return ( arrayArg.map( function( v ) {
		return v + 'foo';
	} ) );
}

describe( 'Batcher', function() {
	// Sanity check.
	it( 'depends on jQuery.Deferred', function() {
		assert( jQuery );
		assert( jQuery.Deferred );
	} );

	it( 'returns the same value for a single call',
		function() {

			batchedResult = batchedTestFunctionFoo( '1' );

			testFunctionFoo( [ '1' ],
				function( originalResult ) {
					jQuery.when()
						.then( function( result ) {
							assert.deepEqual( originalResult[0], result );
						} );
				} );
		} );

	it( 'returns the same values with multiple calls',
		function() {
			jQuery.when( batchedTestFunctionFoo( 'a' ),
					batchedTestFunctionFoo( 'b' ),
					batchedTestFunctionFoo( 'c' ),
					testFunctionFoo( [ 'a', 'b', 'c' ] ) )
				.then( function( a, b, c, originalFunctionResult ) {
					assert.deepEqual( [ a, b, c ], originalFunctionResult );
				} );
		} );

	it( 'fails if given a non-function', function() {
		assert( ! batcher( "foo" ) );
	} );

	it( 'returns a function when given a function', function() {
		assert.equal( 'function', typeof batcher( function() {} ) );
	} );

	it( 'produces a function that returns jQuery.Deferred objects',
		function() {
			// Quick and dirty test for jQuery().Deferred
			var isWhenable = function( object ) {
				return object && object.then && (
					'function' === typeof object.then );
			};

			var result = batchedTestFunctionFoo( "test" );

			assert( isWhenable( batchedTestFunctionFoo( "test" ) ) );
		} );

	it( 'calls the original function only once',
		function() {
			var counter = 0,
				batchedCountingFunction = batcher( countingFunction );

			function identity( v ) {
				return v;
			}

			function countingFunction( arrayArg, callback ) {
				counter++;
				return arrayArg.map( identity );
			}

			assert.equal( counter, 0 );
			jQuery.when( batchedCountingFunction( 'a' ),
					batchedCountingFunction( 'b' ),
					batchedCountingFunction( 'c' ) )
				.then( function() {
					assert.equal( counter, 1 );
				} );
		} );
} );
