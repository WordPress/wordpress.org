/**
 * External dependencies
 */
import { ifViewportMatches } from '@wordpress/viewport';

const DefaultMenu = ( { path, options, onClick, isLoading } ) => {
	if ( ! isLoading && ! options.length ) {
		return null;
	}

	return (
		<ul className={ `category-menu ${ isLoading ? 'category-menu--is-loading' : '' } ` }>
			{ options.map( ( i ) => (
				<li key={ i.value }>
					<a
						className={ path === i.value ? 'category-menu--is-active' : '' }
						href={ i.value }
						onClick={ onClick }
					>
						{ i.label }
					</a>
				</li>
			) ) }
		</ul>
	);
};

// Will only render if the viewport is >= medium
export default ifViewportMatches( '>= medium' )( DefaultMenu );
