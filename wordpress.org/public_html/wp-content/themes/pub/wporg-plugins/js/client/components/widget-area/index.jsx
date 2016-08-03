import React from 'react';

export default React.createClass( {
	displayName: 'WidgetArea',

	render() {
		return (
			<aside id="secondary" className="widget-area" role="complementary">
				{ this.props.children }
			</aside>
		)
	}
} );
