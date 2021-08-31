/**
 * WordPress dependencies
 */
import { useCallback, useRef } from '@wordpress/element';
import { focus } from '@wordpress/dom';

/**
 * Hook used to focus the first tabbable element with a callback.
 *
 * @return {Array} Ref and callback.
 */
export default function useFocusOnNavigation() {
	const containerRef = useRef();

	const callback = useCallback( () => {
		if ( ! containerRef?.current ) {
			return;
		}

		const tabStops = focus.tabbable.find( containerRef.current );
		const target = tabStops[ tabStops.length - 1 ] || false;

		if ( target ) {
			target.focus();
		}

		target.focus();
	}, [] );

	return [ containerRef, callback ];
}
