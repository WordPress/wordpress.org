/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { ifViewportMatches } from '@wordpress/viewport';
import { PanelBody } from '@wordpress/components';

const MobileMenu = ( { onClick, options } ) => {
	const [ isOpen, setIsOpen ] = useState( false );

	return (
		<PanelBody
			className="category-menu__mobile"
			title={ __( 'Browse categories', 'wporg-patterns' ) }
			initialOpen={ isOpen }
			opened={ isOpen }
			onToggle={ () => setIsOpen( ! isOpen ) }
		>
			<ul>
				{ options.map( ( i ) => (
					<li key={ i.value }>
						<a
							href={ i.value }
							onClick={ ( event ) => {
								setIsOpen( false );
								onClick( event );
							} }
						>
							{ i.label }
						</a>
					</li>
				) ) }
			</ul>
		</PanelBody>
	);
};

// Will only render if the viewport is < medium
export default ifViewportMatches( '< medium' )( MobileMenu );
