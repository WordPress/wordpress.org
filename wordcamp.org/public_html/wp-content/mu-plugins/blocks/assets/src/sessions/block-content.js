/**
 * External dependencies
 */
import { get } from 'lodash';
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { Disabled } = wp.components;
const { Component } = wp.element;
const { decodeEntities } = wp.htmlEntities;
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import { ItemTitle, ItemHTMLContent } from '../shared/block-content';
import { tokenSplit, arrayTokenReplace, intersperse, listify } from '../shared/i18n';
import GridContentLayout from '../shared/grid-layout/block-content';
import FeaturedImage from '../shared/featured-image';

function SessionSpeakers( { session } ) {
	let speakerData = get( session, '_embedded.speakers', [] );

	speakerData = speakerData.map( ( speaker ) => {
		let { link = '', title = {} } = speaker;
		title = title.rendered || __( 'Unnamed', 'wordcamporg' );

		if ( ! link ) {
			return decodeEntities( title.trim() );
		}

		return (
			<a
				key={ link }
				href={ link }
			>
				{ decodeEntities( title.trim() ) }
			</a>
		);
	} );

	const speakers = arrayTokenReplace(
		/* translators: %s is a list of names. */
		tokenSplit( __( 'Presented by %s', 'wordcamporg' ) ),
		[ listify( speakerData ) ]
	);

	return (
		<div className="wordcamp-item-meta wordcamp-session-speakers">
			{ speakers }
		</div>
	);
}

function SessionDetails( { session, show_meta, show_category } ) {
	let meta, metaContent, category;
	const terms = get( session, '_embedded[\'wp:term\']', [] ).flat();

	if ( show_meta ) {
		if ( session.session_track.length ) {
			const [ firstTrack ] = terms.filter( ( term ) => {
				return 'wcb_track' === term.taxonomy;
			} );

			metaContent = arrayTokenReplace(
				/* translators: 1: A date; 2: A time; 3: A location; */
				tokenSplit( __( '%1$s at %2$s in %3$s', 'wordcamporg' ) ),
				[
					decodeEntities( session.session_date_time.date ),
					decodeEntities( session.session_date_time.time ),
					(
						<span className={ classnames( 'wordcamp-session-track', 'wordcamp-session-track-' + decodeEntities( firstTrack.slug.trim() ) ) }>
							{ decodeEntities( firstTrack.name.trim() ) }
						</span>
					),
				]
			);
		} else {
			metaContent = arrayTokenReplace(
				/* translators: 1: A date; 2: A time; */
				tokenSplit( __( '%1$s at %2$s', 'wordcamporg' ) ),
				[
					decodeEntities( session.session_date_time.date ),
					decodeEntities( session.session_date_time.time ),
				]
			);
		}

		meta = (
			<div className="wordcamp-session-time-location">
				{ metaContent }
			</div>
		);
	}

	if ( show_category && session.session_category.length ) {
		/* translators: used between list items, there is a space after the comma */
		const separator = __( ', ', 'wordcamporg' );
		const categories = terms
			.filter( ( term ) => {
				return 'wcb_session_category' === term.taxonomy;
			} )
			.map( ( term ) => {
				return (
					<span
						key={ term.slug }
						className={ classnames( 'wordcamp-session-category', 'wordcamp-session-category-' + decodeEntities( term.slug ) ) }
					>
						{ decodeEntities( term.name.trim() ) }
					</span>
				);
			} );

		category = (
			<div className="wordcamp-session-categories">
				{ intersperse( categories, separator ) }
			</div>
		);
	}

	return (
		<div className="wordcamp-item-meta wordcamp-session-details">
			{ meta }
			{ category }
		</div>
	);
}

class SessionsBlockContent extends Component {
	hasSpeaker( session ) {
		return get( session, '_embedded.speakers', [] ).length > 0;
	}

	render() {
		const { attributes, sessionPosts } = this.props;
		const { show_speaker, show_images, image_align, featured_image_width, content, excerpt_more, show_meta, show_category } = attributes;

		return (
			<GridContentLayout { ...this.props } >
				{ sessionPosts.map( ( post ) =>
					<div
						key={ post.slug }
						className={ classnames(
							'wordcamp-block-post-list-item',
							'wordcamp-session',
							'wordcamp-session-' + decodeEntities( post.slug ),
							'wordcamp-clearfix'
						) }
					>
						<ItemTitle
							className="wordcamp-session-title"
							headingLevel={ 3 }
							title={ post.title.rendered.trim() }
							link={ post.link }
						/>

						{ show_speaker && this.hasSpeaker( post ) &&
							<SessionSpeakers session={ post } />
						}

						{ show_images &&
							<FeaturedImage
								className={ classnames( 'wordcamp-session-image-container', 'align-' + decodeEntities( image_align ) ) }
								wpMediaDetails={ get( post, '_embedded.wp:featuredmedia[0].media_details.sizes', {} ) }
								alt={ decodeEntities( post.title.rendered ) }
								width={ Number( featured_image_width ) }
								imageLink={ post.link }
							/>
						}

						{ 'none' !== content &&
							<ItemHTMLContent
								className={ classnames( 'wordcamp-session-content-' + decodeEntities( content ) ) }
								content={ 'full' === content ? post.content.rendered.trim() : post.excerpt.rendered.trim() }
								link={ ( 'full' === content || excerpt_more ) ? post.link : null }
								linkText={ 'full' === content ? __( 'Visit session page', 'wordcamporg' ) : __( 'Read more', 'wordcamporg' ) }
							/>
						}

						{ ( show_meta || show_category ) &&
							<SessionDetails
								session={ post }
								show_meta={ show_meta }
								show_category={ show_category }
							/>
						}
					</div>
				) }
			</GridContentLayout>
		);
	}
}

export default SessionsBlockContent;
