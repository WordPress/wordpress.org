import { combineReducers } from 'redux';
import { routerReducer } from 'react-router-redux';
/**
 * Internal dependencies.
 */
import pages from './pages';

export default combineReducers( {
	pages,
	routing: routerReducer
} );
