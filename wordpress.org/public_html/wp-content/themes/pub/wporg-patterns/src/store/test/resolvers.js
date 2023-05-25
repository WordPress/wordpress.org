/**
 * Internal dependencies
 */
import apiPatterns from './fixtures/patterns';
import apiCategories from './fixtures/categories';
import apiPatternFlagReasons from './fixtures/pattern-flag-reasons';
import { getCategories, getFavorites, getPattern, getPatternFlagReasons, getPatternsByQuery } from '../resolvers';

// Set up the global.
global.wporgLocale = { id: 'en_US' };

describe( 'getPatternsByQuery', () => {
	it( 'yields with the requested patterns & query meta', async () => {
		const generator = getPatternsByQuery( {} );

		expect( generator.next().value ).toEqual( {
			type: 'FETCH_BLOCK_PATTERNS',
			query: '',
		} );

		// trigger apiFetch
		const { value: apiFetchAction } = generator.next();
		expect( apiFetchAction.request ).toEqual( {
			path: `/wp/v2/wporg-pattern?locale=en_US`,
			parse: false,
		} );

		// Step through the promise - the response is omitted here & mocked in the next step because `Response`
		// is a browser feature, not available in node.
		const { value: awaitPromiseControl } = generator.next( {
			/* Response omitted */
		} );
		expect( awaitPromiseControl ).toEqual( {
			type: 'AWAIT_PROMISE',
			promise: expect.any( Promise ),
		} );

		// Complete the promise and return content
		const { value: received } = generator.next( {
			total: 8,
			totalPages: 2,
			results: apiPatterns,
		} );
		expect( received ).toEqual( {
			type: 'LOAD_BLOCK_PATTERNS',
			query: '',
			page: 1,
			patterns: apiPatterns,
			total: 8,
			totalPages: 2,
		} );
	} );
} );

describe( 'getPattern', () => {
	const testPattern = apiPatterns[ 0 ];

	it( 'yields with the requested pattern', async () => {
		const generator = getPattern( testPattern.id );

		// trigger apiFetch
		const { value: apiFetchAction } = generator.next();
		expect( apiFetchAction.request ).toEqual( {
			path: '/wp/v2/wporg-pattern/' + testPattern.id,
		} );

		// Provide response and trigger action
		const { value: received } = generator.next( testPattern );
		expect( received ).toEqual( {
			type: 'LOAD_BLOCK_PATTERN',
			postId: testPattern.id,
			pattern: testPattern,
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
			path: '/wp/v2/pattern-categories?per_page=50',
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
