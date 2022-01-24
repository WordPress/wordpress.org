/**
 * WordPress dependencies
 */
import { isRTL } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import Iframe from '../iframe';
import getCardFrameHeight from '../../utils/get-card-frame-height';
import useInView from '../../hooks/in-view';

const VIEWPORT_WIDTH = 800;

function PatternThumbnail( { className, html } ) {
	const wrapperRef = useRef();
	const [ frameHeight, setFrameHeight ] = useState( '1px' );
	const [ frameScale, setFrameScale ] = useState( 0.3125 );
	const isVisible = useInView( { element: wrapperRef } );
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
				setFrameScale( wrapperRef.current.clientWidth / VIEWPORT_WIDTH );
			} catch ( err ) {}
		};

		handleOnResize();

		window.addEventListener( 'resize', handleOnResize );

		return () => {
			window.addEventListener( 'resize', handleOnResize );
		};
	}, [] );

	const style = {
		border: 'none',
		width: `${ VIEWPORT_WIDTH }px`,
		maxWidth: 'none',
		height: `${ getCardFrameHeight( VIEWPORT_WIDTH ) }px`,
		transform: `scale(${ frameScale })`,
		transformOrigin: isRTL() ? 'top right' : 'top left',
		pointerEvents: 'none',
	};

	return (
		<div
			className={ className }
			ref={ wrapperRef }
			style={ {
				height: frameHeight,
			} }
			tabIndex="-1"
		>
			<Iframe
				className="pattern-grid__preview-iframe"
				style={ style }
				bodyStyle={ 'overflow: hidden;' }
				headHTML={ window.__editorStyles.html }
			>
				<div
					dangerouslySetInnerHTML={ {
						__html: shouldLoad ? html : '',
					} }
				/>
			</Iframe>
		</div>
	);
}

export default PatternThumbnail;
