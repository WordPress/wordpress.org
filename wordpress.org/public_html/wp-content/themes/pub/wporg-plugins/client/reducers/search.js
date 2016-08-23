
/**
 * Internal dependencies.
 */
import { SEARCH_PLUGINS } from 'actions/action-types';

const plugins = ( state = {}, action ) => { // jshint ignore:line

	switch ( action.type ) {
		case SEARCH_PLUGINS:
			state = Object.assign( {}, state, {
				[ action.searchTerm.toLowerCase() ] : action.plugins.map( plugin => ( plugin.slug ) )
			} );

			break;
	}

	return state;
};

export default plugins;
