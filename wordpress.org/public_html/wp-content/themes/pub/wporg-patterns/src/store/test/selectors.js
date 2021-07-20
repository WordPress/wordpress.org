/**
 * Internal dependencies
 */
import apiPatterns from './fixtures/patterns';
import apiCategories from './fixtures/categories';
import {
	getCategories,
	getCategoryBySlug,
	getCurrentQuery,
	getFavorites,
	getPattern,
	getPatternFlagReasons,
	getPatternTotalPagesByQuery,
	getPatternTotalsByQuery,
	getPatterns,
	getPatternsByQuery,
	getQueryFromUrl,
	getUrlFromQuery,
	hasLoadedCategories,
	isFavorite,
	isLoadingCategories,
	isLoadingPatternFlagReasons,
	isLoadingPatternsByQuery,
} from '../selectors';

global.wporgPatternsUrl = { site: 'http://localhost:8889/' };

describe( 'selectors', () => {
	const initialState = {
		patterns: {
			queries: {},
			byId: {},
		},
		favorites: [],
	};

	const state = {
		patterns: {
			queries: {
				'': {
					total: 4,
					totalPages: 2,
					1: [ 31, 25 ],
					2: [ 27, 26 ],
				},
				'empty=1': {
					total: 0,
					totalPages: 0,
					1: [],
				},
			},
			byId: apiPatterns.reduce( ( acc, cur ) => ( { ...acc, [ cur.id ]: cur } ), {} ),
		},
		favorites: [ 25, 27 ],
		categories: apiCategories,
	};

	describe( 'isLoadingPatternsByQuery', () => {
		it( 'should show that a query is loading', () => {
			expect( isLoadingPatternsByQuery( initialState, '' ) ).toEqual( true );
		} );

		it( 'should show that a query is loaded', () => {
			expect( isLoadingPatternsByQuery( state, '' ) ).toEqual( false );
		} );

		it( 'should show that a query is loading, even if other queries are loaded', () => {
			expect( isLoadingPatternsByQuery( state, { loading: 1 } ) ).toEqual( true );
		} );

		it( 'should show that a query is loaded, even it is empty', () => {
			expect( isLoadingPatternsByQuery( state, { empty: 1 } ) ).toEqual( false );
		} );
	} );

	describe( 'getPatterns', () => {
		it( 'should get an empty array if no patterns are loaded', () => {
			expect( getPatterns( initialState ) ).toEqual( [] );
		} );

		it( 'should get all loaded patterns', () => {
			const allPatterns = getPatterns( state );
			const isAllPatterns = allPatterns.every( ( { id: foundId } ) =>
				apiPatterns.find( ( { id: sourceId } ) => foundId === sourceId )
			);
			expect( allPatterns ).toHaveLength( apiPatterns.length );
			expect( isAllPatterns ).toEqual( true );
		} );
	} );

	describe( 'getPatternsByQuery', () => {
		it( 'should get an empty array if no patterns are loaded for this query', () => {
			expect( getPatternsByQuery( initialState, {} ) ).toEqual( [] );
		} );

		it( 'should get an empty array if no patterns are loaded for this query, even if patterns exist for another query', () => {
			expect( getPatternsByQuery( state, 'not-loaded' ) ).toEqual( [] );
		} );

		it( 'should get the list of patterns for this query', () => {
			const patternsByQuery = getPatternsByQuery( state, {} );
			expect( patternsByQuery ).toHaveLength( 2 );
			// Keep the sort order of the query: [ 31, 25 ]
			expect( patternsByQuery[ 0 ] ).toHaveProperty( 'id', 31 );
			expect( patternsByQuery[ 1 ] ).toHaveProperty( 'id', 25 );
		} );

		it( 'should get the second page of patterns for this query', () => {
			const patternsByQuery = getPatternsByQuery( state, { page: 2 } );
			expect( patternsByQuery ).toHaveLength( 2 );
			// Keep the sort order of the query: [ 27, 26 ]
			expect( patternsByQuery[ 0 ] ).toHaveProperty( 'id', 27 );
			expect( patternsByQuery[ 1 ] ).toHaveProperty( 'id', 26 );
		} );
	} );

	describe( 'getPatternTotalsByQuery', () => {
		it( 'should get 0 if no patterns are loaded for this query', () => {
			expect( getPatternTotalsByQuery( initialState, {} ) ).toEqual( 0 );
		} );

		it( 'should get the total number of patterns for this query, regardless of pagination', () => {
			expect( getPatternTotalsByQuery( state, {} ) ).toEqual( 4 );
			expect( getPatternTotalsByQuery( state, { page: 2 } ) ).toEqual( 4 );
		} );
	} );

	describe( 'getPatternTotalPagesByQuery', () => {
		it( 'should get 0 if no patterns are loaded for this query', () => {
			expect( getPatternTotalPagesByQuery( initialState, {} ) ).toEqual( 0 );
		} );

		it( 'should get the total number of pages for this query, regardless of pagination', () => {
			expect( getPatternTotalPagesByQuery( state, {} ) ).toEqual( 2 );
			expect( getPatternTotalPagesByQuery( state, { page: 2 } ) ).toEqual( 2 );
		} );
	} );

	describe( 'getPattern', () => {
		it( 'should get an null if no patterns are loaded yet', () => {
			expect( getPattern( initialState, 99 ) ).toBeNull();
		} );

		it( 'should get an null if this pattern is not loaded yet', () => {
			expect( getPattern( state, 99 ) ).toBeNull();
		} );

		it( 'should get the expected pattern', () => {
			const pattern = getPattern( state, 25 );
			expect( pattern ).toHaveProperty( 'id', 25 );
			expect( pattern ).toHaveProperty( 'title.rendered', 'Large header with a heading' );
		} );
	} );

	describe( 'getCurrentQuery', () => {
		it( 'should get an empty object if there is no query', () => {
			const initialQueryState = {
				currentQuery: {},
			};

			expect( getCurrentQuery( initialQueryState ) ).toMatchObject( {} );
		} );

		it( 'should get an the correct query object', () => {
			const initialQueryState = {
				currentQuery: {
					'pattern-categories': [ 1 ],
				},
			};

			expect( getCurrentQuery( initialQueryState ) ).toMatchObject( initialQueryState.currentQuery );
		} );
	} );

	describe( 'isLoadingCategories', () => {
		it( 'should get true when state is null', () => {
			const categoryState = {
				categories: null,
			};

			expect( isLoadingCategories( categoryState ) ).toBe( true );
		} );

		it( 'should get false when state is an array', () => {
			const categoryState = {
				categories: apiCategories,
			};
			expect( isLoadingCategories( categoryState ) ).toBe( false );
		} );
	} );

	describe( 'hasLoadedCategories', () => {
		it( 'should get false when state is undefined', () => {
			const categoryState = {
				categories: undefined,
			};
			expect( hasLoadedCategories( categoryState ) ).toBe( false );
		} );

		it( 'should get true when state is an array', () => {
			const categoryState = {
				categories: apiCategories,
			};
			expect( hasLoadedCategories( categoryState ) ).toBe( true );
		} );
	} );

	describe( 'getCategories', () => {
		it( 'should get a list of categories', () => {
			const categoryState = {
				categories: apiCategories,
			};

			expect( getCategories( categoryState ) ).toHaveLength( apiCategories.length );
		} );
	} );

	describe( 'getCategoryBySlug', () => {
		it( 'should get undefined if categories have not loaded', () => {
			expect(
				getCategoryBySlug(
					{
						categories: undefined,
					},
					'header'
				)
			).toBeUndefined();

			expect(
				getCategoryBySlug(
					{
						categories: null,
					},
					'header'
				)
			).toBeUndefined();
		} );

		it( 'should get undefined if there are no categories', () => {
			const categoryState = {
				categories: [],
			};

			expect( getCategoryBySlug( categoryState, apiCategories[ 0 ].slug ) ).toBeUndefined();
		} );

		it( 'should get undefined if no category exist', () => {
			const categoryState = {
				categories: apiCategories,
			};

			expect( getCategoryBySlug( categoryState, 'missing-category-slug' ) ).toBeUndefined();
		} );

		it( 'should get the correct category', () => {
			const categoryState = {
				categories: apiCategories,
			};

			expect( getCategoryBySlug( categoryState, apiCategories[ 0 ].slug ) ).toHaveProperty(
				'id',
				apiCategories[ 0 ].id
			);
		} );
	} );

	describe( 'getQueryFromUrl', () => {
		const baseUrl = global.wporgPatternsUrl.site.replace( /\/$/, '' );

		it( 'should return empty object if no URL is passed.', async () => {
			expect( getQueryFromUrl( state, `${ baseUrl }` ) ).toEqual( {} );
			expect( getQueryFromUrl( state, `${ baseUrl }/` ) ).toEqual( {} );
			// This is functionally the same as `/` or `''`.
			expect( getQueryFromUrl( state, `${ baseUrl }//` ) ).toEqual( {} );
		} );

		it( 'should return a query object representing this URL.', async () => {
			expect( getQueryFromUrl( state, `${ baseUrl }/page/3/` ) ).toEqual( {
				page: 3,
			} );
			expect( getQueryFromUrl( state, `${ baseUrl }/search/foo` ) ).toEqual( {
				search: 'foo',
			} );
			expect( getQueryFromUrl( state, `${ baseUrl }/author/username/` ) ).toEqual( {
				author_name: 'username',
			} );
			expect( getQueryFromUrl( state, `${ baseUrl }/my-patterns/draft/page/2/` ) ).toEqual( {
				status: 'draft',
				page: 2,
			} );
			expect( getQueryFromUrl( state, `${ baseUrl }/categories/header` ) ).toEqual( {
				'pattern-categories': 3,
			} );
			expect( getQueryFromUrl( state, `${ baseUrl }/categories/header?page=2` ) ).toEqual( {
				'pattern-categories': 3,
				page: 2,
			} );
			expect(
				getQueryFromUrl( state, `${ baseUrl }/author/username/categories/header/?orderby=date&page=3` )
			).toEqual( {
				author_name: 'username',
				'pattern-categories': 3,
				orderby: 'date',
				page: 3,
			} );
		} );

		it( 'should ignore any hash values.', async () => {
			expect( getQueryFromUrl( state, `${ baseUrl }/#content` ) ).toEqual( {} );
			expect( getQueryFromUrl( state, `${ baseUrl }/page/3/#content` ) ).toEqual( { page: 3 } );
			expect( getQueryFromUrl( state, `${ baseUrl }/?orderby=date#content` ) ).toEqual( {
				orderby: 'date',
			} );
		} );

		it( 'should ignore any malformed path segments.', async () => {
			expect( getQueryFromUrl( state, `${ baseUrl }/page/` ) ).toEqual( {} );
			expect( getQueryFromUrl( state, `${ baseUrl }/author/page/2` ) ).toEqual( { page: 2 } );
			expect( getQueryFromUrl( state, `${ baseUrl }/category/header` ) ).toEqual( {} );
			expect( getQueryFromUrl( state, `${ baseUrl }/foo/bar` ) ).toEqual( {} );
			expect( getQueryFromUrl( state, `${ baseUrl }/author/categories/?orderby=date` ) ).toEqual( {
				orderby: 'date',
			} );
			expect( getQueryFromUrl( state, `${ baseUrl }/author/page/` ) ).toEqual( {} );
		} );
	} );

	describe( 'getUrlFromQuery', () => {
		const baseUrl = global.wporgPatternsUrl.site.replace( /\/$/, '' );

		it( 'should return the base URL if no query is passed.', async () => {
			expect( getUrlFromQuery( state ) ).toBe( `${ baseUrl }/` );
			expect( getUrlFromQuery( state, {} ) ).toBe( `${ baseUrl }/` );
		} );

		it( 'should return a URL for the given query.', async () => {
			expect(
				getUrlFromQuery( state, {
					page: 2,
				} )
			).toBe( `${ baseUrl }/page/2/` );
			expect(
				getUrlFromQuery( state, {
					author_name: 'username',
					page: 2,
				} )
			).toBe( `${ baseUrl }/author/username/page/2/` );
			expect(
				getUrlFromQuery( state, {
					author_name: 'username',
					orderby: 'favorite_count',
				} )
			).toBe( `${ baseUrl }/author/username/?orderby=favorite_count` );
			expect(
				getUrlFromQuery( state, {
					author_name: 'username',
					'pattern-categories': 3,
					orderby: 'date',
					page: 3,
				} )
			).toBe( `${ baseUrl }/author/username/categories/header/page/3/?orderby=date` );
		} );

		it( 'should add extra object properties as query strings.', async () => {
			expect(
				getUrlFromQuery( state, {
					foo: 'bar',
				} )
			).toBe( `${ baseUrl }/?foo=bar` );
			expect(
				getUrlFromQuery( state, {
					page: 2,
					foo: 'bar',
				} )
			).toBe( `${ baseUrl }/page/2/?foo=bar` );
			expect(
				getUrlFromQuery( state, {
					orderby: 'date',
					foo: 'bar',
				} )
			).toBe( `${ baseUrl }/?orderby=date&foo=bar` );
		} );
	} );

	describe( 'getPatternFlagReasons', () => {
		it( 'should get undefined if query has not been made', () => {
			expect( getPatternFlagReasons( {} ) ).toBeUndefined();
		} );

		it( 'should get array if query has completed', () => {
			const reasons = [
				{ id: 1, name: 'crude' },
				{ id: 2, name: 'rude' },
			];
			expect( getPatternFlagReasons( { patternFlagReasons: reasons } ) ).toEqual( reasons );
		} );
	} );

	describe( 'isLoadingPatternFlagReasons', () => {
		it( 'should get false if not null', () => {
			expect(
				isLoadingPatternFlagReasons( {
					patternFlagReasons: [],
				} )
			).toBe( false );
		} );

		it( 'should get true if null', () => {
			expect(
				isLoadingPatternFlagReasons( {
					patternFlagReasons: null,
				} )
			).toBe( true );
		} );
	} );

	describe( 'getFavorites', () => {
		it( 'should get an empty array if no favorites are loaded', () => {
			expect( getFavorites( initialState ) ).toEqual( [] );
		} );

		it( 'should get the list of favorites if they exist', () => {
			expect( getFavorites( state ) ).toEqual( [ 25, 27 ] );
		} );
	} );

	describe( 'isFavorite', () => {
		it( 'should get false if no favorites are loaded', () => {
			expect( isFavorite( initialState, 2 ) ).toBe( false );
		} );

		it( 'should get false if the ID requested is not in the list', () => {
			expect( isFavorite( state, 2 ) ).toBe( false );
		} );

		it( 'should get false if the ID requested is not a valid ID', () => {
			expect( isFavorite( state, 'fake' ) ).toBe( false );
		} );

		it( 'should get true if the ID requested is found in the list', () => {
			expect( isFavorite( state, 25 ) ).toBe( true );
		} );
	} );
} );
