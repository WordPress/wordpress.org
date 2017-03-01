/**
 * Internal dependencies.
 */
import Api from 'modules/api';
import {
	USER_RECEIVE,
	USER_REQUEST,
	USER_REQUEST_SUCCESS,
	USER_REQUEST_FAILURE,
} from 'state/action-types';

export const fetchUser = ( id ) => ( dispatch ) => {
	dispatch( {
		type: USER_REQUEST,
		id,
	} );

	Api.users().id( id )
		.then( ( user ) => {
			dispatch( {
				type: USER_REQUEST_SUCCESS,
				id,
			} );
			dispatch( {
				type: USER_RECEIVE,
				user,
			} );
		} )
		.catch( ( error ) => dispatch( {
			type: USER_REQUEST_FAILURE,
			error,
			id,
		} ) );
};
