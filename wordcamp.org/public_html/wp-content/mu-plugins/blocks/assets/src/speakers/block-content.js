/**
 * External dependencies
 */
import { get } from 'lodash';
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { Disabled } = wp.components;
const { Component, Fragment, RawHTML } = wp.element;
const { decodeEntities } = wp.htmlEntities;
const { __, _n, sprintf } = wp.i18n;

/**
 * Internal dependencies
 */
import AvatarImage from '../shared/avatar';
import './block-content.scss';

class SpeakersBlockContent extends Component {
	render() {
		const { attributes, speakerPosts, tracks } = this.props;
		const {
			layout, grid_columns, className,
			show_avatars, avatar_size, avatar_align,
			content, excerpt_more, show_session,
		} = attributes;

		const containerClasses = [
			'wordcamp-speakers-block',
			'layout-' + layout,
			className,
		];

		if ( 'grid' === layout ) {
			containerClasses.push( 'grid-columns-' + Number( grid_columns ) );
		}

		return (
			<ul className={ classnames( containerClasses ) }>
				{ speakerPosts.map( ( post ) =>
					<li
						key={ post.slug }
						className={ classnames(
							'wordcamp-speaker',
							'wordcamp-speaker-' + decodeEntities( post.slug ),
							'wordcamp-clearfix'
						) }
					>
						<h3 className="wordcamp-speaker-name-heading">
							<Disabled>
								<a href={ post.link }>
									{ decodeEntities( post.title.rendered.trim() ) || __( '(Untitled)', 'wordcamporg' ) }
								</a>
							</Disabled>
						</h3>

						{ show_avatars &&
							<div className={ classnames( 'wordcamp-speaker-avatar-container', 'align-' + decodeEntities( avatar_align ) ) }>
								<Disabled>
									<a href={ post.link } className="wordcamp-speaker-avatar-link">
										<AvatarImage
											className="wordcamp-speaker-avatar"
											name={ decodeEntities( post.title.rendered.trim() ) || '' }
											size={ avatar_size }
											url={ post.avatar_urls[ '24' ] }
										/>
									</a>
								</Disabled>
							</div>
						}

						{ ( 'none' !== content ) &&
							<div className={ classnames( 'wordcamp-speaker-content', 'wordcamp-speaker-content-' + decodeEntities( content ) ) }>
								{ 'full' === content &&
									<Disabled>
										<RawHTML children={ post.content.rendered.trim() } />
										<p className="wordcamp-speaker-permalink">
											<a href={ post.link }>
												{ __( 'Visit speaker page', 'wordcamporg' ) }
											</a>
										</p>
									</Disabled>
								}
								{ 'excerpt' === content &&
									<Disabled>
										<RawHTML children={ post.excerpt.rendered.trim() } />
										{ excerpt_more &&
											<p className="wordcamp-speaker-permalink">
												<a href={ post.link }>
													{ __( 'Read more', 'wordcamporg' ) }
												</a>
											</p>
										}
									</Disabled>
								}
							</div>
						}

						{ true === show_session && post._embedded.sessions &&
							<Fragment>
								<h4 className="wordcamp-speaker-session-heading">
									{ _n( 'Session', 'Sessions', post._embedded.sessions.length, 'wordcamporg' ) }
								</h4>

								<ul className="wordcamp-speaker-session-list">
									{ post._embedded.sessions.map( ( session ) =>
										<li
											key={ session.slug }
											className="wordcamp-speaker-session-content"
										>
											<Disabled>
												<a
													className="wordcamp-speaker-session-link"
													href={ session.link }
												>
													{ decodeEntities( session.title.rendered.trim() ) || __( '(Untitled)', 'wordcamporg' ) }
												</a>
												<br />
												<span className="wordcamp-speaker-session-info">
													{ session.session_track.length &&
														sprintf(
															/* translators: 1: A date; 2: A time; 3: A location; */
															__( '%1$s at %2$s in %3$s', 'wordcamporg' ),
															session.session_date_time.date,
															session.session_date_time.time,
															get( tracks.find( ( value ) => {
																const [ firstTrackId ] = session.session_track;
																return parseInt( value.id ) === firstTrackId;
															} ), 'name' )
														)
													}
													{ ! session.session_track.length &&
														sprintf(
															/* translators: 1: A date; 2: A time; */
															__( '%1$s at %2$s', 'wordcamporg' ),
															session.session_date_time.date,
															session.session_date_time.time
														)
													}
												</span>
											</Disabled>
										</li>
									) }
								</ul>
							</Fragment>
						}
					</li>,
				) }
			</ul>
		);
	}
}

export default SpeakersBlockContent;
