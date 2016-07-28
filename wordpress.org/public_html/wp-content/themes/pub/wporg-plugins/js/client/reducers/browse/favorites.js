import union from 'lodash/union';

/**
 * Internal dependencies.
 */
import { GET_BROWSE } from 'actions/action-types';

const favorites = ( state = [], action ) => { // jshint ignore:line

	switch ( action.type ) {
		case GET_BROWSE:
			if ( 'favorites' === action.term ) {
				state = union( state, action.plugins );
			}
			break;
	}

	return state;
};

export default favorites;
