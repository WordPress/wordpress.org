/**
 * Internal dependencies.
 */
import { getPath, getSearchResults } from 'state/selectors';
import { SEARCH_RECEIVE } from 'state/action-types';

export const bodyClass = ( { getState } ) => ( next ) => ( action ) => {
	const prevState = getState();
	const nextAction = next( action );
	const nextState = getState();
	const nextPath = getPath( nextState );

	if (
		getPath( prevState ) === nextPath &&
		SEARCH_RECEIVE !== action.type
	) {
		return nextAction;
	}

	/**
	 * [0] => search, taxonomy, single slug
	 * [1] => term
	 * [2] => username (in /browse/favorites/)
	 */
	const parts = nextPath.split( '/' );
	const classes = [ 'js' ];

	if ( pluginDirectory.userId > 0 ) {
		classes.push( 'logged-in admin-bar' );
	}

	switch ( parts[ 0 ] ) {
		case '':
			classes.push( 'home' );
			break;

		case 'search':
			classes.push( 'search' );

			if ( ! getSearchResults( nextState ).length ) {
				classes.push( 'search-no-results' );
			}
			break;

		case 'author':
		case 'browse':
		case 'category':
		case 'tag':
			classes.push( 'archive' );
			classes.push( 'term-' + parts[ 1 ] );
			break;

		case 'developers':
			classes.push( 'single single-page' );
			break;

		default:
			classes.push( 'single single-plugin' );
			break;
	}

	window.document.body.className = classes.join( ' ' );

	return nextAction;
};

export default bodyClass;
