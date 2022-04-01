/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { store as patternStore } from '../../store';
import { getPageFromPath, removeQueryString } from '../../utils';
import { useRoute } from '../../hooks';

const DocumentTitleMonitor = () => {
	const { path } = useRoute();

	const title = useSelect( ( select ) => {
		const _query = select( patternStore ).getQueryFromUrl( path );
		const category = select( patternStore ).getCategoryById( _query[ 'pattern-categories' ] )?.name;
		const authorName = wporgPatternsData.currentAuthorName || _query?.author_name;
		const page = getPageFromPath( path );

		// Remove irrelvant content from the path: query string, site subdirectory.
		const contentPath = removeQueryString( path ).replace( /^\/patterns/, '' );
		// Get the first item after the `/`: categories, author, etc.
		const section = contentPath.split( '/' )[ 1 ] || '';
		const parts = [];

		if ( 'categories' === section && category ) {
			parts.push(
				/* translators: Taxonomy term name */
				sprintf( __( 'Block Patterns: %s', 'wporg-patterns' ), category )
			);
		} else if ( 'author' === section && authorName ) {
			parts.push(
				/* translators: Author name */
				sprintf( __( 'Block Patterns by %s', 'wporg-patterns' ), authorName )
			);
		} else {
			parts.push( __( 'Block Pattern Directory', 'wporg-patterns' ) );
		}

		if ( page > 1 ) {
			parts.push(
				/* translators: Page number */
				sprintf( __( 'Page %d', 'wporg-patterns' ), page )
			);
		}

		parts.push( __( 'WordPress.org', 'wporg-patterns' ) + ` ${ wporgLocale.displayName }` );
		return parts.join( ' | ' );
	} );

	useEffect( () => {
		if ( title ) {
			document.title = title;
		}
	}, [ title ] );

	return null;
};

export default DocumentTitleMonitor;
