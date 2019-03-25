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
import SessionsBlockControls from './block-controls';
import SessionsInspectorControls from './inspector-controls';
import GridToolbar from '../shared/grid-layout/toolbar';

const blockData = window.WordCampBlocks.sessions || {};

const SESSIONS_ICON = 'list-view';
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

class SessionsEdit extends Component {
	constructor( props ) {
		super();

		this.state = {
			allSessionPosts      : null,
			allSessionTracks     : null,
			allSessionCategories : null,
		};
	}

	componentWillMount() {
		this.isStillMounted = true;

		const allSessionPosts = apiFetch( {
			path: addQueryArgs( `/wp/v2/sessions`, ALL_POSTS_QUERY ),
		} );
		const allSessionTracks = apiFetch( {
			path: addQueryArgs( `/wp/v2/session_track`, ALL_TERMS_QUERY ),
		} );
		const allSessionCategories = apiFetch( {
			path: addQueryArgs( `/wp/v2/session_category`, ALL_TERMS_QUERY ),
		} );

		if ( this.isStillMounted ) {
			this.setState( {
				allSessionPosts      : allSessionPosts, // Promise
				allSessionTracks     : allSessionTracks, // Promise
				allSessionCategories : allSessionCategories, // Promise
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
				<SessionsBlockControls
					icon={ SESSIONS_ICON }
					{ ...this.props }
					{ ...this.state }
				/>
				{ mode &&
				<Fragment>
					<SessionsInspectorControls { ...this.props } />
					<GridToolbar { ...this.props } />
				</Fragment>
				}
			</Fragment>
		);
	}
}

const sessionsSelect = ( select, props ) => {
	const { mode, item_ids, sort } = props.attributes;
	const { getEntityRecords } = select( 'core' );

	const args = {
		per_page           : MAX_POSTS, // -1 is not allowed for per_page.
		_embed             : true,
		context            : 'view',
		_wcpt_session_type : 'session',
	};

	if ( 'session_time' !== sort ) {
		const [ orderby, order ] = split( sort, '_', 2 );
		args.orderby = orderby;
		args.order = order;
	}

	if ( Array.isArray( item_ids ) ) {
		switch ( mode ) {
			case 'wcb_session':
				args.include = item_ids;
				break;
			case 'wcb_track':
				args.session_track = item_ids;
				break;
			case 'wcb_session_category':
				args.session_category = item_ids;
				break;
		}
	}

	const sessionsQuery = pickBy( args, ( value ) => ! isUndefined( value ) );

	const sessionPosts = getEntityRecords( 'postType', 'wcb_session', sessionsQuery );

	// todo Is there a way to do this sorting via REST API parameters?
	if ( Array.isArray( sessionPosts ) && 'session_time' === sort ) {
		sessionPosts.sort( ( a, b ) => {
			return Number( a.meta._wcpt_session_time ) - Number( b.meta._wcpt_session_time );
		} );
	}

	return { blockData, sessionPosts };
};

export const edit = withSelect( sessionsSelect )( SessionsEdit );
