import getPaginationList from '../get-pagination-list';

describe( 'getPageList', () => {
	it( 'should return empty array if no pages', async () => {
		expect( getPaginationList( 0 ) ).toEqual( [] );
	} );

	it( 'should return one item if one page', async () => {
		expect( getPaginationList( 1 ) ).toEqual( [ 1 ] );
	} );

	it( 'should return a simple list if less than 5 pages', async () => {
		expect( getPaginationList( 4 ) ).toEqual( [ 1, 2, 3, 4 ] );
	} );

	it( 'should return complex lists', async () => {
		expect( getPaginationList( 10, 1 ) ).toEqual( [ 1, 2, '…', 9, 10 ] );
		expect( getPaginationList( 10, 2 ) ).toEqual( [ 1, 2, 3, '…', 9, 10 ] );
		expect( getPaginationList( 10, 3 ) ).toEqual( [ 1, 2, 3, 4, '…', 9, 10 ] );
		expect( getPaginationList( 10, 4 ) ).toEqual( [ 1, 2, 3, 4, 5, '…', 9, 10 ] );
		expect( getPaginationList( 10, 5 ) ).toEqual( [ 1, 2, '…', 4, 5, 6, '…', 9, 10 ] );
		expect( getPaginationList( 10, 6 ) ).toEqual( [ 1, 2, '…', 5, 6, 7, '…', 9, 10 ] );
		expect( getPaginationList( 10, 8 ) ).toEqual( [ 1, 2, '…', 7, 8, 9, 10 ] );
		expect( getPaginationList( 10, 9 ) ).toEqual( [ 1, 2, '…', 8, 9, 10 ] );
		expect( getPaginationList( 10, 10 ) ).toEqual( [ 1, 2, '…', 9, 10 ] );
	} );

	it( 'should ignore out of bound current values', async () => {
		expect( getPaginationList( 4, 10 ) ).toEqual( [ 1, 2, 3, 4 ] );
		expect( getPaginationList( 10, 100 ) ).toEqual( [ 1, 2, '…', 9, 10 ] );
		expect( getPaginationList( 10, -2 ) ).toEqual( [ 1, 2, '…', 9, 10 ] );
	} );
} );
