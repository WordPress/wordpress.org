import { getCategoryFromPath, removeEmptyArgs, removeQueryString } from '../index';

describe( 'utils', () => {
	describe( 'getCategoryFromPath', () => {
		it( 'should return empty string if path is empty', async () => {
			expect( getCategoryFromPath( '' ) ).toEqual( '' );
		} );

		it( 'should return empty string if path is "/"', async () => {
			expect( getCategoryFromPath( '/' ) ).toEqual( '' );
		} );

		it( 'should correctly return category', async () => {
			expect( getCategoryFromPath( 'patterns-categories/header' ) ).toEqual( 'header' );
			expect( getCategoryFromPath( '/patterns-categories/header' ) ).toEqual( 'header' );
			expect( getCategoryFromPath( '/patterns-categories/header/' ) ).toEqual( 'header' );
			expect( getCategoryFromPath( '/patterns-categories/subdirectory/header/' ) ).toEqual( 'header' );
			expect( getCategoryFromPath( '/patterns-categories/subdirectory/header/?search=test' ) ).toEqual(
				'header'
			);
		} );
	} );

	describe( 'removeQueryString', () => {
		it( 'should return the path if there is no query string', async () => {
			const path = '/path/without/string';

			expect( removeQueryString( path ) ).toEqual( path );
		} );

		it( 'should return the path if there is a query string', async () => {
			const base = '/path/without/string';
			const path = `${ base }?search=keyword`;

			expect( removeQueryString( path ) ).toEqual( base );
		} );
	} );

	describe( 'removeEmptyArgs', () => {
		it( 'should remove empty properties', async () => {
			const shouldReturn = {
				string_1: 'first value',
				string_2: 'second value',
				boolean: false,
			};

			const obj = {
				...shouldReturn,
				empty_string: '',
				undefined: undefined,
				null: null,
			};

			expect( removeEmptyArgs( {} ) ).toMatchObject( {} );
			expect( removeEmptyArgs( obj ) ).toMatchObject( shouldReturn );
		} );
	} );
} );
