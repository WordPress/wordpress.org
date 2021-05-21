/**
 * WordPress dependencies
 */
import { Spinner } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { getQueryArg } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { useRoute } from '../../hooks';
import { getCategoryFromPath } from '../../utils';
import { getAllSearchMessage, getDefaultMessage, getDefaultSearchMessage, getLoadingMessage } from './messaging';
import { store as patternStore } from '../../store';

function CategoryContextBar() {
	const { path } = useRoute();
	const [ height, setHeight ] = useState( 0 );
	const [ message, setMessage ] = useState();
	const [ context ] = useState( {
		title: '',
		links: [],
	} );
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
		if ( ! category ) {
			return;
		}

		if ( isLoadingPatterns ) {
			setMessage( getLoadingMessage( category.name ) );
			return;
		}

		const searchTerm = getQueryArg( path, 'search' );

		if ( searchTerm && ! isAllCategory ) {
			setMessage( getDefaultSearchMessage( patterns.length, category.name, searchTerm ) );
		} else if ( searchTerm && isAllCategory ) {
			setMessage( getAllSearchMessage( patterns.length, searchTerm ) );
		} else if ( ! isAllCategory ) {
			setMessage( getDefaultMessage( category.count || 0, category.name ) );
		} else {
			setMessage( '' );
		}
	}, [ category, isLoadingPatterns, patterns ] );

	useEffect( () => {
		const _height = message ? innerRef.current.offsetHeight : 0;
		setHeight( _height );
	}, [ message ] );

	return (
		<header className="category-context-bar" style={ { height: `${ height }px` } }>
			<div ref={ innerRef }>
				<h2 className="category-context-bar__copy">
					<span
						className={ `category-context-bar__spinner ${
							! isLoadingPatterns ? 'category-context-bar__spinner--is-hidden' : ''
						}` }
					>
						<Spinner />
					</span>
					<span>{ message }</span>
				</h2>
				{ context.links && context.links.length > 0 && (
					<div className="category-context-bar__links">
						<h3 className="category-context-bar__title">{ context.title }</h3>

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
