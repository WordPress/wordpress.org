import { compose, createStore, applyMiddleware } from 'redux';
import thunk from 'redux-thunk';
import throttle from 'lodash/throttle';

import reducers from 'reducers';
import { loadState, saveState } from 'modules/local-storage';

const getStore = () => {
	const store = compose(
		applyMiddleware( thunk )
	)( createStore )( reducers, loadState() );

	// Save state to local storage when the store gets updated.
	store.subscribe( throttle( () => {
		saveState( store.getState() );
	}, 1000 ) );

	return store;
};

export default getStore;
