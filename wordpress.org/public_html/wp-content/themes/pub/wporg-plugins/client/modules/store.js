/**
 * External dependencies.
 */
import { compose, createStore, applyMiddleware } from 'redux';
import createBrowserHistory from 'history/lib/createBrowserHistory';
import { reduxReactRouter } from 'redux-router';
import thunk from 'redux-thunk';
import { throttle } from 'lodash';
import { useRouterHistory } from 'react-router';
import { values } from 'lodash';

/**
 * Internal dependencies.
 */
import { fetchUser } from 'state/user/actions';
import { loadState, saveState } from 'modules/local-storage';
import * as middlewares from './middlewares';
import reducers from 'state/reducers';
import { routes } from './router';

const history = useRouterHistory( createBrowserHistory )( {
	/** @type {object} pluginDirectory Description */
	basename: pluginDirectory.base,
} );

const getStore = () => {
	const composeDev = ( 'production' !== process.env.NODE_ENV && window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__ );
	const composeEnhancers = composeDev || compose;
	const store = createStore( reducers, loadState(), composeEnhancers(
		reduxReactRouter( { routes, history } ),
		applyMiddleware( thunk, ...values( middlewares ) ),
	) );

	// Save state to local storage when the store gets updated.
	store.subscribe( throttle( () => {
		saveState( store.getState() );
	}, 1000 ) );

	// Set up user object.
	if ( '0' !== pluginDirectory.userId ) {
		store.dispatch( fetchUser( pluginDirectory.userId ) );
	}

	return store;
};

export default getStore;
