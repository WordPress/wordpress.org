/**
 * External dependencies.
 */
import { keyBy } from 'lodash';

/**
 * Internal dependencies.
 */
import {
	PLUGIN_RECEIVE,
	PLUGINS_RECEIVE,
} from 'state/action-types';

const plugins = ( state = {}, action ) => {
	switch ( action.type ) {
		case PLUGIN_RECEIVE:
			state = { ...state, [ action.plugin.slug ]: action.plugin };
			break;

		case PLUGINS_RECEIVE:
			state = { ...state, ...keyBy( action.plugins, 'slug' ) };
			break;
	}

	return state;
};

export default plugins;
