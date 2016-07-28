import union from 'lodash/union';

/**
 * Internal dependencies.
 */
import { GET_BROWSE } from 'actions/action-types';

const beta = ( state = [], action ) => { // jshint ignore:line

	switch ( action.type ) {
		case GET_BROWSE:
			if ( 'beta' === action.term ) {
				state = union( state, action.plugins );
			}
			break;
	}

	return state;
};

export default beta;
