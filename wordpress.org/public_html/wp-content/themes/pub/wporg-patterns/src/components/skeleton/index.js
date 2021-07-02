const SkeletonWrapper = ( { style, children } ) => {
	return (
		<span className="pattern-skeleton__container" style={ style }>
			{ children }
		</span>
	);
};

const Skeleton = ( styles ) => {
	return <span className="pattern-skeleton" style={ styles }></span>;
};

export { SkeletonWrapper, Skeleton };
