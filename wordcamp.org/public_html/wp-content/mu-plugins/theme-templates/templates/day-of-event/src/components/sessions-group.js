/**
 * Internal dependencies
 */
import { Session } from './session';

// todo is this the best name for this?
export const SessionsGroup = ( { title, sessionTrackPairs } ) => {
	// "session track pairs" is an awkward way to structure this data. instead `session.session_track` should just be overwritten/extended with the full track data.
		// ^ should be done when data enters the system, not down here
		// shouldn't that happen automatically when using `_embed` for the API call?
		// maybe can get rid of separate API call for tracks? or do we need tracks that aren't assigned to sessions? or would it result in much greater response size that wouldn't be performant?
	// anywhere else that does this same pattern should be refactored as well, including Session

	const validSessionTrackPairs = sessionTrackPairs.filter( sessionTrackPair => !! sessionTrackPair );
		// todo shouldn't have to worry about that down here, validating data should be taken care of when the data enters the system, and then everything else can assume that it's valid
		// search for all instances of !! to refactor them out of existence

	return (
		<section>
			<h3>{ title }</h3>

			{
				validSessionTrackPairs.map( ( sessionTrackPair, index ) => {
					let sessionKey = sessionTrackPair.session ? sessionTrackPair.session.id : index;    // todo will this ever be false? should be able to assume data is valid down here. probably remove this once `todo` above is done
					sessionKey = `${ sessionKey }-${ sessionTrackPair.track.id }`;

					return (
						<Session
							key={ sessionKey }
							session={ sessionTrackPair }
						/>
					);
				} )
			}
		</section>
	);
};
