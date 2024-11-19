import { store } from '@wordpress/interactivity'

store( 'wporg-release-menu-options', {
	actions: {
		handleFocusOut: (event) => {
			const details = event.target.closest( 'details' );

			if ( details && !details.contains( event.relatedTarget ) ) {
				details.removeAttribute( 'open' );
			}
		}
	}
});