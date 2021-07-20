import {
	getCategoryFromPath,
	getPageFromPath,
	getSearchTermFromPath,
	getValueFromPath,
	removeEmptyArgs,
	removeQueryString,
} from '../index';

describe( 'utils', () => {
	describe( 'getValueFromPath', () => {
		it( 'should return empty string if path or key is empty', async () => {
			expect( getValueFromPath( '' ) ).toEqual( '' );
			expect( getValueFromPath( '', 'key' ) ).toEqual( '' );
			expect( getValueFromPath( '/', 'key' ) ).toEqual( '' );
			expect( getValueFromPath( '/?', 'key' ) ).toEqual( '' );
		} );

		it( 'should return empty string if the path is malformed', async () => {
			expect( getValueFromPath( '/key', 'key' ) ).toEqual( '' );
		} );

		it( 'should return empty string if the key is not found', async () => {
			expect( getValueFromPath( '/nothing', 'key' ) ).toEqual( '' );
			expect( getValueFromPath( '/1/2/3/', 'key' ) ).toEqual( '' );
			expect( getValueFromPath( '/?search=test', 'key' ) ).toEqual( '' );
		} );

		it( 'should correctly return the value from a path', async () => {
			expect( getValueFromPath( '/key/header', 'key' ) ).toEqual( 'header' );
			expect( getValueFromPath( '/key/header/page/3/', 'key' ) ).toEqual( 'header' );
			expect( getValueFromPath( '/key/header/?search=test', 'key' ) ).toEqual( 'header' );
			expect( getValueFromPath( '/key/words-and-123/', 'key' ) ).toEqual( 'words-and-123' );
		} );

		it( 'should correctly return the value from a query string', async () => {
			expect( getValueFromPath( '/?key=header', 'key' ) ).toEqual( 'header' );
			expect( getValueFromPath( '/?key=header&page=2', 'key' ) ).toEqual( 'header' );
			expect( getValueFromPath( '/?search=test&key=header', 'key' ) ).toEqual( 'header' );
		} );

		it( 'should correctly return the value from a combined path & query string', async () => {
			expect( getValueFromPath( '/key/abc/?key=def', 'key' ) ).toEqual( 'def' );
		} );
	} );

	describe( 'getCategoryFromPath', () => {
		it( 'should return empty string if path is empty', async () => {
			expect( getCategoryFromPath( '' ) ).toEqual( '' );
		} );

		it( 'should correctly return the category', async () => {
			expect( getCategoryFromPath( '/categories/header' ) ).toEqual( 'header' );
			expect( getCategoryFromPath( '/categories/header/page/3/' ) ).toEqual( 'header' );
			expect( getCategoryFromPath( '/?categories=header' ) ).toEqual( 'header' );
			expect( getCategoryFromPath( '/?search=test&categories=header' ) ).toEqual( 'header' );
			expect( getCategoryFromPath( '/categories/abc/?categories=def' ) ).toEqual( 'def' );
		} );
	} );

	describe( 'getPageFromPath', () => {
		it( 'should return 1 (default first page) if the path is empty', async () => {
			expect( getPageFromPath( '' ) ).toEqual( 1 );
		} );

		it( 'should correctly return the page number', async () => {
			expect( getPageFromPath( '/categories/header' ) ).toEqual( 1 );
			expect( getPageFromPath( '/categories/header/page/3/' ) ).toEqual( 3 );
			expect( getPageFromPath( '/?categories=header&page=2' ) ).toEqual( 2 );
			expect( getPageFromPath( '/page/4' ) ).toEqual( 4 );
		} );
	} );

	describe( 'getSearchTermFromPath', () => {
		it( 'should return "" if the search term is empty', async () => {
			expect( getSearchTermFromPath( '' ) ).toEqual( '' );
		} );

		it( 'should correctly return the search term', async () => {
			expect( getSearchTermFromPath( '/search/header' ) ).toEqual( 'header' );
			expect( getSearchTermFromPath( '/search/footer/' ) ).toEqual( 'footer' );
			expect( getSearchTermFromPath( '/search/조선말/' ) ).toEqual( '조선말' );
			expect( getSearchTermFromPath( '/search/조선말/' ) ).toHaveLength( 3 );
			expect( getSearchTermFromPath( '/patterns/search/sidebar' ) ).toEqual( 'sidebar' );
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
