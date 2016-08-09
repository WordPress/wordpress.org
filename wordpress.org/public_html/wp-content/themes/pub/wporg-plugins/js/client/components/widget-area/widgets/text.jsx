import React from 'react';

export default React.createClass( {
	displayName: 'TextWidget',

	render() {
		return (
			<div className="widget widget_text">
				<h3 className="widget-title">{ this.props.widget.title }</h3>
				<div className="textwidget">{ this.props.widget.text }</div>
			</div>
		)
	}
} );
