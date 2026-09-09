var assert = require( 'chai' ).assert,
	GlotPress = require( '../lib/glotpress' );

describe( 'GlotPress', function() {

	var GlotPressInstance;

	describe( 'getPermalink', function() {
		var glotPressProject = false,
			translationPairMock = {
			getGlotPressProject: function() {
				return glotPressProject;
			},
			getOriginal: function() {
				return {
					getId: function() {
						return 123;
					}
				};
			},
		};

		before( function( done ) {
			GlotPressInstance = new GlotPress( {
				getLocaleCode: function() {
					return 'en';
				}
			} );
			done();
		} );

		it( 'should return the correct default permalink', function() {
			GlotPressInstance.loadSettings( {
				url: 'https://translate.wordpress.com',
				project: 'test',
			} );
			assert.equal(
				GlotPressInstance.getPermalink( translationPairMock ),
				'https://translate.wordpress.com/projects/test/en/default?filters[original_id]=123'
			);
		} );

		it( 'should return the correct permalink with translation set slug', function() {
			GlotPressInstance.loadSettings( {
				url: 'https://translate.wordpress.com',
				project: 'test',
				translation_set_slug: 'formal',
			} );
			assert.equal(
				GlotPressInstance.getPermalink( translationPairMock ),
				'https://translate.wordpress.com/projects/test/en/formal?filters[original_id]=123'
			);
		} );
		it( 'should return the correct permalink with value from getGlotPressProject()', function() {
			GlotPressInstance.loadSettings( {
				url: 'https://translate.wordpress.com',
				project: 'test',
				translation_set_slug: 'formal',
			} );
			glotPressProject = 'wpcom';
			assert.equal(
				GlotPressInstance.getPermalink( translationPairMock ),
				'https://translate.wordpress.com/projects/wpcom/en/formal?filters[original_id]=123'
			);
		} );
	} );

} );