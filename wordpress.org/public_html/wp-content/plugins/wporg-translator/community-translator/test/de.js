var assert = require('better-assert'),
	Locale = require('../lib/locale');
	TranslationPair = require('../lib/translation-pair');

describe('German', function () {
	var locale = new Locale( 'de', 'German', 'nplurals=2; plural=n != 1;');
	var translationPair = new TranslationPair( locale, ['%(numberOfThings) thing', '%(numberOfThings) things'] );

	it( 'should have 2 plurals', function () {
		assert( 2 === translationPair.getTranslation().getTextItems().length);
	});

	translationPair.updateAllTranslations( [ [ '1 things', '2 things'] ] );
	it( 'should accept a new translation', function () {
		assert( 2 === translationPair.getTranslation().getTextItems().length);
	});

	translationPair.updateAllTranslations( [ [ '1 thing', '2 things', '3 things' ] ] );
	it( 'should not accept a new translation with wrong number of plurals', function () {
		assert( 2 === translationPair.getTranslation().getTextItems().length);
	});

});
