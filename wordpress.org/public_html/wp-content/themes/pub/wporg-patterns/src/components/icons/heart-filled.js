/**
 * WordPress dependencies
 */
import { Path, SVG } from '@wordpress/primitives';

export default function ( props ) {
	return (
		<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" { ...props }>
			<Path d="M11.941 21.175l-1.443-1.32c-5.124-4.67-8.508-7.75-8.508-11.53 0-3.08 2.408-5.5 5.473-5.5 1.732 0 3.394.81 4.478 2.09 1.085-1.28 2.747-2.09 4.478-2.09 3.065 0 5.473 2.42 5.473 5.5 0 3.78-3.383 6.86-8.508 11.54l-1.443 1.31z" />
		</SVG>
	);
}
