/**
 * External dependencies.
 */
import { combineReducers } from 'redux';
import union from 'lodash/union';

/**
 * Internal dependencies.
 */
import { BROWSE_RECEIVE } from 'state/action-types';

const beta = ( state = [], action ) => { // jshint ignore:line
	switch ( action.type ) {
		case BROWSE_RECEIVE:
			if ( 'beta' === action.term ) {
				state = union( state, action.plugins );
			}
			break;
	}

	return state;
};

const favorites = ( state = [], action ) => { // jshint ignore:line
	switch ( action.type ) {
		case BROWSE_RECEIVE:
			if ( 'favorites' === action.term ) {
				state = union( state, action.plugins );
			}
			break;
	}

	return state;
};

const featured = ( state = [], action ) => { // jshint ignore:line
	switch ( action.type ) {
		case BROWSE_RECEIVE:
			if ( 'featured' === action.term ) {
				state = union( state, action.plugins );
			}
			break;
	}

	return state;
};

const popular = ( state = [], action ) => { // jshint ignore:line
	switch ( action.type ) {
		case BROWSE_RECEIVE:
			if ( 'popular' === action.term ) {
				state = union( state, action.plugins );
			}
			break;
	}

	return state;
};

export default combineReducers( {
	beta,
	favorites,
	featured,
	popular,
} );
