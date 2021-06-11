/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { PanelBody } from '@wordpress/components';

const MobileMenu = ( { onClick, options, label = __( 'Browse categories', 'wporg-patterns' ) } ) => {
	const [ isOpen, setIsOpen ] = useState( false );

	return (
		<PanelBody
			className="pattern-menu is-mobile"
			title={ label }
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

export default MobileMenu;
