/* global app_data:object */
import React from 'react';
import { render } from 'react-dom';
import { compose, createStore, applyMiddleware } from 'redux';
import { Provider, connect } from 'react-redux';
import thunkMiddleware from 'redux-thunk';
import { Router, useRouterHistory } from 'react-router';
import { syncHistoryWithStore } from 'react-router-redux';
import { createHistory } from 'history';
import throttle from 'lodash/throttle';

/**
 * Internal dependencies.
 */
import reducers from 'reducers';
import routes from 'routes';
import { loadState, saveState } from 'modules/local-storage';

// Add the reducer to your store on the `routing` key.
const store = compose(
	applyMiddleware( thunkMiddleware )
)( createStore )( reducers, loadState() );

// Save state to local storage when the store gets updated.
store.subscribe( throttle( () => {
	saveState( store.getState() );
}, 1000 ) );

const history = useRouterHistory( createHistory )( {
	basename: app_data.base
} );

render(
	<Provider store={ store }>
		<Router history={ syncHistoryWithStore( history, store ) } routes={ routes } />
	</Provider>,
	document.getElementById( 'content' )
);
