import { store, getContext, withSyncEvent } from '@wordpress/interactivity';

const { state } = store( 'wporg-jobs/job-browser', {
	state: {
		get current() {
			return getContext().slug === state.active ? 'true' : false;
		},
		get isVisible() {
			const { cats } = getContext();
			return 'all' === state.active || cats.includes( state.active );
		},
		get isEmpty() {
			return 'all' !== state.active && ! state.counts?.[ state.active ];
		},
	},
	actions: {
		select: withSyncEvent( ( event ) => {
			if ( event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
				return;
			}
			const { slug } = getContext();
			// Follow the link to the archive when the page doesn't hold all of the category's jobs.
			if (
				'all' !== slug &&
				( state.counts?.[ slug ] || 0 ) < ( state.totals?.[ slug ] || 0 )
			) {
				return;
			}
			event.preventDefault();
			state.active = slug;
		} ),
	},
} );
