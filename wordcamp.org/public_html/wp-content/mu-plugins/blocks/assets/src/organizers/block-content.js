/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { Component } = wp.element;
const { __ }        = wp.i18n;

/**
 * Internal dependencies
 */
import { AvatarImage }                from '../shared/avatar';
import {ItemTitle, ItemHTMLContent, ItemPermalink} from '../shared/block-content';
import GridContentLayout              from '../shared/grid-layout/block-content';

class OrganizersBlockContent extends Component {
	render() {
		const { attributes, organizerPosts }                                     = this.props;
		const { show_avatars, avatar_size, avatar_align, content } = attributes;

		return (
			<GridContentLayout
				className="wordcamp-organizers-block"
				{ ...this.props }
			>
				{ organizerPosts.map( ( post ) => /* Note that organizer posts are not 'public', so there are no permalinks. */
					<div
						key={ post.slug }
						className={ classnames(
							'wordcamp-organizer',
							'wordcamp-organizer-' + post.slug.trim(),
						) }
					>
						<ItemTitle
							className="wordcamp-organizer-title"
							headingLevel={ 3 }
							title={ post.title.rendered.trim() }
						/>

						{ show_avatars &&
							<AvatarImage
								className={ classnames( 'wordcamp-organizer-avatar-container', 'align-' + avatar_align ) }
								name={ post.title.rendered.trim() || '' }
								size={ avatar_size }
								url={ post.avatar_urls[ '24' ] }
							/>
						}

						{ ( 'none' !== content ) &&
							<ItemHTMLContent
								className={ classnames( 'wordcamp-organizer-content-' + content ) }
								content={  'full' === content ? post.content.rendered.trim() : post.excerpt.rendered.trim() }
							/>
						}
					</div>,
				) }
			</GridContentLayout>
		);
	}
}

export default OrganizersBlockContent;
