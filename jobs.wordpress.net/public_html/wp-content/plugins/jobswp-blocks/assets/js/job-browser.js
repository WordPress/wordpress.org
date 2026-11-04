import { store, getContext } from '@wordpress/interactivity';

const { state } = store( 'jobswp/job-browser', {
	state: {
		get current() {
			return getContext().slug === state.active ? 'true' : false;
		},
		get isVisible() {
			const { cats } = getContext();
			return 'all' === state.active || cats.includes( state.active );
		},
		get isEmpty() {
			return 'all' !== state.active && ! state.counts[ state.active ];
		},
	},
	actions: {
		select( event ) {
			event.preventDefault();
			state.active = getContext().slug;
		},
	},
} );
