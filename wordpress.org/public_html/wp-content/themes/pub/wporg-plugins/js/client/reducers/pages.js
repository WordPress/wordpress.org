import find from 'lodash/find';

/**
 * Internal dependencies.
 */
import { GET_PAGE } from 'actions/action-types';

const pages = ( state = [], action ) => { // jshint ignore:line

	switch ( action.type ) {
		case GET_PAGE:
			if ( ! find( state, { id: action.page.id } ) ) {
				state = state.concat( [ action.page ] );
			}
			break;
	}

	return state;
};

export default pages;
