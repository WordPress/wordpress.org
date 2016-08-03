import React from 'react';

export default React.createClass( {
	displayName: 'TextWidget',

	render() {
		return (
			<div className="widget widget_text">
				<h4 className="widget-title">{ this.props.widget.title }</h4>
				<div className="textwidget">{ this.props.widget.text }</div>
			</div>
		)
	}
} );
