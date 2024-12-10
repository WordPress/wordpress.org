/**
 * WordPress dependencies
 */
import { store, getContext } from '@wordpress/interactivity';

const { state } = store('async-action-block', {
	state: {
		get btnText() {
			const { btnDefaultText, btnLoadingText } = getContext();

			return state.isWaiting ? btnLoadingText : btnDefaultText;
		},
	},
	actions: {
		handlePreSubmit(event) {
			event.preventDefault();
			state.preSubmitting = true;

			const element = document.querySelector(
				'.wp-block-wporg-release-page'
			);

			if (element) {
				element.scrollIntoView({
					behavior: 'instant',
					block: 'center',
				});
			}
		},
		handleBackClick(event) {
			event.preventDefault();
			state.preSubmitting = false;
		},
		*handleSubmit(event) {
			// Prevent default form submission
			event.preventDefault();

			debugger;

			// Set waiting state
			state.isWaiting = true;
			state.errorMessage = '';

			const { pluginSlug, nonce, homeUrl } = getContext();

			try {
				const response = yield fetch(
					'/plugins/wp-json/plugins/v2/plugin/clapback/release',
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': nonce,
						},
						body: JSON.stringify({
							plugin_slug: pluginSlug,
						}),
					}
				);

				const data = yield response.json();

				// Update state based on response
				if (data.success) {
				} else {
					state.errorMessage = data.message || 'Action failed';
				}
			} catch (error) {
				state.errorMessage = 'Network error occurred';
			} finally {
				state.isWaiting = false;
			}
		},
	},
});
