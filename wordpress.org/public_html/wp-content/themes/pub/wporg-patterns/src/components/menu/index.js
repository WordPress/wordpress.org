/**
 * WordPress dependencies
 */
import { useViewportMatch } from '@wordpress/compose';

/**
 * Internal dependencies
 */
import DefaultMenu from './default';
import MobileMenu from './mobile';

const Menu = ( props ) => {
	const isMobile = useViewportMatch( 'medium', '<' );
	return isMobile ? <MobileMenu { ...props } /> : <DefaultMenu { ...props } />;
};

export default Menu;
