/**
 * WordPress dependencies
 */
import { Disabled } from '@wordpress/components';

/**
 * Internal dependencies
 */
import FavoriteButton from '../favorite-button';
import CopyPatternButton from '../copy-pattern-button';
import Canvas from './canvas';

function PatternThumbnail( { pattern } ) {
	return (
		<div className="pattern-grid__pattern">
			<a href={ pattern.link } rel="bookmark">
				<span className="screen-reader-text">{ pattern.title.rendered }</span>
				<Disabled>
					<Canvas className="pattern-grid__preview" html={ pattern.content.rendered } />
				</Disabled>
			</a>
			<div className="pattern-grid__actions">
				<h2 className="pattern-grid__title">{ pattern.title.rendered }</h2>
				<FavoriteButton showLabel={ false } patternId={ pattern.id } />
				<CopyPatternButton isSmall={ true } content={ pattern.pattern_content } />
			</div>
		</div>
	);
}

export default PatternThumbnail;
