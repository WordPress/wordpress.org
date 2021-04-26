/**
 * WordPress dependencies
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { getQueryArg } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { useRoute } from '../../hooks';
import { getCategoryFromPath } from '../../utils';
import { getContextMessage } from './messaging';
import { store as patternStore } from '../../store';

function CategoryContextBar() {
	const { path } = useRoute();
	const [ height, setHeight ] = useState( 0 );
	const [ context, setContext ] = useState( {} );
	const innerRef = useRef( null );

	const { isAllCategory, category, isLoadingPatterns, patterns } = useSelect(
		( select ) => {
			const { getCategoryBySlug, getPatternsByQuery, isLoadingPatternsByQuery, getCurrentQuery } = select(
				patternStore
			);
			const categorySlug = getCategoryFromPath( path );
			const _category = getCategoryBySlug( categorySlug );
			const query = getCurrentQuery();

			return {
				isAllCategory: _category && _category.id === -1,
				isLoadingPatterns: isLoadingPatternsByQuery( query ),
				patterns: query ? getPatternsByQuery( query ) : [],
				category: _category,
			};
		},
		[ path ]
	);

	useEffect( () => {
		if ( ! category || isLoadingPatterns ) {
			return;
		}

		const searchTerm = getQueryArg( path, 'search' );

		let _context = {};

		// Use the category count as default since it has the count of all the associated patterns
		let count = category.count;

		// If we have a search term use the pattern results length.
		// Not: This is okay until we start using paging.
		if ( searchTerm ) {
			count = patterns.length;
		}

		if ( ! isAllCategory || searchTerm ) {
			_context = getContextMessage( count, category.name, searchTerm );
		}

		setContext( _context );
	}, [ category, isLoadingPatterns, patterns ] );

	useEffect( () => {
		const _height = context.message ? innerRef.current.offsetHeight : 0;
		setHeight( _height );
	}, [ context.message ] );

	return (
		<header className="category-context__bar" style={ { height: `${ height }px` } }>
			<div ref={ innerRef }>
				<h2 className="category-context__bar__copy">{ context.message }</h2>
				{ context.links && context.links.length > 0 && (
					<div className="category-context__bar__links">
						<h3 className="category-context__bar__title">{ context.title }</h3>

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

export default CategoryContextBar;
