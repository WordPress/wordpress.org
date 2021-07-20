/**
 * External dependencies
 */
import classnames from 'classnames';
import useDeepCompareEffect from 'use-deep-compare-effect';

/**
 * WordPress dependencies
 */
import { Spinner } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { useRoute } from '../../hooks';
import { getLoadingMessage, getMessage, getSearchMessage } from './messaging';
import { store as patternStore } from '../../store';

function ContextBar( props ) {
	const { path } = useRoute();
	const [ height, setHeight ] = useState();
	const [ message, setMessage ] = useState();
	const [ context ] = useState( {
		title: '',
		links: [],
	} );
	const innerRef = useRef( null );

	const { author, category, count, isLoadingPatterns, query } = useSelect(
		( select ) => {
			const { getCategoryById, getPatternTotalsByQuery, getQueryFromUrl, isLoadingPatternsByQuery } = select(
				patternStore
			);
			const _query = { ...getQueryFromUrl( path ), ...props.query };

			return {
				author: _query?.author_name,
				category: getCategoryById( _query[ 'pattern-categories' ] ),
				count: getPatternTotalsByQuery( _query ),
				isLoadingPatterns: isLoadingPatternsByQuery( _query ),
				query: _query,
			};
		},
		[ path, props.query ]
	);

	useDeepCompareEffect( () => {
		// Show the loading message
		if ( isLoadingPatterns ) {
			setMessage( getLoadingMessage( { category: category?.name, author: author } ) );
			return;
		}

		// We don't show a message when the query is empty.
		if ( query && ! Object.keys( query ).length ) {
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

	useEffect( () => {
		const _height = message ? innerRef.current.offsetHeight : 0;
		setHeight( _height );
	}, [ message ] );

	const classes = classnames( {
		'context-bar__spinner': true,
		'context-bar__spinner--is-hidden': ! isLoadingPatterns,
	} );

	return (
		<header className="context-bar" style={ { height: `${ height }px` } }>
			<div ref={ innerRef }>
				<h2 className="context-bar__copy">
					<span className={ classes }>
						<Spinner />
					</span>
					<span>{ message }</span>
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
			</div>
		</header>
	);
}

export default ContextBar;
