/**
 * External dependencies
 */
import { isUndefined, pickBy, split } from 'lodash';

/**
 * WordPress dependencies
 */
const apiFetch                = wp.apiFetch;
const { withSelect }          = wp.data;
const { Component, Fragment } = wp.element;
const { addQueryArgs }        = wp.url;

/**
 * Internal dependencies
 */
import OrganizersBlockControls     from './block-controls';
import OrganizersInspectorControls from './inspector-controls';
import OrganizersToolbar           from './toolbar';

export const ORGANIZERS_ICON = 'groups';

const blockData = window.WordCampBlocks.organizers || {};
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

class OrganizersEdit extends Component {
	constructor( props ) {
		super();

		this.state = {
			allSpeakerPosts : null,
			allSpeakerTerms : null,
		};
	}

	componentWillMount() {
		this.isStillMounted = true;

		const allSpeakerPosts = apiFetch( {
			path: addQueryArgs( `/wp/v2/organizers`, ALL_POSTS_QUERY ),
		} );
		const allSpeakerTerms = apiFetch( {
			path: addQueryArgs( `/wp/v2/organizer_team`, ALL_TERMS_QUERY ),
		} );

		if ( this.isStillMounted ) {
			this.setState( {
				allSpeakerPosts : allSpeakerPosts, // Promise
				allSpeakerTerms : allSpeakerTerms, // Promise
			} );
		}
	}

	componentWillUnmount() {
		this.isStillMounted = false;
	}

	render() {
		const { mode } = this.props.attributes;

		return (
			<Fragment>
				<OrganizersBlockControls
					icon={ ORGANIZERS_ICON }
					{ ...this.props }
					{ ...this.state }
				/>
				{ mode &&
					<Fragment>
						<OrganizersInspectorControls { ...this.props } />
						<OrganizersToolbar { ...this.props } />
					</Fragment>
				}
			</Fragment>
		);
	}
}

const organizerSelect = ( select, props ) => {
	const { mode, item_ids, sort } = props.attributes;
	const { getEntityRecords }     = select( 'core' );
	const [ orderby, order ]       = split( sort, '_', 2 );

	const args = {
		orderby  : orderby,
		order    : order,
		per_page : MAX_POSTS, // -1 is not allowed for per_page.
		_embed   : true,
		context  : 'view',
	};

	if ( Array.isArray( item_ids ) ) {
		switch ( mode ) {
			case 'wcb_organizer':
				args.include = item_ids;
				break;
			case 'wcb_organizer_team':
				args.organizer_team = item_ids;
				break;
		}
	}

	const organizersQuery = pickBy( args, ( value ) => ! isUndefined( value ) );

	return {
		blockData,
		organizerPosts : getEntityRecords( 'postType', 'wcb_organizer', organizersQuery ),
	};
};

export const edit = withSelect( organizerSelect )( OrganizersEdit );
