/**
 * External dependencies
 */
import { isUndefined, pickBy, split } from 'lodash';

/**
 * WordPress dependencies
 */
const apiFetch = wp.apiFetch;
const { withSelect } = wp.data;
const { Component, Fragment } = wp.element;
const { addQueryArgs } = wp.url;

/**
 * Internal dependencies
 */
import SpeakersBlockControls from './block-controls';
import SpeakersInspectorControls from './inspector-controls';
import SpeakersToolbar from './toolbar';
import { ICON }        from './index';

const blockData = window.WordCampBlocks.speakers || {};

const MAX_POSTS = 100;

const ALL_POSTS_QUERY = {
	orderby  : 'title',
	order    : 'asc',
	per_page : MAX_POSTS,
	_embed   : true,
};

const ALL_TERMS_QUERY = {
	orderby  : 'name',
	order    : 'asc',
	per_page : MAX_POSTS,
};

class SpeakersEdit extends Component {
	constructor( props ) {
		super( props );

		this.fetchSpeakers();
	}

	fetchSpeakers() {
		const allSpeakerPosts = apiFetch( {
			path: addQueryArgs( `/wp/v2/speakers`, ALL_POSTS_QUERY ),
		} );
		const allSpeakerTerms = apiFetch( {
			path: addQueryArgs( `/wp/v2/speaker_group`, ALL_TERMS_QUERY ),
		} );

		this.state = {
			allSpeakerPosts : allSpeakerPosts, // Promise
			allSpeakerTerms : allSpeakerTerms, // Promise
		}
	}

	render() {
		const { mode } = this.props.attributes;

		return (
			<Fragment>
				<SpeakersBlockControls
					icon={ ICON }
					{ ...this.props }
					{ ...this.state }
				/>
				{ mode &&
					<Fragment>
						<SpeakersInspectorControls { ...this.props } />
						<SpeakersToolbar { ...this.props } />
					</Fragment>
				}
			</Fragment>
		);
	}
}

const speakersSelect = ( select, props ) => {
	const { mode, item_ids, sort } = props.attributes;
	const { getEntityRecords } = select( 'core' );
	const [ orderby, order ] = split( sort, '_', 2 );

	const args = {
		orderby  : orderby,
		order    : order,
		per_page : MAX_POSTS, // -1 is not allowed for per_page.
		_embed   : true,
		context  : 'view',
	};

	if ( Array.isArray( item_ids ) ) {
		switch ( mode ) {
			case 'wcb_speaker':
				args.include = item_ids;
				break;
			case 'wcb_speaker_group':
				args.speaker_group = item_ids;
				break;
		}
	}

	const speakersQuery = pickBy( args, ( value ) => ! isUndefined( value ) );

	return {
		blockData,
		speakerPosts : getEntityRecords( 'postType', 'wcb_speaker', speakersQuery ),
		tracks       : getEntityRecords( 'taxonomy', 'wcb_track', { per_page: MAX_POSTS } ),
	};
};

export const edit = withSelect( speakersSelect )( SpeakersEdit );
