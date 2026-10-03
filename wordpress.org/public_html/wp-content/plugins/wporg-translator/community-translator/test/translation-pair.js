var assert = require( 'chai' ).assert,
	fs = require( 'fs' ),
	Locale = require( '../lib/locale' ),
	TranslationPair = require( '../lib/translation-pair' ),
	jsdom = require( 'jsdom' );

describe( 'Translation Pair: Existing translation', function() {
	var locale = new Locale( 'de', 'German', 'nplurals=2; plural=n != 1;' );
	var translationPair1 = new TranslationPair( locale, [ 'Add' ], null, [ 'Hinzufügen' ] );
	var translationPair2 = new TranslationPair( locale, [ '%(numberOfThings) thing', '%(numberOfThings) things' ], null, [ '%(numberOfThings) Ding', '%(numberOfThings) Dinge' ] );

	it( 'should have an original', function() {
		assert( 'Original' === translationPair1.getOriginal().type );
	} );

	it( 'should have a translation', function() {
		assert( 'Translation' === translationPair1.getTranslation().type );
	} );

} );

describe( 'Translation Pair: No translation', function() {
	var tr,
		locale = new Locale( 'de', 'German', 'nplurals=2; plural=n != 1;' );
	var translationPair1 = new TranslationPair( locale, [ 'Add' ], null );
	var translationPair2 = new TranslationPair( locale, [ '%(numberOfThings) thing', '%(numberOfThings) things' ] );

	it( 'should have an original', function() {
		assert( 'Original' === translationPair1.getOriginal().type );
	} );

	it( 'should have a translation object', function() {
		assert( 'Translation' === translationPair1.getTranslation().type );
	} );

	it( 'should have all empty textitems', function() {
		assert( "" === translationPair1.getTranslation().getTextItems()[ 0 ].getText() );
	} );

	it( 'should have only 1 translation fields for a singular-only original', function() {
		assert( 1 === translationPair1.getTranslation().getTextItems().length );
	} );

	it( 'should have 2 translation fields for a singular-plural original', function() {
		assert( 2 === translationPair2.getTranslation().getTextItems().length );
	} );

	it( 'should retain a replaced translation', function() {
		tr = [ 'Hinzufügen' ];
		replaceTranslation( translationPair1, tr );
		assert( tr[ 0 ] === translationPair1.getTranslation().getTextItems()[ 0 ].getText() );
		assert( 1 === translationPair1.getTranslation().getTextItems().length );
	} );

	it( 'should retain replaced translations', function() {
		tr = [ '%(numberOfThings) Ding', '%(numberOfThings) Dinge' ];
		replaceTranslation( translationPair2, tr );
		assert( tr[ 0 ] === translationPair2.getTranslation().getTextItems()[ 0 ].getText() );
		assert( tr[ 1 ] === translationPair2.getTranslation().getTextItems()[ 1 ].getText() );
		assert( 2 === translationPair2.getTranslation().getTextItems().length );
	} );

	it( 'should not allow a wrong number of translations', function() {
		tr = [ 'Hinzufügen' ];
		assert( false === replaceTranslation( translationPair1, [ 'Hinzufügen', 'Hinzufügen' ] ) );
		assert( false === replaceTranslation( translationPair1, [ '%(numberOfThings) Ding', '%(numberOfThings) Dinge' ] ) );
		assert( false === replaceTranslation( translationPair2, [ 'Hinzufügen' ] ) );
		assert( false === replaceTranslation( translationPair2, [ '%(numberOfThings) Ding' ] ) );
		assert( tr[ 0 ] === translationPair1.getTranslation().getTextItems()[ 0 ].getText() );
	} );

	function replaceTranslation( translationPair, translation ) {
		var i,
			t = {};
		for ( i = 0; i < translation.length; i++ ) {
			t[ 'translation_' + i ] = translation[ i ];
		}
		return translationPair.updateAllTranslations( [ t ] );
	}

} );
