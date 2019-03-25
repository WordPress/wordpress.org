/**
 * External dependencies.
 */
import { get, difference } from 'lodash';
import classnames from 'classnames';

/**
 * WordPress dependencies.
 */
const { Component } = wp.element;

/**
 * Internal dependencies.
 */
import FeaturedImage from '../shared/featured-image';
import GridContentLayout from '../shared/grid-layout/block-content';
import { ItemTitle, ItemHTMLContent } from '../shared/block-content';

/**
 * Renders individual sponsor post inside editor.
 *
 * @param sponsorPost
 * @param attributes
 * @param onFeatureImageChange
 * @returns {*}
 * @constructor
 */
function SponsorDetail( { sponsorPost, attributes, onFeatureImageChange } ) {

	const {
		show_name, show_logo, show_desc
	} = attributes;

	const featuredImageSizes = get( sponsorPost, "_embedded.wp:featuredmedia[0].media_details.sizes", {} );

	return (
		<div className={ "wordcamp-sponsor-details"}>

			{ ( show_name || show_name === undefined ) &&
			<ItemTitle
				className='wordcamp-sponsor-title'
				headingLevel={ 3 }
				title={ sponsorPost.title.rendered.trim() }
				link={ sponsorPost.link }
			/>
			}
			{ ( show_logo || show_logo === undefined ) &&
			<FeaturedImage
				className={"wordcamp-sponsor-featured-image wordcamp-sponsor-logo"}
				wpMediaDetails={featuredImageSizes}
				alt={sponsorPost.title.rendered}
				attributes={attributes}
			/>
			}
			{ ( show_desc || show_desc === undefined ) &&
			<ItemHTMLContent
				className={ classnames( 'wordcamp-sponsor-content' ) }
				content={ sponsorPost.content.rendered.trim() }
			/>
			}
		</div>
	);
}

/**
 * Component for rendering Sponsors post inside editor.
 */
class SponsorBlockContent extends Component {

	constructor( props ) {
		super();

		this.state = {
			selectedPosts: [],
			sortBy: 'name_asc',
		};
	}
	/**
	 * Call back for when featured image URL is changed for a post.
	 * We are storing the URL object as JSON stringified value because I was
	 * not able to get object type to work properly. Maybe its not supported in
	 * Gutenberg yet.
	 *
	 * @param sponsorId
	 * @param imageURL
	 */
	setFeaturedImageURL( sponsorId, imageURL) {
		const sponsor_image_urls = this.sponsorImageUrl || {};
		sponsor_image_urls[ sponsorId ] = imageURL;
		this.sponsorImageUrl = sponsor_image_urls;

		const { setAttributes } = this.props;
		const sponsor_image_urls_latest = this.sponsorImageUrl;
		setAttributes( { sponsor_image_urls: encodeURIComponent( JSON.stringify( sponsor_image_urls_latest ) ) } );
	}

	componentWillReceiveProps( nextProps ) {
		// Sort the sponsor posts. Since this could potentially be expensive, lets do it in componentWillReceiveProps hook and set state with result if anything is changed.
		const { selectedPosts: newSelectedPosts, attributes: newAttributes, sponsorTermOrder: newSponsorTermOrder } = nextProps;
		const { sort_by: newSortBy } = newAttributes;
		const newSelectedPostIds = newSelectedPosts.map( post => post.id ).sort();

		const { selectedPosts, sortBy } = this.state;
		const selectedPostsIds = selectedPosts.map( post => post.id ).sort();

		if ( sortBy === newSortBy && newSelectedPosts.length === selectedPosts.length && difference( selectedPostsIds, newSelectedPostIds ).length === 0 ) {
			// Everything is same. No need to calculate sorting. Lets bail.
			return;
		}

		let sortedPosts;

		switch ( newSortBy ) {
			case 'sponsor_level' :
				if ( ! Array.isArray( newSponsorTermOrder ) ||
					newSponsorTermOrder.length === 0 ) {
					break;
				}
				sortedPosts = newSelectedPosts.sort( ( sponsor1, sponsor2 ) => {
					return newSponsorTermOrder.indexOf( ( sponsor1.sponsor_level || [] )[0] ) - newSponsorTermOrder.indexOf( ( sponsor2.sponsor_level || [] )[0] )
				} );
				break;

			case 'name_desc' :
				sortedPosts = newSelectedPosts.sort( ( sponsor1, sponsor2 ) => {
					const title1 = sponsor1.title.rendered.trim();
					const title2 = sponsor2.title.rendered.trim();
					return title1 > title2 ? -1 : 1 ;
				} );
				break;

			case 'name_asc' :
			default:
				sortedPosts = newSelectedPosts.sort( ( sponsor1, sponsor2 ) => {
					const title1 = sponsor1.title.rendered.trim();
					const title2 = sponsor2.title.rendered.trim();
					return title1 < title2 ? -1 : 1 ;
				} );
				break;
		}
		this.setState(
			{
				selectedPosts: sortedPosts,
				sortBy: newSortBy,
			}
		);
	}

	/**
	 * Renders Sponsor Block content inside editor.
	 *
	 * @returns {*}
	 */
	render() {
		const { attributes } = this.props;
		const { selectedPosts } = this.state;

		return (
			<GridContentLayout
				{ ...this.props }
			>
				{
					selectedPosts.map( ( post ) => {
						return (
								<SponsorDetail
									sponsorPost={ post }
									attributes={ attributes }
								/>
						)
					} )
				}
			</GridContentLayout>
		)
	}

}

export default SponsorBlockContent;
