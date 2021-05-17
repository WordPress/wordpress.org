/**
 * Internal dependencies
 */
import apiPatterns from './fixtures/patterns';
import apiPatternsPage2 from './fixtures/patterns-page-2';
import apiCategories from './fixtures/categories';
import apiPatternFlagReasons from './fixtures/pattern-flag-reasons';
import { categories, favorites, patternFlagReasons, patterns } from '../reducer';

describe( 'state', () => {
	describe( 'patterns', () => {
		it( 'should store the patterns in state', () => {
			const state = patterns(
				{},
				{
					type: 'LOAD_BLOCK_PATTERNS',
					query: '',
					patterns: apiPatterns,
				}
			);

			expect( state.queries[ '' ] ).toHaveLength( 5 );
			expect( state.byId ).toHaveProperty( '31' );
		} );

		it( 'should store the next page of patterns in state', () => {
			const state = patterns(
				{
					queries: { '': [ 31, 25, 26, 27, 28 ] },
					byId: apiPatterns.reduce( ( acc, cur ) => ( { ...acc, [ cur.id ]: cur } ), {} ),
				},
				{
					type: 'LOAD_BLOCK_PATTERNS',
					query: '',
					patterns: apiPatternsPage2,
				}
			);

			expect( state.queries[ '' ] ).toHaveLength( 10 );
			expect( state.byId ).toHaveProperty( '31' );
			expect( state.byId ).toHaveProperty( '15' );
		} );

		it( 'should store a different query of patterns in state', () => {
			const state = patterns(
				{
					queries: { '': [ 31, 25, 26, 27, 28 ] },
					byId: apiPatterns.reduce( ( acc, cur ) => ( { ...acc, [ cur.id ]: cur } ), {} ),
				},
				{
					type: 'LOAD_BLOCK_PATTERNS',
					query: 'pattern-categories=3',
					patterns: apiPatternsPage2,
				}
			);

			expect( state.queries[ '' ] ).toHaveLength( 5 );
			expect( state.queries[ 'pattern-categories=3' ] ).toHaveLength( 5 );
			expect( state.byId ).toHaveProperty( '31' );
			expect( state.byId ).toHaveProperty( '15' );
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
			} );
			expect( state ).toEqual( [ 1, 2, 3, 5 ] );
		} );

		it( 'should not add a duplicate pattern to the list', () => {
			const state = favorites( [ 1, 2, 3 ], {
				type: 'ADD_FAVORITE',
				patternId: 1,
			} );
			expect( state ).toEqual( [ 1, 2, 3 ] );
		} );

		it( 'should remove the unfavorited pattern id from the list', () => {
			const state = favorites( [ 1, 2, 3 ], {
				type: 'REMOVE_FAVORITE',
				patternId: 3,
			} );
			expect( state ).toEqual( [ 1, 2 ] );
		} );

		it( 'should not remove a pattern that is not in the list', () => {
			const state = favorites( [ 1, 2, 3 ], {
				type: 'REMOVE_FAVORITE',
				patternId: 5,
			} );
			expect( state ).toEqual( [ 1, 2, 3 ] );
		} );
	} );
} );
