/* global FileReader */
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import getCardFrameHeight from '../../utils/get-card-frame-height';
import useInterval from '../../hooks/use-interval';

/**
 * Module constants
 */
const MAX_ATTEMPTS = 10;
const RETRY_DELAY = 2000;
const VIEWPORT_WIDTH = 1200;
const IMAGE_WIDTH = 600;

/**
 *
 * We are using mShots which is a project that generates a screenshot from a webpage.
 * Since the screenshot generation takes some time, for never seen websites,
 * we need to do some custom handling.
 *
 * @param {Object}  props
 * @param {string}  props.alt       The alt text for the screenshot image.
 * @param {string}  props.className Any extra class names for the wrapper div.
 * @param {boolean} props.isReady   Whether we should start try to show the image.
 * @param {Object}  props.style     Styles for the wrapper div.
 * @param {string}  props.src       The url of the page to screenshot.
 * @return {Object} React component
 */
export default function ( { alt, className, isReady = false, src, style } ) {
	const fullUrl = addQueryArgs( `https://s0.wp.com/mshots/v1/${ encodeURIComponent( src ) }`, {
		w: IMAGE_WIDTH, // eslint-disable-line id-length
		vpw: VIEWPORT_WIDTH,
		vph: getCardFrameHeight( VIEWPORT_WIDTH ),
	} );

	const [ attempts, setAttempts ] = useState( 0 );
	const [ hasLoaded, setHasLoaded ] = useState( false );
	const [ hasError, setHasError ] = useState( false );
	const [ base64Img, setBase64Img ] = useState( '' );
	const [ shouldRetry, setShouldRetry ] = useState( false );

	// We don't want to keep trying infinitely.
	const hasAborted = attempts > MAX_ATTEMPTS;

	// The derived loading state
	const isLoading = isReady && ! hasLoaded && ! hasAborted && ! hasError;

	/**
	 * Since we already made the request, we'll use the response to be frugal.
	 *
	 * @param {string} res
	 */
	const convertResponseToBase64 = async ( res ) => {
		const blob = await res.blob();

		const reader = new FileReader();
		reader.onload = ( event ) => {
			setBase64Img( event.target.result );
		};
		reader.readAsDataURL( blob );
	};

	const fetchImage = async () => {
		try {
			const res = await fetch( fullUrl );

			if ( res.redirected ) {
				setShouldRetry( true );
			} else if ( res.status === 200 && ! res.redirected ) {
				await convertResponseToBase64( res );

				setHasLoaded( true );
				setShouldRetry( false );
			} else {
				setAttempts( attempts + 1 );
			}
		} catch ( error ) {
			setHasError( true );
			setShouldRetry( false );
		}
	};

	useEffect( () => {
		( async () => await fetchImage() )();
	}, [] );

	/**
	 * The Snapshot service will redirect when its generating an image.
	 * We want to continue requesting the image until it doesn't redirect.
	 */
	useInterval(
		async () => {
			await fetchImage();
		},
		shouldRetry ? RETRY_DELAY : null
	);

	if ( ! isReady ) {
		return null;
	}

	if ( isLoading ) {
		return (
			<div className={ `${ className } is-loading` } style={ style }>
				<Spinner style={ { width: '32px', height: '32px' } } />
				<span className="screen-reader-text">{ __( 'Loading', 'wporg-patterns' ) }</span>
			</div>
		);
	}

	if ( hasError || hasAborted ) {
		return (
			<div className={ `${ className } has-error` } style={ style }>
				{ __( 'Error', 'wporg-patterns' ) }
			</div>
		);
	}

	return (
		<div className={ className }>
			<img src={ base64Img } alt={ alt } style={ { ...style, verticalAlign: 'middle' } } />
		</div>
	);
}
