var assert = require( 'chai' ).assert,
	fs = require( 'fs' ),
	Locale = require( '../../lib/locale' ),
	Walker = require( '../../lib/walker' ),
	TranslationPair = require( '../../lib/translation-pair' );

describe( 'String Extraction', function() {


	beforeEach( function( done ) {

		fs.readFile( './test/string-extraction/test.html', 'utf-8', function( err, htmlFromFile ) {
			if ( err ) {
				throw err;
			}
			$( 'body' ).html( htmlFromFile );
			done();
		} );

	} );

	describe( 'Brazilian Portuguese', function() {
		var translationData = require( './translation-data/pt-br.js' );
		before( function( done ) {
			TranslationPair.setTranslationData( translationData );
			done();
		} );

		it( 'should not match the outer elements', function() {
			assert.isFalse( TranslationPair.extractFrom( $( 'body' ) ) );
		} );

		it( 'should match the inner elements', function() {
			assert.equal( TranslationPair.extractFrom( $( '#inner-element' ) ).getOriginal().getSingular(),
				translationData.placeholdersUsedOnPage[ 0 ].original
			);

			assert.equal( TranslationPair.extractFrom( $( '#o2_extend_resolved_posts_unresolved_posts-2 div div span.showing' ) ).getOriginal().getSingular(),
				translationData.placeholdersUsedOnPage[ 1 ].original
			);
		} );

	} );

	describe( 'Hebrew', function() {
		var walker,
			translationData = require( './translation-data/he.js' );
		before( function( done ) {
			TranslationPair.setTranslationData( translationData );
			walker = new Walker( TranslationPair, $ );
			done();
		} );

		it( 'should not match the outer elements', function() {
			assert.equal( TranslationPair.extractFrom( $( 'body' ) ), false );
		} );

		it( 'should match the inner elements', function() {
			assert.equal( TranslationPair.extractFrom( $( '#yoav span.posted-on' ) ).getOriginal().getSingular(),
				translationData.placeholdersUsedOnPage[ 15 ].original
			);
		} );
		it( 'should not match too much', function() {
			walker.walkTextNodes( $( 'body' )[ 0 ], function( translationPair, enclosingNode ) {
				enclosingNode.addClass( 'translator-translatable' );
			} );
			assert.isFalse( $( '#yoav div.entry-meta' ).hasClass( 'translator-translatable' ) );
			assert.equal( $( '#yoav .translator-translatable' ).length, 2 );
			assert.isTrue( $( '#yoav .translator-checked' ).length > 0 );
		} );

	} );

	describe( 'English UK', function() {
		var walker, translationData = require( './translation-data/en-uk.js' );
		before( function( done ) {
			TranslationPair.setTranslationData( translationData );
			walker = new Walker( TranslationPair, $ );
			done();
		} );

		it( 'should not match the outer elements', function() {
			assert.equal( TranslationPair.extractFrom( $( 'body' ) ), false );
		} );


		it( 'should not match the content text', function() {
			walker.walkTextNodes( $( '#jack-lenox' )[ 0 ], function( translationPair, enclosingNode ) {
				enclosingNode.addClass( 'translator-translatable' );
			} );
			assert.isTrue( $( '#jack-lenox p' ).hasClass( 'translator-checked' ) );
			assert.isFalse( $( '#jack-lenox p' ).hasClass( 'translator-translatable' ) );
			assert.equal( $( '.translator-translatable' ).length, 0 );
		} );

	} );

	describe( 'Spanish', function() {
		var walker,
			translationData = require( './translation-data/es.js' );
		before( function( done ) {
			TranslationPair.setTranslationData( translationData );
			walker = new Walker( TranslationPair, $ );
			done();
		} );

		it( 'should match the Reply button', function() {
			walker.walkTextNodes( $( '#jp-post-flair' )[ 0 ], function( translationPair, enclosingNode ) {
				enclosingNode.addClass( 'translator-translatable' );
			} );
			assert.isTrue( $( '#jp-post-flair a.o2-reply' ).hasClass( 'translator-checked' ) );
			assert.isTrue( $( '#jp-post-flair a.o2-reply' ).hasClass( 'translator-translatable' ) );
		} );

	} );

	describe( 'anyChildMatches', function() {

		it( 'should matches direct children', function() {
			assert.isTrue( TranslationPair._test.anyChildMatches( $( 'div.sub' ), /blocks\s*spam/ ) );
		} );

		it( 'should matches indirect children', function() {
			assert.isTrue( TranslationPair._test.anyChildMatches( $( 'body' ), /blocks\s*spam/ ) );
		} );

		it( 'should match placeholders', function() {
			var spaceUsedRegex = /\s*(.*?) MB \((.*?)%\) de espacio usado\s*/;
			assert.isTrue( TranslationPair._test.anyChildMatches( $( 'div.sub' ), spaceUsedRegex ) );
		} );

		it( 'Should not match itself', function() {
			assert.isFalse( TranslationPair._test.anyChildMatches( $( 'p.akismet-right-now' ), /blocks\s*spam/ ) );
		} );
	} );

} );
