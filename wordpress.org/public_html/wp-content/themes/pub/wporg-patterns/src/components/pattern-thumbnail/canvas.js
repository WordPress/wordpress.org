/**
 * WordPress dependencies
 */
import { __, isRTL } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import getCardFrameHeight from '../../utils/get-card-frame-height';
import useInView from '../../hooks/in-view';
import Screenshot from './screenshot';

export default function ( { alt, url, useMShot = true } ) {
	const wrapperRef = useRef();
	const isVisible = useInView( { element: wrapperRef } );
	const [ frameHeight, setFrameHeight ] = useState( 1 );
	const [ frameWidth, setFrameWidth ] = useState( 1 );
	const [ shouldLoad, setShouldLoad ] = useState( false );

	useEffect( () => {
		if ( isVisible ) {
			setShouldLoad( true );
		}
	}, [ isVisible ] );

	useEffect( () => {
		const handleOnResize = () => {
			try {
				setFrameHeight( getCardFrameHeight( wrapperRef.current.clientWidth ) );
				setFrameWidth( wrapperRef.current.clientWidth );
			} catch ( err ) {}
		};

		handleOnResize();

		window.addEventListener( 'resize', handleOnResize );

		return () => {
			window.removeEventListener( 'resize', handleOnResize );
		};
	}, [ isVisible ] );

	const style = {
		border: 'none',
		width: '100%',
		maxWidth: 'none',
		height: `${ frameHeight }px`,
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
	};

	return (
		<div ref={ wrapperRef }>
			{ useMShot ? (
				<Screenshot
					className="pattern-grid__preview"
					alt={ alt || __( 'Pattern Preview', 'wporg-patterns' ) }
					style={ style }
					isReady={ shouldLoad }
					src={
						wporgPatternsData.env === 'local'
							? url.replace( wporgPatternsUrl.site, 'https://wordpress.org/patterns' )
							: url
					}
				/>
			) : (
				<div
					style={ {
						height: `${ frameHeight }px`,
						overflow: 'hidden',
					} }
				>
					<iframe
						className="pattern-grid__preview"
						title={ alt || __( 'Pattern Preview', 'wporg-patterns' ) }
						tabIndex="-1"
						style={ {
							border: 'none',
							width: `${ frameWidth * 4 }px`,
							maxWidth: 'none',
							height: `${ frameHeight * 4 }px`,
							transform: 'scale(0.25)',
							transformOrigin: isRTL() ? 'top right' : 'top left',
							pointerEvents: 'none',
						} }
						src={ shouldLoad ? url : '' }
					/>
				</div>
			) }
		</div>
	);
}
