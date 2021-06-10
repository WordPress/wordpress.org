/**
 * Internal dependencies
 */
import { getQueryString } from '../utils';

describe( 'getQueryString', () => {
	it( 'should return empty string when no query is passed', async () => {
		expect( getQueryString() ).toEqual( '' );
		expect( getQueryString( {} ) ).toEqual( '' );
		expect( getQueryString( '' ) ).toEqual( '' );
	} );

	it( 'should convert a simple query to string', () => {
		expect( getQueryString( { 'pattern-categories': 3 } ) ).toEqual( 'pattern-categories=3' );
		expect(
			getQueryString( {
				'pattern-categories': 3,
				'pattern-keywords': 10,
			} )
		).toEqual( 'pattern-categories=3&pattern-keywords=10' );
	} );

	it( 'should handle array values', () => {
		expect( getQueryString( { 'pattern-categories': [ 3, 5 ] } ) ).toEqual( 'pattern-categories=3%2C5' );
	} );

	it( 'should remove any pagination parameters from the string', () => {
		expect( getQueryString( { 'pattern-categories': 3, page: 2 } ) ).toEqual( 'pattern-categories=3' );
		expect( getQueryString( { 'pattern-categories': 3, per_page: 10 } ) ).toEqual( 'pattern-categories=3' );
		expect( getQueryString( { 'pattern-categories': 3, page: 2, per_page: 10 } ) ).toEqual(
			'pattern-categories=3'
		);
	} );
} );
