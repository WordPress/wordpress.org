var assert = require('better-assert'),
	Locale = require('../lib/locale');
	TranslationPair = require('../lib/translation-pair');

describe('Russian', function () {
	var locale = new Locale( 'ru', 'Russian', 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);');
	var translationPair = new TranslationPair( locale, ['%(numberOfThings) thing', '%(numberOfThings) things'] );

	it( 'should have 3 plurals', function () {
		assert( 3 === translationPair.getTranslation().getTextItems().length);
	});

	translationPair.updateAllTranslations( [ [ '1 things', '2 things', 'a few things' ] ] );
	it( 'should accept a new translation', function () {
		assert( 3 === translationPair.getTranslation().getTextItems().length);
	});

	translationPair.updateAllTranslations( [ [ '1 thing', '2 things' ] ] );
	it( 'should not accept a new translation with wrong number of plurals', function () {
		assert( 3 === translationPair.getTranslation().getTextItems().length);
	});

});
