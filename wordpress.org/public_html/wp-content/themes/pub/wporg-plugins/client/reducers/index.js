import { combineReducers } from 'redux';
import { routerReducer } from 'react-router-redux';

import browse from './browse/index';
import favorites from './favorites';
import pages from './pages';
import plugins from './plugins';
import search from './search';

export default combineReducers( {
	browse,
	favorites,
	pages,
	plugins,
	search,
	routing: routerReducer
} );
