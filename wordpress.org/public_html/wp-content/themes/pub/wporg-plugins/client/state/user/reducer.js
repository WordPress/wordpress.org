/**
 * External dependencies.
 */
import { combineReducers } from 'redux';
import { keyBy } from 'lodash';

/**
 * Internal dependencies.
 */
import {
	USER_RECEIVE,
	USER_REQUEST,
	USER_REQUEST_SUCCESS,
	USER_REQUEST_FAILURE,
} from 'state/action-types';

const isFetching = ( state = {}, { type, id } ) => {
	switch ( type ) {
		case USER_REQUEST:
			state = { ...state, [ id ]: true };
			break;

		case USER_REQUEST_SUCCESS:
		case USER_REQUEST_FAILURE:
			state = { ...state, [ id ]: false };
			break;
	}

	return state;
};

const items = ( state = {}, { type, user } ) => {
	switch ( type ) {
		case USER_RECEIVE:
			state = { ...state, [ user.id ]: user };
			break;
	}

	return state;
};

export default combineReducers( {
	isFetching,
	items,
} );
