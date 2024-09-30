var assert = require('better-assert'),
	Locale = require('../lib/locale');
	TranslationPair = require('../lib/translation-pair');

describe('Japanese', function () {
	var locale = new Locale( 'jp', 'Japanese', 'nplurals=1; plural=0;');
	var translationPair = new TranslationPair( locale, ['%(numberOfThings) thing', null, '%(numberOfThings) things'] );

	it( 'should have 1 plural', function () {
		assert( 1 === translationPair.getTranslation().getTextItems().length);
	});

	translationPair.updateAllTranslations( [ ['things'] ] );
	it( 'should accept a new translation', function () {
		assert( 1 === translationPair.getTranslation().getTextItems().length);
	});

	translationPair.updateAllTranslations( [ ['1 thing', '2 things'] ] );
	it( 'should not accept a new translation with wrong number of plurals', function () {
		assert( 1 === translationPair.getTranslation().getTextItems().length);
	});

});
