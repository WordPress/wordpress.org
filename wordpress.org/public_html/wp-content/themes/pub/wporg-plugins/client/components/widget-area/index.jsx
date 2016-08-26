import React from 'react';

export default React.createClass( {
	displayName: 'WidgetArea',

	render() {
		let classNames = [ 'widget-area' ];

		if ( this.props.router.isActive( '/', true ) ) {
			classNames.push( 'home' );
		}

		return (
			<aside id="secondary" className={ classNames.join( ' ' ) } role="complementary">
				{ this.props.children }
			</aside>
		)
	}
} );
