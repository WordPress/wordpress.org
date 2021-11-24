/**
 * WordPress dependencies
 */
import { createContext, useContext, useEffect, useState } from '@wordpress/element';
import { addQueryArgs, getPathAndQueryString, getQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { removeEmptyArgs, removeQueryString } from '../utils';

const StateContext = createContext();

export function RouteProvider( { children } ) {
	const [ path, setPath ] = useState( getPathAndQueryString( window.location.href ) );

	/**
	 * Combines query strings from the current path and the new path for arguments with values.
	 *
	 * @param {string} newPath Path including query strings
	 * @return {Object} Query strings as an object
	 */
	const mergeQueryStrings = ( newPath ) => {
		const currentQueryStrings = getQueryArgs( path );
		const newQueryStrings = getQueryArgs( newPath );

		const combined = { ...currentQueryStrings, ...newQueryStrings };

		// remove empty query strings
		return removeEmptyArgs( combined );
	};

	/**
	 * Combines the current and new path's query strings and updates the browser's url.
	 *
	 * @param {string} newPath
	 */
	const _pushState = ( newPath ) => {
		// Merge the existing and new query strings.
		const newQueryStrings = mergeQueryStrings( newPath );

		// Remove the query strings from the path
		const pathOnly = removeQueryString( newPath );

		// Rebuild the full path
		const rebuiltPath = addQueryArgs( pathOnly, newQueryStrings );

		_replaceState( rebuiltPath );
	};

	/**
	 * Calls `window.history.pushState` to update the browser's url.
	 *
	 * @param {string} newPath
	 */
	const _replaceState = ( newPath ) => {
		window.history.pushState( '', '', newPath );
		setPath( newPath );
	};

	/**
	 * Updates the path to the current browser path.
	 */
	const setPathOnPop = () => {
		setPath( document.location.href );
	};

	useEffect( () => {
		// When the browser modifies the history, update our path.
		window.addEventListener( 'popstate', setPathOnPop );

		return () => {
			window.removeEventListener( 'popstate', setPathOnPop );
		};
	}, [] );

	return (
		<StateContext.Provider
			value={ {
				path: path,
				update: _pushState,
				replace: _replaceState,
			} }
		>
			{ children }
		</StateContext.Provider>
	);
}

export function useRoute() {
	const context = useContext( StateContext );

	if ( context === undefined ) {
		throw new Error( 'useRoute must be used within a Provider' );
	}

	return context;
}
