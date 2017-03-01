/**
 * External dependencies.
 */
import { uniq, without } from 'lodash';

/**
 * Internal dependencies.
 */
import {
	FAVORITE_PLUGIN,
	GET_FAVORITES,
	UNFAVORITE_PLUGIN,
} from 'state/action-types';

const favorites = ( state = [], action ) => {
	switch ( action.type ) {
		case FAVORITE_PLUGIN:
		case GET_FAVORITES:
			state = uniq( [ ...state, action.plugin ] );
			break;

		case UNFAVORITE_PLUGIN:
			state = without( state, action.plugin );
			break;
	}

	return state;
};

export default favorites;
