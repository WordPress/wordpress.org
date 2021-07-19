/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import MenuSkeleton from './menu-skeleton';

const DefaultMenu = ( { current, isLoading, label = __( 'Main Menu', 'wporg-patterns' ), onClick, options } ) => {
	if ( isLoading ) {
		return <MenuSkeleton />;
	}

	if ( ! options.length ) {
		return null;
	}

	return (
		<nav>
			<h2 className="screen-reader-text">{ label }</h2>
			<ul className="pattern-menu">
				{ options.map( ( i ) => (
					<li key={ i.value }>
						<a
							className={ classnames( {
								'pattern-menu__item': true,
								'is-active': current === i.slug,
							} ) }
							href={ i.value }
							onClick={ onClick }
							aria-current={ current === i.slug ? 'page' : undefined }
						>
							{ i.label }
						</a>
					</li>
				) ) }
			</ul>
		</nav>
	);
};

export default DefaultMenu;
