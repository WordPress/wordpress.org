/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import getCardFrameHeight from '../../utils/get-card-frame-height';
import useInView from '../../hooks/in-view';
import Screenshot from './screenshot';

export default function ( { alt, url } ) {
	const wrapperRef = useRef();
	const isVisible = useInView( { element: wrapperRef } );
	const [ frameHeight, setFrameHeight ] = useState( '1px' );
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
		</div>
	);
}
