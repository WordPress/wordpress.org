/**
 * External dependencies.
 */
import { combineReducers } from 'redux';
import { routerStateReducer } from 'redux-router';

/**
 * Internal dependencies.
 */
import browse from './browse/reducer';
import favorites from './favorites/reducer';
import pages from './pages/reducer';
import plugins from './plugins/reducer';
import search from './search/reducer';
import sections from './sections/reducer';
import user from './user/reducer';

export default combineReducers( {
	browse,
	favorites,
	pages,
	plugins,
	search,
	sections,
	router: routerStateReducer,
	user,
} );
