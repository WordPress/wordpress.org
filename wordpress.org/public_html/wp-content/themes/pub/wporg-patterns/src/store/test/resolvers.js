/**
 * Internal dependencies
 */
import apiPatterns from './fixtures/patterns';
import apiCategories from './fixtures/categories';
import { getCategories, getPatternsByQuery } from '../resolvers';

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
	it( 'yields with the requested patterns', async () => {
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
