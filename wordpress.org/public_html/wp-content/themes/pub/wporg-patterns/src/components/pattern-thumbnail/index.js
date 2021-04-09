/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Disabled, Tooltip } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import IconHeartOutline from '../icons/heart-outline';
import IconHeartFilled from '../icons/heart-filled';
import Canvas from './canvas';

function PatternThumbnail( { pattern } ) {
	// @todo Implement a real favoriting process.
	const [ isFavorite, setFavorite ] = useState( Math.random() < 0.3 );

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
				<Tooltip
					text={
						isFavorite
							? __( 'Remove pattern from favorites', 'wporg-patterns' )
							: __( 'Favorite pattern', 'wporg-patterns' )
					}
				>
					<button
						className={
							'button button-link pattern__favorite-button' + ( isFavorite ? ' is-favorited' : '' )
						}
						onClick={ () => setFavorite( ! isFavorite ) }
					>
						<IconHeartFilled className="pattern__favorite-filled" />
						<IconHeartOutline className="pattern__favorite-outline" />
					</button>
				</Tooltip>
				<Button className="pattern__copy-button is-small" isPrimary>
					{ __( 'Copy', 'wporg-patterns' ) }
				</Button>
			</div>
		</div>
	);
}

export default PatternThumbnail;
