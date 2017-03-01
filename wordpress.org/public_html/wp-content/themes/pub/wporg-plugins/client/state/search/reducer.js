
/**
 * Internal dependencies.
 */
import { SEARCH_RECEIVE } from 'state/action-types';

const search = ( state = {}, action ) => { // jshint ignore:line
	switch ( action.type ) {
		case SEARCH_RECEIVE:
			state = { ...state,
				[ action.search.toLowerCase() ]: action.plugins.map( ( plugin ) => plugin.slug ),
			};
			break;
	}

	return state;
};

export default search;
