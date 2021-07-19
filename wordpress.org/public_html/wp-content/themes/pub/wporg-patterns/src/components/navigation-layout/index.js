const MenuLayout = ( { primary, secondary } ) => {
	return (
		<div className="pattern-navigation-layout">
			{ primary && <div className="pattern-navigation-layout__primary">{ primary }</div> }
			{ secondary && <div className="pattern-navigation-layout__secondary">{ secondary }</div> }
		</div>
	);
};

export default MenuLayout;
