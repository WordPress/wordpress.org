import { combineReducers } from 'redux';
import { routerReducer } from 'react-router-redux';

/**
 * Internal dependencies.
 */
import browse from './browse/index';
import pages from './pages';
import plugins from './plugins';
import search from './search';

export default combineReducers( {
	browse,
	pages,
	plugins,
	search,
	routing: routerReducer
} );
