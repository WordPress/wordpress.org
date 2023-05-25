/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */
import Canvas from './canvas';
import CopyPatternButton from '../copy-pattern-button';
import FavoriteButton from '../favorite-button';
import FavoriteButtonSmall from '../favorite-button/small';
import ManageOptions from '../manage-options';

function getStatusLabel( pattern ) {
	switch ( pattern.status ) {
		case 'pending-review': // Potential spam.
		case 'pending':
			return __( 'Pending', 'wporg-patterns' );
		case 'draft':
			return __( 'Draft', 'wporg-patterns' );
		case 'unlisted':
			return __( 'Declined', 'wporg-patterns' );
	}
	return '';
}

function PatternThumbnail( { pattern, showAvatar, showOptions } ) {
	const statusLabel = getStatusLabel( pattern );
	return (
		<div className="pattern-grid__pattern">
			<div className="pattern-grid__pattern-frame">
				<a href={ pattern.link } rel="bookmark">
					<span className="screen-reader-text">{ decodeEntities( pattern.title.rendered ) }</span>
					<Canvas
						useMShot={ 'publish' === pattern.status }
						url={ addQueryArgs( pattern.link, {
							view: true,
							modified: pattern.modified_gmt,
							version: wporgPatternsData.thumbnailVersion,
						} ) }
					/>
				</a>
				{ statusLabel ? (
					<div className={ `pattern-grid__status is-${ pattern.status }` }>
						<span>{ statusLabel }</span>
					</div>
				) : null }
				<span className="pattern-grid__actions">
					<FavoriteButton showLabel={ false } patternId={ pattern.id } />
					{ showOptions && <ManageOptions isSmall patternId={ pattern.id } /> }
					<CopyPatternButton isSmall={ true } content={ pattern.pattern_content } />
				</span>
			</div>

			<h2 className="pattern-grid__title">
				<a href={ pattern.link }>{ decodeEntities( pattern.title.rendered ) }</a>
			</h2>
			<p className="pattern-grid__meta">
				{ showAvatar && pattern.author_meta ? (
					<a href={ pattern.author_meta.url } className="pattern-grid__author-avatar">
						<img alt="" src={ pattern.author_meta.avatar } />
						{ pattern.author_meta.name }
					</a>
				) : null }
				{ pattern.favorite_count > 0 ? (
					<FavoriteButtonSmall
						className="pattern-grid__favorite-count"
						patternId={ pattern.id }
						label={
							<>
								<span className="screen-reader-text">
									{ sprintf(
										/* translators: %s is the favorite count for a pattern. */
										_n(
											'Favorited %s times',
											'Favorited %s times',
											pattern.favorite_count,
											'wporg-patterns'
										),
										pattern.favorite_count
									) }
								</span>
								<span aria-hidden>{ pattern.favorite_count }</span>
							</>
						}
					/>
				) : null }
			</p>
		</div>
	);
}

export default PatternThumbnail;
