/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';

export const TextWidget = ( { widget } ) => (
	<div className="widget widget_text">
		<h3 className="widget-title">{ widget.title }</h3>
		<div className="textwidget">{ widget.text }</div>
	</div>
);

TextWidget.propTypes = {
	widget: PropTypes.object,
};

TextWidget.defaultProps = {
	widget: {},
};

export default TextWidget;
