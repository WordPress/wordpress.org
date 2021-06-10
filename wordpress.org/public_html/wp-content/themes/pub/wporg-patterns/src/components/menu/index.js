/**
 * Internal dependencies
 */
import DefaultMenu from './default';
import MobileMenu from './mobile';

const Menu = ( props ) => {
	return (
		<>
			<DefaultMenu { ...props } />
			<MobileMenu { ...props } />
		</>
	);
};

export default Menu;
