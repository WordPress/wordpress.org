/**
 * External dependencies
 */
import { keyBy, flatten } from 'lodash';

/**
 * WordPress dependencies
 */
import { stripTagsAndEncodeText } from '@wordpress/sanitize';
import { _x }                     from '@wordpress/i18n';


export const Session = ( { session } ) => {
	const noMoreSessions = {
		link              : '#',    // todo null would be better? then don't output the markup for a link if don't have a url. will always have a valid url though? are these defaults really needed?
		title             : { rendered: _x( 'Track finished', 'session title', 'wordcamporg' ) },
		session_date_time : { time: '' },
		session_category  : [],
		_embedded         : { 'wp:term': {}, speakers: [] },
		meta              :  { _wcpt_session_type: '' },
	};
	// shouldn't be creating a fake "session" for this purpose, should instead have the controller aware of when there are and aren't sessions,
	// and if there aren't then it should display a message rather than a fake session

	const {
		// todo this entire assignment is hard to read and should be refactored in several ways
		// are all the defaults, etc actually needed? maybe some but probably not all

		session: {
			link = '#',
			title: {
				rendered: title = '',
			},
			session_date_time: {
				time = '',
			},
			session_category: sessionCategories = [],
			_embedded: {
				'wp:term': embeddedTerms = {},
				speakers = [],
			},
			meta: {
				_wcpt_session_type: sessionType = '',
			},
		} = noMoreSessions,

		track: {
			name: trackName,
			slug: trackSlug,
		},
	} = session;

	const terms         = keyBy( flatten( embeddedTerms ), 'id' );
	const validSpeakers = speakers.filter( ( speaker ) => ! ! speaker.id );
	let categoryName, categorySlug;

	if ( sessionCategories.length > 0 ) {
		const sessionCategory = sessionCategories[ 0 ]; // todo is this an ID? if so, name it descriptively to reflect that
		categoryName = terms[ sessionCategory ].name;
		categorySlug = terms[ sessionCategory ].slug;

		// todo test this condition
	}

	return (
		<div className={ `wordcamp-schedule-session ${ sessionType }` }>
			<span className={ `wordcamp-schedule-session-track ${ trackSlug }` }>{ trackName }</span>

			<div className="wordcamp-schedule-session-details">
				<h4 className="wordcamp-schedule-session-title">
					<a href={ link }>
						{ stripTagsAndEncodeText( title ) }
					</a>
				</h4>

				<span className="wordcamp-schedule-session-time">{ time }</span>

				<span className="wordcamp-schedule-session-speaker">
					{
						!! validSpeakers.length && validSpeakers.map( ( speaker ) => {
							const {
								id,
								title: {
									rendered: name,
								},
								link: speakerLink,
							} = speaker;

							return (
								<a key={ id } href={ speakerLink }>{ stripTagsAndEncodeText( name ) }</a>
							);
						} )
					}
				</span>

				{ !! categoryName && (
					<span className={ `wordcamp-schedule-session-category ${ categorySlug }` }>{ categoryName }</span>
				) }
			</div>
		</div>
	);
};
