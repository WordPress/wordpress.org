/**
 * External dependencies.
 */
import React from 'react';
import { render } from 'react-dom';
import { Provider } from 'react-redux';
import { setLocale } from 'i18n-calypso';

/**
 * Internal dependencies.
 */
import Router from 'modules/router';
import getStore from 'modules/store';

//setLocale( localeData );

render(
	<Provider store={ getStore() }>
		{ Router }
	</Provider>,
	document.getElementById( 'content' )
);
