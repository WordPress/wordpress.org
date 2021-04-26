/**
 * Internal dependencies
 */
import apiPatterns from './fixtures/patterns';
import apiCategories from './fixtures/categories';
import {
	getCategories,
	getCategoryBySlug,
	getCurrentQuery,
	getPattern,
	getPatterns,
	getPatternsByQuery,
	hasLoadedCategories,
	isLoadingCategories,
	isLoadingPatternsByQuery,
} from '../selectors';

describe( 'selectors', () => {
	const initialState = {
		patterns: {
			queries: {},
			byId: {},
		},
	};

	const state = {
		patterns: {
			queries: {
				'': [ 31, 25, 27, 26 ],
				'empty=1': [],
			},
			byId: apiPatterns.reduce( ( acc, cur ) => ( { ...acc, [ cur.id ]: cur } ), {} ),
		},
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
			expect( getPatternsByQuery( initialState, '' ) ).toEqual( [] );
		} );

		it( 'should get an empty array if no patterns are loaded for this query, even if patterns exist for another query', () => {
			expect( getPatternsByQuery( state, 'not-loaded' ) ).toEqual( [] );
		} );

		it( 'should get the list of patterns for this query', () => {
			const patternsByQuery = getPatternsByQuery( state, '' );
			expect( patternsByQuery ).toHaveLength( 4 );
			// Keep the sort order of the query: [ 31, 25, 27, 26 ]
			expect( patternsByQuery[ 0 ] ).toHaveProperty( 'id', 31 );
			expect( patternsByQuery[ 1 ] ).toHaveProperty( 'id', 25 );
			expect( patternsByQuery[ 2 ] ).toHaveProperty( 'id', 27 );
			expect( patternsByQuery[ 3 ] ).toHaveProperty( 'id', 26 );
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
} );
