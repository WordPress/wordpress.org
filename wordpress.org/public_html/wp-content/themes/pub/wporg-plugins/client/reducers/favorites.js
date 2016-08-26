import union from 'lodash/union';
import without from 'lodash/without';

import {
	FAVORITE_PLUGIN,
	GET_FAVORITES,
	UNFAVORITE_PLUGIN
} from 'actions/action-types';

const favorites = ( state = [], action ) => { // jshint ignore:line

	switch ( action.type ) {
		case FAVORITE_PLUGIN:
		case GET_FAVORITES:
			state = union( state, state.concat( [ action.plugin ] ) );
			break;

		case UNFAVORITE_PLUGIN:
			state = without( state, action.plugin );
			break;
	}

	return state;
};

export default favorites;
