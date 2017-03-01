/**
 * External dependencies.
 */
import { combineReducers } from 'redux';
import { keyBy } from 'lodash';

/**
 * Internal dependencies.
 */
import {
	SECTIONS_RECEIVE,
	SECTIONS_REQUEST,
	SECTIONS_REQUEST_SUCCESS,
	SECTIONS_REQUEST_FAILURE,
} from 'state/action-types';

const isFetching = ( state = false, { type } ) => {
	switch ( type ) {
		case SECTIONS_REQUEST:
			state = true;
			break;

		case SECTIONS_REQUEST_SUCCESS:
		case SECTIONS_REQUEST_FAILURE:
			state = false;
			break;
	}

	return state;
};

const items = ( state = {}, { type, sections } ) => {
	switch ( type ) {
		case SECTIONS_RECEIVE:
			state = { ...state, ...keyBy( sections, 'slug' ) };
			break;
	}

	return state;
};

export default combineReducers( {
	isFetching,
	items,
} );
