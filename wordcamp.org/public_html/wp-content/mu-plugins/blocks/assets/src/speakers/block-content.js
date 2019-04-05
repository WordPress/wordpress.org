/**
 * External dependencies
 */
import { get } from 'lodash';
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { Disabled } = wp.components;
const { Component, Fragment } = wp.element;
const { decodeEntities } = wp.htmlEntities;
const { __, _n } = wp.i18n;
const { escapeAttribute } = wp.escapeHtml;

/**
 * Internal dependencies
 */
import { AvatarImage } from '../shared/avatar';
import { ItemTitle, ItemHTMLContent } from '../shared/block-content';
import { tokenSplit, arrayTokenReplace } from '../shared/i18n';
import GridContentLayout from '../shared/grid-layout/block-content';
import './block-content.scss';

function SpeakerSessions( { speaker, tracks } ) {
	const sessions = get( speaker, '_embedded.sessions', [] );

	let output = ( <Fragment>{ null }</Fragment> );

	if ( sessions.length ) {
		output = (
			<div className={ classnames( 'wordcamp-item-meta', 'wordcamp-speaker-sessions' ) }>
				<h4 className="wordcamp-speaker-sessions-heading">
					{ _n( 'Session', 'Sessions', sessions.length, 'wordcamporg' ) }
				</h4>

				<ul className="wordcamp-speaker-sessions-list">
					{ sessions.map( ( session ) =>
						<li
							key={ escapeAttribute( session.slug ) }
							className="wordcamp-speaker-session-content"
						>
							<Disabled>
								<a
									className="wordcamp-speaker-session-link"
									href={ session.link }
								>
									{ decodeEntities( session.title.rendered.trim() ) || __( '(Untitled)', 'wordcamporg' ) }
								</a>
								<span className="wordcamp-speaker-session-info">
									{ session.session_track.length && Array.isArray( tracks ) &&
										arrayTokenReplace(
											/* translators: 1: A date; 2: A time; 3: A location; */
											tokenSplit( __( '%1$s at %2$s in %3$s', 'wordcamporg' ) ),
											[
												decodeEntities( session.session_date_time.date ),
												decodeEntities( session.session_date_time.time ),
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
												decodeEntities( session.session_date_time.date ),
												decodeEntities( session.session_date_time.time ),
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

class SpeakersBlockContent extends Component {
	render() {
		const { attributes, speakerPosts, tracks } = this.props;
		const {
			show_avatars, avatar_size, avatar_align,
			content, excerpt_more, show_session,
		} = attributes;

		return (
			<GridContentLayout
				{ ...this.props }
			>
				{ speakerPosts.map( ( post ) =>
					<div
						key={ post.slug }
						className={ classnames(
							'wordcamp-speaker',
							'wordcamp-speaker-' + decodeEntities( post.slug ),
						) }
					>
						<ItemTitle
							className="wordcamp-speaker-title"
							headingLevel={ 3 }
							title={ post.title.rendered.trim() }
							link={ post.link }
						/>

						{ show_avatars &&
							<AvatarImage
								className={ classnames( 'wordcamp-speaker-avatar-container', 'align-' + decodeEntities( avatar_align ) ) }
								name={ decodeEntities( post.title.rendered.trim() ) || '' }
								size={ avatar_size }
								url={ post.avatar_urls[ '24' ] }
								imageLink={ post.link }
							/>
						}

						{ ( 'none' !== content ) &&
							<ItemHTMLContent
								className={ classnames( 'wordcamp-speaker-content-' + decodeEntities( content ) ) }
								content={ 'full' === content ? post.content.rendered.trim() : post.excerpt.rendered.trim() }
								link={ ( 'full' === content || excerpt_more ) ? post.link : null }
								linkText={ 'full' === content ? __( 'Visit session page', 'wordcamporg' ) : __( 'Read more', 'wordcamporg' ) }
							/>
						}

						{ true === show_session &&
							<SpeakerSessions
								speaker={ post }
								tracks={ tracks }
							/>
						}
					</div>,
				) }
			</GridContentLayout>
		);
	}
}

export default SpeakersBlockContent;
