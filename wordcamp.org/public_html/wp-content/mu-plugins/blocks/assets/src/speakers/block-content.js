/**
 * External dependencies
 */
import { find, get, head } from 'lodash';
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
	static maybeAddMoreLink( content, add ) {
		if ( add ) {
			const more = sprintf(
				'<a href="#" class="wordcamp-speaker-more-link">%s</a>',
				__( 'Read more', 'wordcamporg' )
			);

			const pattern = /<\/p>$/;

			if ( Array.isArray( content.match( pattern ) ) ) {
				content = content.replace( pattern, ' ' + more + '</p>' );
			} else {
				content += ' ' + more;
			}
		}

		return content;
	}

	render() {
		const { attributes, speakerPosts, tracks } = this.props;
		const {
			layout, grid_columns, className,
			show_avatars, avatar_size, avatar_align,
			content, speaker_link, show_session,
		} = attributes;

		let containerClasses = [
			'wordcamp-speakers-block',
			'layout-' + layout,
			className,
		];

		if ( 'grid' === layout ) {
			containerClasses.push( 'grid-columns-' + Number( grid_columns ) );
		}

		return (
			<ul className={ classnames( containerClasses ) }>
				{ speakerPosts.map( ( post, i ) =>
					<li
						key={ i }
						className={ classnames( 'wordcamp-speaker', 'wordcamp-speaker-' + post.slug ) }
					>
						<h3 className={ 'wordcamp-speaker-name-heading' }>
							{ decodeEntities( post.title.rendered.trim() ) || __( '(Untitled)', 'wordcamporg' ) }
						</h3>

						{ show_avatars &&
							<AvatarImage
								className={ classnames( 'wordcamp-speaker-avatar', 'align-' + avatar_align ) }
								name={ decodeEntities( post.title.rendered.trim() ) || '' }
								size={ avatar_size }
								url={ post[ 'avatar_urls' ][ '24' ] }
							/>
						}

						{ ( 'none' !== content || true === speaker_link ) &&
							<div className={ 'wordcamp-speaker-content' }>
								{ 'full' === content &&
									<Disabled>
										<RawHTML>
											{ this.constructor.maybeAddMoreLink( post.content.rendered.trim(), speaker_link ).trim() }
										</RawHTML>
									</Disabled>
								}
								{ 'excerpt' === content &&
									<Disabled>
										<RawHTML>
											{ this.constructor.maybeAddMoreLink( post.excerpt.rendered.trim(), speaker_link ).trim() }
										</RawHTML>
									</Disabled>
								}
								{ 'none' === content &&
									<Disabled>
										<RawHTML>
											{ this.constructor.maybeAddMoreLink( '', speaker_link ).trim() }
										</RawHTML>
									</Disabled>
								}
							</div>
						}

						{ true === show_session && post._embedded.sessions.length &&
							<Fragment>
								<h4 className={ 'wordcamp-speaker-session-heading' }>
									{ _n( 'Session', 'Sessions', post._embedded.sessions.length, 'wordcamporg' ) }
								</h4>

								<ul className={ 'wordcamp-speaker-session-list' }>
									{ post._embedded.sessions.map( ( session, x ) =>
										<li
											key={ x }
											className={ 'wordcamp-speaker-session-content' }
										>
											<Disabled>
												<a
													className={ 'wordcamp-speaker-session-link' }
													href={ session.link }
												>
													{ decodeEntities( session.title.rendered.trim() ) || __( '(Untitled)', 'wordcamporg' ) }
												</a>
												<br />
												<span className={ 'wordcamp-speaker-session-info' }>
													{ session.session_track.length &&
														sprintf(
															/* translators: 1: A date; 2: A time; 3: A location; */
															__( '%1$s at %2$s in %3$s', 'wordcamporg' ),
															session.session_date_time.date,
															session.session_date_time.time,
															get( find( tracks, ( value ) => { return parseInt( value.id ) === head( session.session_track ) } ), 'name' )
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
