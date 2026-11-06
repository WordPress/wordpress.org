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
			const { slug } = getContext();
			// The homepage renders only the newest jobs. When some of a
			// category's jobs aren't on the page, follow the link to its
			// archive instead of filtering an incomplete list.
			if (
				'all' !== slug &&
				( state.counts[ slug ] || 0 ) < ( state.totals?.[ slug ] || 0 )
			) {
				return;
			}
			event.preventDefault();
			state.active = slug;
		},
	},
} );
