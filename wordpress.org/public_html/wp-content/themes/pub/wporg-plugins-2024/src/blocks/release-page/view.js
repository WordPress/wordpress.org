/**
 * WordPress dependencies
 */
import { store } from '@wordpress/interactivity';

const { state } = store( 'wporg/publish-draft', {
	actions: {
		handlePreSubmit( event ) {
			event.preventDefault();
			state.isCreatingRelease = true;

			const element = document.querySelector(
				'.wp-block-wporg-release-page'
			);

			if ( element ) {
				element.scrollIntoView( {
					behavior: 'instant',
					block: 'center',
				} );
			}
		},
	},
} );
