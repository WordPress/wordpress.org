/**
 * Internal dependencies
 */
import DefaultMenu from './default';
import MobileMenu from './mobile';

const CategoryMenu = ( props ) => {
	return (
		<>
			<DefaultMenu { ...props } />
			<MobileMenu { ...props } />
		</>
	);
};

export default CategoryMenu;
