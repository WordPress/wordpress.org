/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

const DefaultMenu = ( { current, isLoading, label = __( 'Main Menu', 'wporg-patterns' ), onClick, options } ) => {
	if ( ! isLoading && ! options.length ) {
		return null;
	}

	return (
		<>
			<h2 className="screen-reader-text">{ label }</h2>
			<ul
				className={ classnames( {
					'pattern-menu': true,
					'is-loading': isLoading,
				} ) }
			>
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
		</>
	);
};

export default DefaultMenu;
