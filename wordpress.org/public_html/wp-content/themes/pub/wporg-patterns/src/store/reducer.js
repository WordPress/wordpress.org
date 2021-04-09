/**
 * WordPress dependencies
 */
import { combineReducers } from '@wordpress/data';

/**
 * Reducer to track available patterns.
 *
 * @param {Object} state Current state.
 * @param {Object} action Dispatched action.
 * @return {Object} Updated state.
 */
export function patterns( state = {}, action ) {
	return {
		byId: byId( state.byId, action ),
		queries: queries( state.queries, action ),
	};
}

function byId( state = {}, action ) {
	const patternsById = ( action.patterns || [] ).reduce( ( acc, cur ) => ( { ...acc, [ cur.id ]: cur } ), {} );
	switch ( action.type ) {
		case 'LOAD_BLOCK_PATTERNS':
			return { ...state, ...patternsById };
		default:
			return state;
	}
}

function queries( state = {}, action ) {
	const patternIds = ( action.patterns || [] ).map( ( { id } ) => id );
	switch ( action.type ) {
		case 'LOAD_BLOCK_PATTERNS':
			return { ...state, [ action.query ]: [ ...( state[ action.query ] || [] ), ...patternIds ] };
		default:
			return state;
	}
}

export default combineReducers( {
	patterns,
	// taxonomy items,
	// filter query,
	// favorites,
} );
