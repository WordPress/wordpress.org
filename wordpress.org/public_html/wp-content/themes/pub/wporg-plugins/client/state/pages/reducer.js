/**
 * External dependencies.
 */
import { find } from 'lodash';

/**
 * Internal dependencies.
 */
import { PAGE_RECEIVE } from 'state/action-types';

const pages = ( state = {}, action ) => {
	switch ( action.type ) {
		case PAGE_RECEIVE:
			const page = find( action.pages, { slug: action.slug } );
			if ( page ) {
				state = { ...state, [ page.slug ]: page };
			}
			break;
	}

	return state;
};

export default pages;
