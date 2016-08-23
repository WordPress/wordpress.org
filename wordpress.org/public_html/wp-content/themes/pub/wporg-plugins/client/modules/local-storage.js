const storageKey = 'wporg-plugins-state';

export const loadState = () => {
	let state;

	try {
		const serializedState = localStorage.getItem( storageKey );

		if ( null !== serializedState ) {
			state = JSON.parse( serializedState );
		}
	} catch ( error ) {}

	return state;
};

export const saveState = ( state ) => {
	try {
		const serializedState = JSON.stringify( state );
		localStorage.setItem( storageKey, serializedState );
	} catch( error ) {}
};
