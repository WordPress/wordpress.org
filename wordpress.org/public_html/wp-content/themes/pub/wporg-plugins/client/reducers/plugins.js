import union from 'lodash/union';

/**
 * Internal dependencies.
 */
import { GET_PLUGIN } from 'actions/action-types';

const plugins = ( state = [], action ) => { // jshint ignore:line

	switch ( action.type ) {
		case GET_PLUGIN:
			state = union( state, state.concat( [ action.plugin ] ) );
			break;
	}

	return state;
};

export default plugins;
