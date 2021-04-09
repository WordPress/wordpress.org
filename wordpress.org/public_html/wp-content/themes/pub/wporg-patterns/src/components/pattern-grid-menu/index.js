/**
 * External dependencies
 */
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { getPath } from '@wordpress/url';

/**
 * Internal dependencies
 */
import CategoryMenu from '../category-menu';
import CategorySearch from '../category-search';
import CategoryContextBar from '../category-context-bar';
import contextMessaging from './messaging';

/**
 * Module constants
 */
const PATTERN_TAXONOMY = 'wporg-pattern-category';

const PatternGridMenu = () => {
	// Show loading state
	const [ showLoading, setShowLoading ] = useState( true );
	const [ isFetching, setIsFetching ] = useState( false );
	const [ path, setPath ] = useState();
	const [ categoryContext, setCategoryContext ] = useState( undefined );

	const categories = useSelect( ( select ) =>
		select( coreStore ).getEntityRecords( 'taxonomy', PATTERN_TAXONOMY )
	);

	useEffect( () => {
		const pathOnLoad = getPath( window.location.href );
		setPath( pathOnLoad );
	}, [] );

	useEffect( () => {
		// Since categories starts as an [] then switches to null
		if ( showLoading && categories === null ) {
			setIsFetching( true );
		}

		if ( isFetching && Array.isArray( categories ) ) {
			setShowLoading( false );
			setIsFetching( false );
		}
	}, [ isFetching, categories ] );

	useEffect( () => {
		setCategoryContext( contextMessaging[ path ] );
	}, [ path ] );

	return (
		<>
			<nav className="pattern-grid-menu">
				<CategoryMenu
					path={ path }
					options={
						categories
							? categories.map( ( record ) => {
								return {
									// TODO: This url is temporary and won't use the # symbol
									value: `#/pattern-categories/${ record.slug }`,
									label: record.name,
								};
							} )
							: []
					}
					onClick={ ( _path ) => setPath( _path ) }
					isLoading={ showLoading }
				/>
				<CategorySearch isLoading={ showLoading } />
			</nav>
			<CategoryContextBar { ...categoryContext } />
		</>
	);
};

export default PatternGridMenu;
