/**
 * External dependencies
 */
import { useEffect, useRef } from '@wordpress/element';
import { ifViewportMatches } from '@wordpress/viewport';

const updateIndicatorLocation = ( container, { top, left, width, height } ) => {
	if ( ! container ) {
		return;
	}

	container.style.backgroundPositionX = `${ left }px`;
	container.style.backgroundSize = `${ width }px ${ top + height }px`;
};

const DefaultMenu = ( { path, options, onClick, isLoading } ) => {
	const containerRef = useRef( null );
	const activeRef = useRef( null );

	useEffect( () => {
		if ( ! containerRef || ! containerRef.current || ! activeRef || ! activeRef.current ) {
			return;
		}

		updateIndicatorLocation( containerRef.current, {
			top: activeRef.current.offsetTop,
			left: activeRef.current.offsetLeft,
			width: activeRef.current.offsetWidth,
			height: activeRef.current.offsetHeight,
		} );
	} );

	if ( ! isLoading && ! options.length ) {
		return null;
	}

	return (
		<ul className={ `category-menu ${ isLoading ? 'category-menu--is-loading' : '' } ` } ref={ containerRef }>
			{ options.map( ( i ) => {
				const isActive = path === i.value;

				return (
					<li key={ i.value }>
						<a
							className={ isActive ? 'category-menu--is-active' : '' }
							href={ i.value }
							ref={ isActive ? activeRef : null }
							onClick={ onClick }
						>
							{ i.label }
						</a>
					</li>
				);
			} ) }
		</ul>
	);
};

// Will only render if the viewport is >= medium
export default ifViewportMatches( '>= medium' )( DefaultMenu );
