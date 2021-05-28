/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { ifViewportMatches } from '@wordpress/viewport';

const DefaultMenu = ( { currentCategory, options, onClick, isLoading } ) => {
	if ( ! isLoading && ! options.length ) {
		return null;
	}

	return (
		<>
			<h2 className="screen-reader-text">{ __( 'Main Menu', 'wporg-patterns' ) }</h2>
			<ul className={ classnames( { 'category-menu': true, 'category-menu--is-loading': isLoading } ) }>
				{ options.map( ( i ) => (
					<li key={ i.value }>
						<a
							className={ classnames( { 'category-menu--is-active': currentCategory === i.slug } ) }
							href={ i.value }
							onClick={ onClick }
							aria-current={ currentCategory === i.slug ? 'page' : undefined }
						>
							{ i.label }
						</a>
					</li>
				) ) }
			</ul>
		</>
	);
};

// Will only render if the viewport is >= medium
export default ifViewportMatches( '>= medium' )( DefaultMenu );
