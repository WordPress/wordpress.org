import React from 'react';
import { render } from 'react-dom';
import { compose, createStore, applyMiddleware } from 'redux';
import { Provider, connect } from 'react-redux';
import thunkMiddleware from 'redux-thunk';
import { Router, useRouterHistory } from 'react-router';
import { syncHistoryWithStore } from 'react-router-redux';
import { createHistory } from 'history';

/**
 * Internal dependencies.
 */
import reducers from 'reducers';
import routes from 'routes';

// Add the reducer to your store on the `routing` key.
const store = compose(
	applyMiddleware( thunkMiddleware )
)( createStore )( reducers );

const history = useRouterHistory( createHistory )( {
	basename: "/plugins"
} );

render(
	<Provider store={ store }>
		<Router history={ syncHistoryWithStore( history, store ) } routes={ routes } />
	</Provider>,
	document.getElementById( 'content' )
);
