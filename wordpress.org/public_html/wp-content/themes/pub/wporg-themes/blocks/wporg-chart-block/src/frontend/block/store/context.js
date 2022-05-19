/**
 * WordPress dependencies
 */
import { createContext, useContext, useState } from '@wordpress/element';

const StateContext = createContext();

const getDate = ( dateObj, subtract = 0 ) => {
	const month = dateObj.getUTCMonth() + 1;
	const paddedMonth = month < 10 ? `0${ month }` : month;
	const year = dateObj.getUTCFullYear() - subtract;

	return year + '-' + paddedMonth;
};

export function AppContext( { children } ) {
	const initDate = getDate( new Date(), 2 ); // Start 2 years back
	const [ startDate, setStartDate ] = useState( initDate );

	return (
		<StateContext.Provider
			value={ {
				startDate,
				setStartDate,
			} }
		>
			{ children }
		</StateContext.Provider>
	);
}

export function useAppContext() {
	const context = useContext( StateContext );

	if ( context === undefined ) {
		throw new Error( 'useAppContext must be used within a Provider' );
	}

	return context;
}
