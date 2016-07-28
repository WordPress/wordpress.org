import { combineReducers } from 'redux';
import { routerReducer } from 'react-router-redux';

/**
 * Internal dependencies.
 */
import browse from './browse/index';
import pages from './pages';
import plugins from './plugins';

export default combineReducers( {
	browse,
	pages,
	plugins,
	routing: routerReducer
} );
