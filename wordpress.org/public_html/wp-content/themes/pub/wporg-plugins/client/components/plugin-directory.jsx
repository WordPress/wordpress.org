import React from 'react';
import { IndexLink } from 'react-router';

import TextWidget from 'components/widget-area/widgets/text';
import WidgetArea from 'components/widget-area';

export default React.createClass( {
	displayName: 'PluginDirectory',

	widgetArea() {
		return (
			<WidgetArea>
				{ this.props.widgets.map( widget =>
					<TextWidget key={ widget.title } widget={ widget } />
				) }
			</WidgetArea>
		);
	},

	render() {
		return (
			<div>
				{ this.props.header }
				{ this.props.main }
				{ this.props.router.isActive( '/', true ) ? this.widgetArea() : <div /> }
			</div>
		)
	}
} );

