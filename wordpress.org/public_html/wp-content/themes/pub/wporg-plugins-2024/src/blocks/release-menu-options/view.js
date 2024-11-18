import { store } from '@wordpress/interactivity'

store( 'wporg-release-menu-options', {
	actions: {
		handleMenuItemClick: ( event ) => {
			const itemId = event.target.dataset.itemId;

			switch ( itemId ) {
				case 'download':
					alert( 'download: not implemented');
					break;

				case 'playground':
					alert( 'playground: not implemented' );
					break;
			}
		},
		
		handleFocusOut: (event) => {
			const details = event.target.closest( 'details' );

			if ( details && !details.contains( event.relatedTarget ) ) {
				details.removeAttribute( 'open' );
			}
		}
	}
});