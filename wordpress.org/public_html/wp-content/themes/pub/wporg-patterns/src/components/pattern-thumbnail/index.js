/**
 * WordPress dependencies
 */
import { speak } from '@wordpress/a11y';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Disabled } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import FavoriteButton from '../favorite-button';
import Canvas from './canvas';
import { copyToClipboard } from '../../utils';

function PatternThumbnail( { pattern } ) {
	const [ copied, setCopied ] = useState( false );

	const handleCopy = () => {
		const result = copyToClipboard( pattern.pattern_content );

		setCopied( result );
	};

	useEffect( () => {
		if ( ! copied ) {
			return;
		}

		speak(
			sprintf(
				/* translators: %s: pattern title. */
				__( 'Copied %s pattern to clipboard.', 'wporg-patterns' ),
				pattern.title.rendered
			)
		);

		const timer = setTimeout( () => setCopied( false ), 20000 );
		return () => {
			clearTimeout( timer );
		};
	}, [ copied ] );

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
				<Button className="pattern__copy-button is-small" isPrimary onClick={ handleCopy }>
					{ copied ? __( 'Copied!', 'wporg-patterns' ) : __( 'Copy', 'wporg-patterns' ) }
				</Button>
			</div>
		</div>
	);
}

export default PatternThumbnail;
