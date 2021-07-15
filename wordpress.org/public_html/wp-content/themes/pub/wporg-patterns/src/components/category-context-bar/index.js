/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { Spinner } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useRoute } from '../../hooks';
import { getCategoryFromPath, getSearchTermFromPath } from '../../utils';
import { getDefaultMessage, getLoadingMessage, getSearchMessage } from './messaging';
import { store as patternStore } from '../../store';

function CategoryContextBar( { query } ) {
	const { path } = useRoute();
	const [ height, setHeight ] = useState();
	const [ message, setMessage ] = useState();
	const [ context ] = useState( {
		title: '',
		links: [],
	} );
	const innerRef = useRef( null );

	const { isAllCategory, category, count, isLoadingPatterns } = useSelect(
		( select ) => {
			const { getCategoryBySlug, isLoadingPatternsByQuery, getPatternTotalsByQuery } = select(
				patternStore
			);
			const categorySlug = getCategoryFromPath( path );
			const _category = getCategoryBySlug( categorySlug );

			return {
				isAllCategory: _category && _category.id === -1,
				isLoadingPatterns: isLoadingPatternsByQuery( query ),
				category: _category,
				count: getPatternTotalsByQuery( query ),
			};
		},
		[ path, query ]
	);

	useEffect( () => {
		// Show the loading message
		if ( isLoadingPatterns ) {
			if ( category ) {
				setMessage( getLoadingMessage( category.name ) );
			} else {
				setMessage( __( 'Loading patterns', 'wporg-patterns' ) );
			}

			return;
		}

		// We don't show a message when viewing all categories
		if ( isAllCategory ) {
			setMessage( '' );
			return;
		}

		if ( category ) {
			setMessage( getDefaultMessage( count, category.name ) );
		}

		const searchTerm = getSearchTermFromPath( path );
		if ( searchTerm.length > 0 ) {
			setMessage( getSearchMessage( count, searchTerm ) );
		}
	}, [ category, isLoadingPatterns ] );

	useEffect( () => {
		const _height = message ? innerRef.current.offsetHeight : 0;
		setHeight( _height );
	}, [ message ] );

	const classes = classnames( {
		'category-context-bar__spinner': true,
		'category-context-bar__spinner--is-hidden': ! isLoadingPatterns,
	} );

	return (
		<header className="category-context-bar" style={ { height: `${ height }px` } }>
			<div ref={ innerRef }>
				<h2 className="category-context-bar__copy">
					<span className={ classes }>
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
