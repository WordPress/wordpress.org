/**
 * External dependencies
 */
import { debounce } from 'lodash';

/**
 * WordPress dependencies
 */
import { useEffect, useState } from '@wordpress/element';

const useInView = ( { element } ) => {
	const [ visible, setVisible ] = useState( null );

	useEffect( () => {
		if ( ! element.current ) {
			return;
		}

		const debouncedIsVisible = debounce( isVisible, 200 );

		// Initialize `isVisible`.
		isVisible();

		window.addEventListener( 'scroll', debouncedIsVisible );
		window.addEventListener( 'resize', debouncedIsVisible );

		return () => {
			window.removeEventListener( 'scroll', debouncedIsVisible );
			window.removeEventListener( 'resize', debouncedIsVisible );
		};
	}, [ element ] );

	const isVisible = () => {
		if ( ! element.current ) {
			return;
		}
		const windowHeight = window.innerHeight;
		const { top } = element.current.getBoundingClientRect();

		if ( top >= 0 && top <= windowHeight ) {
			setVisible( true );
		} else {
			setVisible( false );
		}
	};

	return visible;
};

export default useInView;
