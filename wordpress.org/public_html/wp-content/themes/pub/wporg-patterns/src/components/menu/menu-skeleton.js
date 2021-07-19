/**
 * Internal dependencies
 */
import { Skeleton, SkeletonWrapper } from '../skeleton';

/**
 * Module constants
 */
const PLACEHOLDER_COUNT = 7;

const MenuSkeleton = () => {
	return (
		<SkeletonWrapper className="pattern-menu" style={ { flexDirection: 'row' } }>
			{ Array( PLACEHOLDER_COUNT )
				.fill()
				.map( ( val, idx ) => (
					<Skeleton key={ idx } height="1.25rem" width="5rem" marginRight="1rem" />
				) ) }
		</SkeletonWrapper>
	);
};

export default MenuSkeleton;
