/**
 * Internal dependencies
 */
import apiPatterns from './fixtures/patterns';
import apiCategories from './fixtures/categories';
import apiPatternFlagReasons from './fixtures/pattern-flag-reasons';
import { getCategories, getFavorites, getPatternFlagReasons, getPatternsByQuery } from '../resolvers';

describe( 'getPatternsByQuery', () => {
	it( 'yields with the requested patterns', async () => {
		const generator = getPatternsByQuery( {} );

		expect( generator.next().value ).toEqual( {
			type: 'FETCH_BLOCK_PATTERNS',
			query: '',
		} );

		// trigger apiFetch
		const { value: apiFetchAction } = generator.next();
		expect( apiFetchAction.request ).toEqual( {
			path: '/wp/v2/wporg-pattern',
		} );

		// Provide response and trigger action
		const { value: received } = generator.next( apiPatterns );
		expect( received ).toEqual( {
			type: 'LOAD_BLOCK_PATTERNS',
			query: '',
			patterns: apiPatterns,
		} );
	} );
} );

describe( 'getCategories', () => {
	it( 'yields with the requested categories', async () => {
		const generator = getCategories();

		expect( generator.next().value ).toEqual( {
			type: 'FETCH_CATEGORIES',
		} );

		// trigger apiFetch
		const { value: apiFetchAction } = generator.next();
		expect( apiFetchAction.request ).toEqual( {
			path: '/wp/v2/pattern-categories',
		} );

		// Provide response and trigger action
		const { value: received } = generator.next( apiCategories );
		expect( received ).toEqual( {
			type: 'LOAD_CATEGORIES',
			categories: apiCategories,
		} );
	} );
} );

describe( 'getPatternFlagReasons', () => {
	it( 'yields with the requested pattern flag reasons', async () => {
		const generator = getPatternFlagReasons();

		expect( generator.next().value ).toEqual( {
			type: 'FETCH_PATTERN_FLAG_REASONS',
		} );

		// trigger apiFetch
		const { value: apiFetchAction } = generator.next();
		expect( apiFetchAction.request ).toEqual( {
			path: '/wp/v2/wporg-pattern-flag-reason',
		} );

		// Provide response and trigger action
		const { value: received } = generator.next( apiPatternFlagReasons );
		expect( received ).toEqual( {
			type: 'LOAD_PATTERN_FLAG_REASONS',
			reasons: apiPatternFlagReasons,
		} );
	} );

	describe( 'getFavorites', () => {
		it( 'yields with the requested favorite patterns', async () => {
			const generator = getFavorites();

			// trigger apiFetch
			const { value: apiFetchAction } = generator.next();
			expect( apiFetchAction.request ).toEqual( {
				path: '/wporg/v1/pattern-favorites',
			} );

			// Provide response and trigger action
			const { value: received } = generator.next( [ 1, 2, 3 ] );
			expect( received ).toEqual( {
				type: 'LOAD_FAVORITES',
				patternIds: [ 1, 2, 3 ],
			} );
		} );
	} );
} );
