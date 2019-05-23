/**
 * External dependencies
 */
import { get }    from 'lodash';
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { Component } = wp.element;
const { __ }        = wp.i18n;

/**
 * Internal dependencies
 */
import { ItemTitle, ItemHTMLContent, ItemPermalink, BlockNoContent } from '../../components/block-content';
import { FeaturedImage }                                             from '../../components/image';
import { PostList }                                                  from '../../components/post-list';
import { filterEntities }                                            from '../../data';

/**
 * Component for displaying the block content.
 */
export class BlockContent extends Component {
	/**
	 * Run additional operations during component initialization.
	 *
	 * @param {Object} props
	 */
	constructor( props ) {
		super( props );

		this.getFilteredPosts = this.getFilteredPosts.bind( this );
	}

	/**
	 * Filter and sort the content that will be rendered.
	 *
	 * @returns {Array}
	 */
	getFilteredPosts() {
		const { attributes, entities } = this.props;
		const { wcb_sponsor: posts } = entities;
		const { mode, item_ids, sort } = attributes;

		const args = {};

		if ( Array.isArray( item_ids ) && item_ids.length > 0 ) {
			args.filter  = [
				{
					fieldName  : mode === 'wcb_sponsor' ? 'id' : 'sponsor_level',
					fieldValue : item_ids,
				},
			];
		}

		args.sort = sort;

		return filterEntities( posts, args );
	}

	/**
	 * Render the block content.
	 *
	 * @return {Element}
	 */
	render() {
		const { attributes } = this.props;
		const { show_name, show_logo, featured_image_width, image_align, content } = attributes;

		const posts     = this.getFilteredPosts();
		const isLoading = ! Array.isArray( posts );
		const hasPosts  = ! isLoading && posts.length > 0;

		if ( isLoading || ! hasPosts ) {
			return (
				<BlockNoContent loading={ isLoading } />
			);
		}

		return (
			<PostList
				{ ...this.props }
				className="wordcamp-sponsors"
			>
				{ posts.map( ( post ) =>
					<div
						key={ post.slug }
						className={ classnames( 'wordcamp-sponsors__post', `slug-{post.slug}` ) }
					>
						{ show_name &&
							<ItemTitle
								className="wordcamp-sponsors__title"
								headingLevel={ 3 }
								title={ post.title.rendered.trim() }
								link={ post.link }
							/>
						}

						{ show_logo &&
							<FeaturedImage
								imageData={ get( post, '_embedded.wp:featuredmedia[0]', {} ) }
								width={ featured_image_width }
								className={ classnames( 'wordcamp-sponsors__featured-image', 'wordcamp-sponsors__logo', 'align-' + image_align ) }
								imageLink={ post.link }
							/>
						}

						{ ( 'none' !== content ) &&
							<ItemHTMLContent
								className={ classnames( 'wordcamp-sponsors__content', 'has-content-' + content ) }
								content={ 'full' === content ? post.content.rendered.trim() : post.excerpt.rendered.trim() }
							/>
						}

						{ ( 'full' === content ) &&
							<ItemPermalink
								link={ post.link }
								linkText={ __( 'Visit sponsor page', 'wordcamporg' ) }
								className="wordcamp-sponsors__permalink"
							/>
						}
					</div>
				) }
			</PostList>
		);
	}
}
