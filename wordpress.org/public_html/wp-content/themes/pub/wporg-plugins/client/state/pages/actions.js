/**
 * Internal dependencies.
 */
import Api from 'modules/api';
import {
	PAGE_RECEIVE,
	PAGE_REQUEST,
	PAGE_REQUEST_SUCCESS,
	PAGE_REQUEST_FAILURE,
} from 'state/action-types';

export const fetchPage = ( slug ) => ( dispatch ) => {
	dispatch( {
		type: PAGE_REQUEST,
		slug,
	} );

	Api.pages().slug( slug )
		.then( ( pages ) => {
			dispatch( {
				type: PAGE_REQUEST_SUCCESS,
				slug,
			} );
			dispatch( {
				type: PAGE_RECEIVE,
				pages,
				slug,
			} );
		} )
		.catch( () => dispatch( {
			type: PAGE_REQUEST_FAILURE,
			slug,
		} ) );
};
