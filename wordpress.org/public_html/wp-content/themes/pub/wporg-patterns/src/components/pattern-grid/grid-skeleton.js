/**
 * WordPress dependencies
 */
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Skeleton, SkeletonWrapper } from '../skeleton';
import IconHeartFilled from '../icons/heart-filled';
import getCardFrameHeight from '../../utils/get-card-frame-height';

const CardSkeleton = () => {
	const [ height, setHeight ] = useState();
	const wrapper = useRef();

	useEffect( () => {
		if ( wrapper.current ) {
			setHeight( `${ getCardFrameHeight( wrapper.current.clientWidth ) }px` );
		}
	}, [ wrapper ] );

	return (
		<div ref={ wrapper }>
			<SkeletonWrapper>
				<Skeleton height={ height } />
				<Skeleton height="14px" marginTop="1rem" />

				<SkeletonWrapper style={ { flexDirection: 'row', marginTop: '0.5rem', alignItems: 'center' } }>
					<Skeleton height="18px" width="18px" marginRight="0.5rem" borderRadius="32px" />
					<Skeleton height="12px" width="100px" marginRight="0.75rem" />
					<IconHeartFilled
						width="16px"
						height="16px"
						fill="#787c82"
						className="pattern-favorite-button__filled"
					/>
					<Skeleton height="12px" width="32px" marginLeft="0.5rem" />
				</SkeletonWrapper>
			</SkeletonWrapper>
		</div>
	);
};

const GridSkeleton = ( { length = 6 } ) =>
	Array( length )
		.fill()
		.map( ( val, idx ) => <CardSkeleton key={ idx } /> );

export default GridSkeleton;
