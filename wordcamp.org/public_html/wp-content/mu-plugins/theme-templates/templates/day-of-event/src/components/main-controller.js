/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { fetchSessions, fetchTracks, fetchPosts } from '../api';
import { LiveSchedule }                           from './schedule';
import { LatestPosts }                            from './latest-posts';


const entityLists = {
	sessionList: fetchSessions,
	trackList: fetchTracks,
	postList: fetchPosts,
};
// todo need ^ ? should they be class properties instead of separate vars?
	// maybe b/c they're external b/c can't have const class properties?

// this wont work when 'coming soon' mode is on, b/c front-end rest api is disabled then?
	// what's a good way to handle that?

// todo also unfinished comments on https://github.com/wceu/wordcamp-pwa-page/pull/11

// todo run linter on everything after removing all the todos, refactoring, etc

// todo reduce bundle size, 10k is way too big for a small thing like this

// what happens if browser doesn't support service workers?
	// do they still get content template? they should, but they'll hits api every 60 seconds, so need to detect that and change limit to every 15 minutes in those cases
	// maybe usage not high enough to justify the time spent on that though

// add other wp-scripts to package.json scripts, like linting, etc
	// maybe create a central wordcamp.org build process for anything using wp-scripts?
	// avoid duplication and managing lots of little things that all do the same thing
	// need to convert blocks to wp-scripts first though


export class MainController extends Component {
	constructor( props ) {
		super( props );

		this.state = {};

		for ( const listName of Object.keys( entityLists ) ) {
			this.state[ listName ] = {
				isFetching : true,
				error      : null,
				data       : [],
			};
		}

		// todo test initial loading state, can throttle speed in dev tools
	}

	/**
	 * Loop over entityLists and update their state.
	 */
	updateLists() {
		let entityFetcher;

		for ( const listName of Object.keys( entityLists ) ) {
			entityFetcher = entityLists[ listName ];

			this.setState( ( state ) => ( {
				[ listName ]: { ...state[ listName ], isFetching: true },   // todo seems like this can be less verbose, not sure should be spreading
			} ) );

			entityFetcher().then( data => {
				this.setState( {
					[ listName ]: { isFetching: false, data }
					// todo does this overwrite the error property? if reference this.state.listname here, then will probably need to move this whole thing to callback of above setstate b/c can't assume it'll complete before reaching this?
					// the [ listname ] syntax seems awkward, is there a better way?
				} );

			} ).catch( error => {
				this.setState( state => ( { // todo why ( here?
					...state,   // todo why spreading here and below?
					// todo all of this is really hard to read, need to simplify it
					[ listName ]: {
						...state[ listName ],
						isFetching: false,
						error,
					},
				} ) );
			} );
		}
	}

	componentDidMount() {
		this.updateLists();
			// todo do in the construction since only want once? or would want each time this rerenders theoretically? seems wrong to do it _after_ a render though, shouldn't it be before b/c it'll update state?
			// maybe want to do after b/c gonna mount it w/ a loading state?

		// maybe should bust rest api caching the first time updateLists() is called, so that explicit refreshes from the user fetch new data?


		// todo document that requests every 60 seconds will be mitigated by service worker caching, otherwise could be scaling issue
		this.updateIntervalId = window.setInterval( () => {
			this.updateLists();
		}, 60 * 1000 );

		// todo shouldn't remove content whiel fetching updated content, only show loading during initial load
			// same for error. if error output to console but don't remove good content that already have
	}

	componentWillUnmount() {
		window.clearInterval( this.updateIntervalId );
	}

	render() {
		const { config }                           = this.props;
		const { postList, sessionList, trackList } = this.state;

		return (
			<>
				<LiveSchedule
					fullScheduleUrl={ config.scheduleUrl }
					isFetching={ sessionList.isFetching || trackList.isFetching }
					sessions={ sessionList.data }
					tracks={ trackList.data }
				/>

				<LatestPosts
					archiveUrl={ config.postsArchiveUrl }
					isfetching={ postList.isFetching }
					posts={ postList.data }
				/>
			</>
		);
	}
}
