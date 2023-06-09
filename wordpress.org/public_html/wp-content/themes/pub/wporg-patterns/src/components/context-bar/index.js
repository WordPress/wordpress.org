/**
 * External dependencies
 */
import classnames from 'classnames';
import useDeepCompareEffect from 'use-deep-compare-effect';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useRoute } from '../../hooks';
import { getLoadingMessage, getMessage, getPageLabel, getSearchMessage } from './messaging';
import { store as patternStore } from '../../store';

/**
 * Check if the query is just the "home" query, and doesn't need a message.
 *
 * Exclude the orderby & page properties; if there are no other query keys, this is a "home" query.
 *
 * @param {Object} query
 * @return {boolean}
 */
const isHomeQuery = ( query ) => {
	const allKeys = Object.keys( query || {} );
	// Filter out "orderby", "page", and "curation", which have no affect on what kind of query this is.
	const keys = allKeys.filter( ( key ) => ! [ 'orderby', 'page', 'curation' ].includes( key ) );
	return ! keys.length;
};

/**
 * Check if the query is a valid query.
 *
 * @param {Object} query
 * @return {boolean}
 */
const isQueryValid = ( query = {} ) => {
	// If there is an "include", it should have items.
	if ( query.hasOwnProperty( 'include' ) ) {
		return query.include.length > 0;
	}
	return true;
};

function ContextBar( props ) {
	const { path } = useRoute();
	const [ message, setMessage ] = useState();
	const [ context ] = useState( {
		title: '',
		links: [],
	} );

	const { author, category, count, isLoadingPatterns, pageLabel, query } = useSelect(
		( select ) => {
			const {
				getCategoryById,
				getPatternTotalsByQuery,
				getPatternTotalPagesByQuery,
				getQueryFromUrl,
				isLoadingPatternsByQuery,
			} = select( patternStore );
			const _query = { ...getQueryFromUrl( path ), ...props.query };
			const isLoading = isLoadingPatternsByQuery( _query );

			return {
				author: wporgPatternsData.currentAuthorName || _query?.author_name,
				category: getCategoryById( _query[ 'pattern-categories' ] ),
				count: getPatternTotalsByQuery( _query ),
				isLoadingPatterns: isLoading,
				pageLabel:
					_query && ! isLoading
						? getPageLabel( _query.page, getPatternTotalPagesByQuery( _query ) )
						: '',
				query: _query,
			};
		},
		[ path, props.query ]
	);

	useDeepCompareEffect( () => {
		// Show the loading message
		if ( isQueryValid( query ) && isLoadingPatterns ) {
			setMessage( getLoadingMessage( { category: category?.name, author: author } ) );
			return;
		}

		// We don't show a message when the query is empty.
		if ( isHomeQuery( query ) ) {
			setMessage( '' );
			return;
		}

		const searchTerm = query?.search || '';
		if ( searchTerm.length > 0 ) {
			setMessage( getSearchMessage( count, searchTerm ) );
			return;
		}

		setMessage( getMessage( { category: category?.name, author: author }, count ) );

		// Remove the context message from favorites.
		if ( query?.include && ! category ) {
			setMessage( '' );
		}
	}, [ query, isLoadingPatterns ] );

	const spinnerClassName = classnames( {
		'context-bar__spinner': true,
		'context-bar__spinner--is-hidden': ! isLoadingPatterns || props.isEmpty,
	} );

	return (
		<div className={ message ? null : 'screen-reader-text' }>
			<header className="context-bar" aria-live="polite" aria-atomic="true" tabIndex="0">
				<h2 className="context-bar__copy">
					<span className={ spinnerClassName }>
						<Spinner />
					</span>
					<span>{ message || __( 'All patterns.', 'wporg-patterns' ) }</span>
					{ pageLabel && <span className="screen-reader-text">{ pageLabel }</span> }
				</h2>
				{ context.links && context.links.length > 0 && (
					<div className="context-bar__links">
						<h3 className="context-bar__title">{ context.title }</h3>

						<ul>
							{ context.links.map( ( i ) => (
								<li key={ i.href }>
									<a href={ i.href }>{ i.label }</a>
								</li>
							) ) }
						</ul>
					</div>
				) }
			</header>
		</div>
	);
}

export default ContextBar;
