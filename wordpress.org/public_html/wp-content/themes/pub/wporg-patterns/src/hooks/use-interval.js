/**
 * WordPress dependencies
 */
import { useEffect, useRef } from '@wordpress/element';

/**
 * Thanks! https://overreacted.io/making-setinterval-declarative-with-react-hooks/.
 *
 * @param {Function} callback
 * @param {number}   delay
 */
export default function ( callback, delay ) {
	const savedCallback = useRef();

	useEffect( () => {
		savedCallback.current = callback;
	}, [ callback ] );

	useEffect( () => {
		function tick() {
			savedCallback.current();
		}
		if ( delay !== null ) {
			const id = setInterval( tick, delay );
			return () => clearInterval( id );
		}
	}, [ delay ] );
}
