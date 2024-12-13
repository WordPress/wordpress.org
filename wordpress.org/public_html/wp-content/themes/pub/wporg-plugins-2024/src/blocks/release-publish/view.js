/**
 * WordPress dependencies
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

const { state } = store( 'wporg/publish-draft', {
	state: {
		get userHasConfirmed() {
			return state.hasConfirmed;
		},
		get isDefaultState() {
			return ! state.isPublishing && ! state.isPublished;
		},
		get isPublishingState() {
			return state.isPublishing;
		},
		get isPublishedState() {
			return state.isPublished;
		},
	},
	actions: {
		handleReleaseConfirm() {
			state.hasConfirmed = ! state.hasConfirmed;
		},
		handleBackClick( event ) {
			event.preventDefault();
			state.isCreatingRelease = false;

			// Make user reconfirm.
			state.hasConfirmed = false;
		},
		handlePageReload() {
			window.location.reload();
		},
		*handleSubmit( event ) {
			event.preventDefault();

			const { pluginSlug, nonce, apiURL, genericErrorMessage, tooltipMessage } = getContext();
			
			// Replicate form validation.
			if ( ! state.hasConfirmed ) {
				const input = document.getElementById( 'confirm-release' );

				input.setCustomValidity( tooltipMessage );
				input.reportValidity();

				return false;
			}

			state.isPublishing = true;
			state.errorMessage = '';
			state.hasError = false;

			try {
				const response = yield fetch( apiURL, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce,
					},
					body: JSON.stringify( {
						plugin_slug: pluginSlug,
					} ),
				} );

				if ( ! response.ok ) {
					try {
						const error = yield response.json();
						throw new Error( error.message );
					} catch ( error ) {
						if ( error instanceof SyntaxError ) {
							// Handle cases where json is not returned, like a gateway timeout.
							throw new Error( genericErrorMessage );
						}
						throw error;
					}
				}

				state.isPublished = true;
			} catch ( error ) {
				state.errorMessage = error.message;
				state.hasError = true;
				state.isPublishing = false;
				state.hasConfirmed = false;
			} finally {
				state.isPublishing = false;
			}
		},
	},
} );
