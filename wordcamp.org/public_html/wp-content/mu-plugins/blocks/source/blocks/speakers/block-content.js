/**
 * External dependencies
 */
import { get }    from 'lodash';
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { Disabled }            = wp.components;
const { Component, Fragment } = wp.element;
const { __, _n }              = wp.i18n;
const { escapeAttribute }     = wp.escapeHtml;

/**
 * Internal dependencies
 */
import { ItemTitle, ItemHTMLContent, ItemPermalink, BlockNoContent } from '../../components/block-content';
import { AvatarImage }                                               from '../../components/image';
import { PostList }                                                  from '../../components/post-list';
import { filterEntities }                                            from '../../data';
import { tokenSplit, arrayTokenReplace }                             from '../../i18n';

import './block-content.scss';

/**
 * Component for the section of each speaker post that displays information about relevant sessions.
 *
 * @param {Object} props {
 *     @type {Object} speaker
 *     @type {Array}  tracks
 * }
 *
 * @return {Element}
 */
function SpeakerSessions( { speaker, tracks } ) {
	const sessions = get( speaker, '_embedded.sessions', [] );

	let output = ( <Fragment>{ null }</Fragment> );

	if ( sessions.length ) {
		output = (
			<div className={ classnames( 'wordcamp-item-meta', 'wordcamp-speaker-sessions' ) }>
				<h4 className="wordcamp-speakers__sessions-heading">
					{ _n( 'Session', 'Sessions', sessions.length, 'wordcamporg' ) }
				</h4>

				<ul className="wordcamp-speakers__sessions-list">
					{ sessions.map( ( session ) =>
						<li
							key={ escapeAttribute( session.slug ) }
							className="wordcamp-speakers__sessions-list-item"
						>
							<Disabled>
								<a
									className="wordcamp-speakers__session-link"
									href={ session.link }
								>
									{ session.title.rendered.trim() || __( '(Untitled)', 'wordcamporg' ) }
								</a>
								<span className="wordcamp-speakers__session-info">
									{ session.session_track.length && Array.isArray( tracks ) &&
										arrayTokenReplace(
											/* translators: 1: A date; 2: A time; 3: A location; */
											tokenSplit( __( '%1$s at %2$s in %3$s', 'wordcamporg' ) ),
											[
												session.session_date_time.date,
												session.session_date_time.time,
												get( tracks.find( ( value ) => {
													const [ firstTrackId ] = session.session_track;
													return parseInt( value.id ) === firstTrackId;
												} ), 'name' ),
											]
										)
									}
									{ ( ! session.session_track.length || ! Array.isArray( tracks ) ) &&
										arrayTokenReplace(
											/* translators: 1: A date; 2: A time; */
											tokenSplit( __( '%1$s at %2$s', 'wordcamporg' ), ),
											[
												session.session_date_time.date,
												session.session_date_time.time,
											]
										)
									}
								</span>
							</Disabled>
						</li>
					) }
				</ul>
			</div>
		);
	}

	return output;
}

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
		const { wcb_speaker: posts } = entities;
		const { mode, item_ids, sort } = attributes;

		const args = {};

		if ( Array.isArray( item_ids ) && item_ids.length > 0 ) {
			args.filter  = [
				{
					fieldName  : mode === 'wcb_speaker' ? 'id' : 'speaker_group',
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
		const { attributes, entities } = this.props;
		const { wcb_track: tracks } = entities;
		const { show_avatars, avatar_size, avatar_align, content, show_session } = attributes;

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
				className="wordcamp-speakers"
			>
				{ posts.map( ( post ) =>
					<div
						key={ post.slug }
						className={ classnames(
							'wordcamp-speakers__post',
							`slug-${ post.slug }`,
						) }
					>
						<ItemTitle
							className="wordcamp-speakers__title"
							headingLevel={ 3 }
							title={ post.title.rendered.trim() }
							link={ post.link }
						/>

						{ show_avatars &&
							<AvatarImage
								className={ classnames( 'align-' + avatar_align ) }
								name={ post.title.rendered.trim() || '' }
								size={ avatar_size }
								url={ post.avatar_urls[ '24' ] }
								imageLink={ post.link }
							/>
						}

						{ ( 'none' !== content ) &&
							<ItemHTMLContent
								className={ classnames( 'wordcamp-speakers__content', 'is-' + content ) }
								content={ 'full' === content ? post.content.rendered.trim() : post.excerpt.rendered.trim() }
							/>
						}

						{ true === show_session &&
							<SpeakerSessions
								speaker={ post }
								tracks={ tracks }
							/>
						}

						{ ( 'full' === content ) &&
							<ItemPermalink
								link={ post.link }
								linkText={ __( 'Visit speaker page', 'wordcamporg' ) }
							/>
						}
					</div>
				) }
			</PostList>
		);
	}
}
