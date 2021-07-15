/**
 * Internal dependencies
 */
import apiPatterns from './fixtures/patterns';
import apiPatternsPage2 from './fixtures/patterns-page-2';
import apiCategories from './fixtures/categories';
import apiPatternFlagReasons from './fixtures/pattern-flag-reasons';
import { categories, favorites, patternFlagReasons, patterns } from '../reducer';

// Set up the global.
global.wporgPatternsUrl = { site: 'http://localhost:8889/' };

describe( 'state', () => {
	describe( 'patterns', () => {
		it( 'should store the patterns in state', () => {
			const state = patterns(
				{},
				{
					type: 'LOAD_BLOCK_PATTERNS',
					page: 1,
					patterns: apiPatterns,
					query: '',
					total: 10,
					totalPages: 2,
				}
			);

			expect( state.queries[ '' ].total ).toBe( 10 );
			expect( state.queries[ '' ].totalPages ).toBe( 2 );
			expect( state.queries[ '' ][ '1' ] ).toHaveLength( 5 );
			expect( state.byId ).toHaveProperty( '31' );
		} );

		it( 'should store the next page of patterns in state', () => {
			const state = patterns(
				{
					queries: {
						'': {
							total: 10,
							totalPages: 2,
							1: [ 31, 25, 26, 27, 28 ],
						},
					},
					byId: apiPatterns.reduce( ( acc, cur ) => ( { ...acc, [ cur.id ]: cur } ), {} ),
				},
				{
					type: 'LOAD_BLOCK_PATTERNS',
					page: 2,
					patterns: apiPatternsPage2,
					query: '',
					total: 10,
					totalPages: 2,
				}
			);

			expect( state.queries[ '' ].total ).toBe( 10 );
			expect( state.queries[ '' ].totalPages ).toBe( 2 );
			expect( state.queries[ '' ][ '1' ] ).toHaveLength( 5 );
			expect( state.queries[ '' ][ '2' ] ).toHaveLength( 5 );
			expect( state.byId ).toHaveProperty( '31' );
			expect( state.byId ).toHaveProperty( '15' );
		} );

		it( 'should store a different query of patterns in state', () => {
			const state = patterns(
				{
					queries: {
						'': {
							total: 10,
							totalPages: 2,
							1: [ 31, 25, 26, 27, 28 ],
						},
					},
					byId: apiPatterns.reduce( ( acc, cur ) => ( { ...acc, [ cur.id ]: cur } ), {} ),
				},
				{
					type: 'LOAD_BLOCK_PATTERNS',
					page: 1,
					patterns: apiPatternsPage2,
					query: 'pattern-categories=3',
					total: 5,
					totalPages: 1,
				}
			);

			expect( state.queries[ '' ].total ).toBe( 10 );
			expect( state.queries[ '' ].totalPages ).toBe( 2 );
			expect( state.queries[ '' ][ '1' ] ).toHaveLength( 5 );
			expect( state.queries[ 'pattern-categories=3' ].total ).toBe( 5 );
			expect( state.queries[ 'pattern-categories=3' ].totalPages ).toBe( 1 );
			expect( state.queries[ 'pattern-categories=3' ][ '1' ] ).toHaveLength( 5 );
			expect( state.byId ).toHaveProperty( '31' );
			expect( state.byId ).toHaveProperty( '15' );
		} );

		it( 'should handle an error', () => {
			const state = patterns(
				{
					queries: {
						'': {
							total: 10,
							totalPages: 2,
							1: [ 31, 25, 26, 27, 28 ],
						},
					},
					byId: apiPatterns.reduce( ( acc, cur ) => ( { ...acc, [ cur.id ]: cur } ), {} ),
				},
				{
					type: 'ERROR_BLOCK_PATTERNS',
					query: '',
					page: 3,
					error: {
						code: 'rest_post_invalid_page_number',
						message: 'The page number requested is larger than the number of pages available.',
						data: { status: 400 },
					},
				}
			);

			expect( state.queries[ '' ].total ).toBe( 10 );
			expect( state.queries[ '' ].totalPages ).toBe( 2 );
			expect( state.queries[ '' ][ '1' ] ).toHaveLength( 5 );
			expect( state.queries[ '' ][ '3' ] ).toHaveLength( 0 );
			expect( state.byId ).toHaveProperty( '31' );
		} );

		it( 'should update when favorites are added', () => {
			const state = patterns(
				{
					queries: {
						'': {
							total: 10,
							totalPages: 2,
							1: [ 31, 25, 26, 27, 28 ],
						},
					},
					byId: apiPatterns.reduce( ( acc, cur ) => ( { ...acc, [ cur.id ]: cur } ), {} ),
				},
				{
					type: 'ADD_FAVORITE',
					patternId: 31,
					count: 1,
				}
			);

			expect( state.byId ).toHaveProperty( '31' );
			expect( state.byId[ '31' ] ).toHaveProperty( 'title' );
			expect( state.byId[ '31' ] ).toHaveProperty( 'favorite_count' );
			expect( state.byId[ '31' ].favorite_count ).toEqual( 1 );
		} );

		it( 'should update when favorites are removed', () => {
			const state = patterns(
				{
					queries: {
						'': {
							total: 10,
							totalPages: 2,
							1: [ 31, 25, 26, 27, 28 ],
						},
					},
					byId: apiPatterns.reduce( ( acc, cur ) => ( { ...acc, [ cur.id ]: cur } ), {} ),
				},
				{
					type: 'REMOVE_FAVORITE',
					patternId: 31,
					count: 0,
				}
			);

			expect( state.byId ).toHaveProperty( '31' );
			expect( state.byId[ '31' ] ).toHaveProperty( 'title' );
			expect( state.byId[ '31' ] ).toHaveProperty( 'favorite_count' );
			expect( state.byId[ '31' ].favorite_count ).toEqual( 0 );
		} );
	} );

	describe( 'pattern', () => {
		it( 'should store the pattern in state', () => {
			const state = patterns(
				{},
				{
					type: 'LOAD_BLOCK_PATTERN',
					postId: apiPatterns[ 0 ].id,
					pattern: apiPatterns[ 0 ],
				}
			);

			expect( state.byId ).toHaveProperty( '31' );
		} );

		it( 'should store the next page of patterns in state', () => {
			const state = patterns(
				{
					queries: {},
					byId: { [ apiPatterns[ 0 ].id ]: apiPatterns[ 0 ] },
				},
				{
					type: 'LOAD_BLOCK_PATTERN',
					postId: apiPatterns[ 1 ].id,
					pattern: apiPatterns[ 1 ],
				}
			);

			expect( state.byId ).toHaveProperty( '31' );
			expect( state.byId ).toHaveProperty( '25' );
		} );

		it( 'should not affect the queries', () => {
			const state = patterns(
				{
					queries: {
						'': {
							total: 10,
							totalPages: 2,
							1: [ 31, 25, 26, 27, 28 ],
						},
					},
					byId: apiPatterns.reduce( ( acc, cur ) => ( { ...acc, [ cur.id ]: cur } ), {} ),
				},
				{
					type: 'LOAD_BLOCK_PATTERN',
					postId: apiPatternsPage2[ 0 ].id,
					pattern: apiPatternsPage2[ 0 ],
				}
			);

			expect( state.queries[ '' ].total ).toBe( 10 );
			expect( state.queries[ '' ].totalPages ).toBe( 2 );
			expect( state.queries[ '' ][ '1' ] ).toHaveLength( 5 );
			expect( state.byId ).toHaveProperty( '31' );
			expect( state.byId ).toHaveProperty( '25' );
			expect( state.byId ).toHaveProperty( '29' );
		} );
	} );

	describe( 'categories', () => {
		it( 'should return null when fetching categories', () => {
			const state = categories(
				{},
				{
					type: 'FETCH_CATEGORIES',
				}
			);

			expect( state ).toBeNull();
		} );

		it( 'should store categories in the state with the "all" category', () => {
			const state = categories(
				{},
				{
					type: 'LOAD_CATEGORIES',
					categories: apiCategories,
				}
			);

			const lengthWithAll = apiCategories.length + 1;

			expect( state ).toHaveLength( lengthWithAll );
		} );
	} );

	describe( 'pattern flag reasons', () => {
		it( 'should store the pattern flag reasons in state', () => {
			const state = patternFlagReasons(
				{},
				{
					type: 'LOAD_PATTERN_FLAG_REASONS',
					reasons: apiPatternFlagReasons,
				}
			);

			expect( state ).toEqual( apiPatternFlagReasons );
		} );
	} );

	describe( 'favorites', () => {
		it( 'should store the list of favorite pattern ids', () => {
			const state = favorites( [], {
				type: 'LOAD_FAVORITES',
				patternIds: [ 1, 2, 3 ],
			} );
			expect( state ).toEqual( [ 1, 2, 3 ] );
		} );

		it( 'should add a new favorite pattern to the list', () => {
			const state = favorites( [ 1, 2, 3 ], {
				type: 'ADD_FAVORITE',
				patternId: 5,
				count: 1,
			} );
			expect( state ).toEqual( [ 1, 2, 3, 5 ] );
		} );

		it( 'should not add a duplicate pattern to the list', () => {
			const state = favorites( [ 1, 2, 3 ], {
				type: 'ADD_FAVORITE',
				patternId: 1,
				count: 1,
			} );
			expect( state ).toEqual( [ 1, 2, 3 ] );
		} );

		it( 'should remove the unfavorited pattern id from the list', () => {
			const state = favorites( [ 1, 2, 3 ], {
				type: 'REMOVE_FAVORITE',
				patternId: 3,
				count: 1,
			} );
			expect( state ).toEqual( [ 1, 2 ] );
		} );

		it( 'should not remove a pattern that is not in the list', () => {
			const state = favorites( [ 1, 2, 3 ], {
				type: 'REMOVE_FAVORITE',
				patternId: 5,
				count: 1,
			} );
			expect( state ).toEqual( [ 1, 2, 3 ] );
		} );
	} );
} );
