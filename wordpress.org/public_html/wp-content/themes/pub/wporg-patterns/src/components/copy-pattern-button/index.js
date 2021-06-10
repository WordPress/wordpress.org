/**
 * External dependencies
 */
import classnames from 'classnames';
import { noop } from 'lodash';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { Button } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { copyToClipboard } from '../../utils';

const CopyPatternButton = ( { isSmall = false, onSuccess = noop, content } ) => {
	const [ copied, setCopied ] = useState( false );

	if ( ! content ) {
		// Grab the pattern markup from hidden input
		const blockData = document.getElementById( 'block-data' );
		content = JSON.parse( decodeURIComponent( blockData.value ) );
	}

	const handleClick = ( { target } ) => {
		const success = copyToClipboard( content );

		setCopied( success );

		// Make sure we reset focus in case it was lost in the 'copy' command.
		target.focus();

		if ( success ) {
			onSuccess();
		} else {
			// TODO Handle error case
		}
	};

	useEffect( () => {
		if ( ! copied ) {
			return;
		}

		speak( __( 'Copied pattern to clipboard.', 'wporg-patterns' ) );

		const timer = setTimeout( () => setCopied( false ), 20000 );
		return () => {
			clearTimeout( timer );
		};
	}, [ copied ] );

	let label = __( 'Copy Pattern', 'wporg-patterns' );
	if ( isSmall ) {
		label = copied ? __( 'Copied', 'wporg-patterns' ) : __( 'Copy', 'wporg-patterns' );
	}

	const classes = classnames( {
		'pattern-copy-button': true,
		'is-small-label': isSmall,
	} );

	return (
		<Button className={ classes } isPrimary onClick={ handleClick }>
			{ label }
		</Button>
	);
};

export default CopyPatternButton;
