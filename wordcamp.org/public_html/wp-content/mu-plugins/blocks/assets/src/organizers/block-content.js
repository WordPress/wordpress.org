/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { Disabled }       = wp.components;
const { Component }      = wp.element;
const { decodeEntities } = wp.htmlEntities;
const { __ }             = wp.i18n;

/**
 * Internal dependencies
 */
import { AvatarImage }                from '../shared/avatar';
import { ItemTitle, ItemHTMLContent } from '../shared/block-content';
import GridContentLayout              from '../shared/grid-layout/block-content';

class OrganizersBlockContent extends Component {
	render() {
		const { attributes, organizerPosts }                                     = this.props;
		const { show_avatars, avatar_size, avatar_align, content, excerpt_more } = attributes;

		return (
			<GridContentLayout { ...this.props } >
				{ organizerPosts.map( ( post ) =>
					<div
						key={ post.slug }
						className={ classnames(
							'wordcamp-organizer',
							'wordcamp-organizer-' + decodeEntities( post.slug ),
						) }
					>
						<ItemTitle
							className="wordcamp-organizer-title"
							headingLevel={ 3 }
							title={ post.title.rendered.trim() }
							link={ post.link }
						/>

						{ show_avatars &&
							<AvatarImage
								className={ classnames( 'wordcamp-organizer-avatar-container', 'align-' + decodeEntities( avatar_align ) ) }
								name={ decodeEntities( post.title.rendered.trim() ) || '' }
								size={ avatar_size }
								url={ post.avatar_urls[ '24' ] }
								imageLink={ post.link }
							/>
						}

						{ ( 'none' !== content ) &&
							<ItemHTMLContent
								className={ classnames( 'wordcamp-organizer-content-' + decodeEntities( content ) ) }
								content={  'full' === content ? post.content.rendered.trim() : post.excerpt.rendered.trim() }
								link={ (   'full' === content || excerpt_more ) ? post.link : null }
								linkText={ 'full' === content ? __( 'Visit organizer page', 'wordcamporg' ) : __( 'Read more', 'wordcamporg' ) }
							/>
						}
					</div>,
				) }
			</GridContentLayout>
		);
	}
}

export default OrganizersBlockContent;
