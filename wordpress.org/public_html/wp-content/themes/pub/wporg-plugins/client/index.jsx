import React from 'react';
import { render } from 'react-dom';
import { Provider } from 'react-redux';

import Router from 'modules/router';
import getStore from 'modules/store';

render(
	<Provider store={ getStore() }>
		{ Router }
	</Provider>,
	document.getElementById( 'content' )
);
