/**
 * Internal dependencies.
 */
import Api from 'modules/api';
import {
	SEARCH_RECEIVE,
	SEARCH_REQUEST,
	SEARCH_REQUEST_SUCCESS,
	SEARCH_REQUEST_FAILURE,
} from 'state/action-types';

export const fetchSearch = ( search ) => ( dispatch ) => {
	dispatch( {
		type: SEARCH_REQUEST,
		search,
	} );

	Api.plugin()
		.then( ( plugins ) => {
			dispatch( {
				type: SEARCH_REQUEST_SUCCESS,
				search,
			} );
			dispatch( {
				type: SEARCH_RECEIVE,
				plugins,
				search,
			} );
		} )
		.catch( () => dispatch( {
			type: SEARCH_REQUEST_FAILURE,
			search,
		} ) );
};
