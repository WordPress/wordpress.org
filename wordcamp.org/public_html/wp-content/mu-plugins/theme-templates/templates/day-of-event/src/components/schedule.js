/**
 * External dependencies
 */
import { sortBy } from 'lodash';

/**
 * WordPress dependencies
 */
import { Spinner }                   from '@wordpress/components';
import { __experimentalGetSettings } from '@wordpress/date';
import { _x }                        from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { SessionsGroup } from './sessions-group';


/**
 * todo describe what this does
 *
 * @param {{tracks: [], sessions: []}} data an object with tracks and sessions
 *
 * @returns {{next: *, now: *, track: T}[]} an array with schedule data
 *
 * todo just use {object}, maybe make returns simpler though, returning multidimensional objects is a smell that it's doing more than 1 thing
 * todo this whole function feels awkward and hard to follow, and should probably be refactored to be more straight-forward.
 */
const getScheduleData = ( data ) => {
	/*
	 * The timestamps returned by the REST API are relative to the local timezone, rather than UTC.
	 *
	 * To further complicate things, there are several bugs with `wp.date` functions that prevent using them, and
	 * require a workaround to make sure the timezones match.
	 *
	 * @todo Once those bugs are fixed, we should use `wp.date` functions instead of `Date()` natively, and the `offset`
	 * workaround should be removed.
	 *
	 * https://github.com/WordPress/gutenberg/issues/16218
	 * https://github.com/WordPress/gutenberg/issues/15221
	 */
	const nowUTC = window.dayOfEventConfig.scheduleNowOverride || Date.now(); // todo remove NowOverride after WCEU testing.
	const offset = __experimentalGetSettings().timezone.offset * ( 60 * 60 * 1000 ); // todo find future-proof way to do this if aren't remove this after bugs above fixed.

	if ( ! Array.isArray( data.tracks ) ) {
		return;
		// should return empty array or object?
	}

	// todo break this into smaller sections instead of one long chain
	const scheduleData = data.tracks.map( ( track ) => {
		const sessionsInTrack = sortBy(
			data.sessions.filter(
				( session ) => session.session_track.includes( track.id )
			),
			( sessionInTrack ) => sessionInTrack.meta._wcpt_session_time // todo need to worry about this being converted to wrong time zone?
		);

		const indexOfNextSession = sessionsInTrack.findIndex(
			( session ) => {
				const sessionTimeUTC = ( session.meta._wcpt_session_time * 1000 ) - offset; // Convert to UTC, see note above.

				return nowUTC < sessionTimeUTC;
			}
		);

		const nextSession = sessionsInTrack[ indexOfNextSession ];
		const nowSession = sessionsInTrack[ indexOfNextSession - 1 ];

		return {
			track,
			now: nowSession,
			next: nextSession,
		};
	} );

	// todo test boundaries, like when `now` is before camp, and after camp.
	// also after end of 1st day but before start of 2nd day. also when no sessions are published.
	// also when one track has sessions and another doesn't
		// does "track finished" need to reflect that there may be gaps during middle of the day?

	// when the event is over, shouldn't see "track finished" under "coming up next", should probably just see a message saying it's over
	// but when 1st day is over and there are more sessions tomorrow, should see something saying today's sessions are done, but there are more tomorrow
		// or maybe show the first ones that start tomorrow?

	// why does "in progress" not show anything when a track is empty, but "coming up next" shows "track finished" ?
		// should be consistent

	return scheduleData;
};

const Schedule = ( { sessionList, trackList } ) => {
	const tracks = getScheduleData( { sessions: sessionList, tracks: trackList } );
	// todo rename to sessions/tracks or whatever's appropriate

	const onNowSessions = tracks.map( ( track ) => {    // todo TypeError: "tracks is undefined". what were steps to reproduce? should validate data when it comes into system, though, not down here.
		return {
			track: track.track,
			session: track.now,
		};
	} ).filter( ( sessionInTrack ) => !! sessionInTrack.session );

	const upNextSessions = tracks.map( ( track ) => {
		return {
			track: track.track,
			session: track.next,
		};
	} );

	return (
		<>
			{ !! onNowSessions.length &&
				<SessionsGroup
					sessionTrackPairs={ onNowSessions }
					title={ _x( 'In Progress', 'title', 'wordcamporg' ) }
				/>
			}

			{ !! upNextSessions.length &&
				<SessionsGroup
					sessionTrackPairs={ upNextSessions }
					title={ _x( 'Coming Up Next', 'title', 'wordcamporg' ) }
				/>
			}
		</>
	);
};


export const LiveSchedule = ( { fullScheduleUrl, isFetching, sessions, tracks } ) => {
	// todo test when there aren't any sessions
	// test when there are sessions, but they're not assigned to any tracks
	// todo should this be rendering 4x during the initial loading process?

	return (
		<div className="day-of-event-schedule">
			<h2>
				{ _x( 'Live Schedule', 'title', 'wordcamporg' ) }
			</h2>

			{/* If we already have some sessions, then continue showing them while we fetch new ones. */}
			{ isFetching && 0 === sessions.length &&
				<Spinner />
			}

			{ ( ! isFetching || 0 < sessions.length ) &&
				<Schedule
					sessionList={ sessions }
					trackList={ tracks }
				/>
			}
			{/* todo rename ^ to sessions/tracks, and move any logic up here */}

			<a href={ fullScheduleUrl } className="full-schedule">
				{ _x( 'View Full Schedule', 'text', 'wordcamporg' ) }
			</a>
		</div>
	);
};
