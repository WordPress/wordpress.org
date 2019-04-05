/**
 * Displays sponsor block.
 */
import SponsorInspectorControls from './inspector-controls';
import SponsorBlockControls from './block-controls';
import GridToolbar from '../shared/grid-layout/toolbar';
import { ICON }                 from './index';

/**
 WordPress dependencies.
 **/
const { withSelect } = wp.data;
const { Component, Fragment } = wp.element;
const apiFetch = wp.apiFetch;
const { addQueryArgs } = wp.url;

const MAX_PAGE = 100;

class SponsorsEdit extends Component {
	/**
	 * Constructor for SponsorsEdit block.
	 *
	 * @param props
	 */
	constructor( props ) {
		super( props );

		this.fetchSponsors();
	}

	fetchSponsors() {
		const sponsorQuery = {
			orderby  : 'title',
			order    : 'asc',
			per_page : MAX_PAGE,
			_embed   : true,
		};

		const sponsorLevelQuery = {
			orderby  : 'id',
			order    : 'asc',
			per_page : MAX_PAGE,
			_embed   : true,
		};

		this.state = {
			sponsorPosts  : apiFetch( { path: addQueryArgs( '/wp/v2/sponsors', sponsorQuery ) } ),
			sponsorLevels : apiFetch( { path: addQueryArgs( '/wp/v2/sponsor_level', sponsorLevelQuery ) } ),
			siteSettings  : apiFetch( { path: addQueryArgs( '/wp/v2/settings', {} ) } ),
		}
	}

	/**
	 * Renders SponsorEdit component.
	 */
	render() {
		const { sponsorPosts, sponsorLevels, siteSettings } = this.state;
		const { attributes } = this.props;
		const { mode } = attributes;

		return (
			<Fragment>
				{
					<SponsorBlockControls
						icon={ ICON }
						sponsorPosts={ sponsorPosts }
						sponsorLevels={ sponsorLevels }
						siteSettings={ siteSettings }
						{ ...this.props }
					/>
				}
				<Fragment>
					<SponsorInspectorControls
						sponsorPosts={ sponsorPosts }
						sponsorLevels={ sponsorLevels }
						{ ...this.props }
					/>
					{ mode &&
					<GridToolbar
						{ ...this.props }
					/>
					}
				</Fragment>
			</Fragment>
		);
	}
}

export const edit = SponsorsEdit;
