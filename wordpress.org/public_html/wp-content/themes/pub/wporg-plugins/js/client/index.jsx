import React from 'react';
import { render } from 'react-dom';
import { Provider } from 'react-redux';

/**
 * Internal dependencies.
 */
import Router from 'router';
import getStore from 'store';

render(
	<Provider store={ getStore() }>
		{ Router }
	</Provider>,
	document.getElementById( 'content' )
);
