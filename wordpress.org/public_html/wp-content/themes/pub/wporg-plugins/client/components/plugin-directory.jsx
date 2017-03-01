/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';

/**
 * Internal dependencies.
 */
import TextWidget from 'components/widget-area/widgets/text';
import WidgetArea from 'components/widget-area';

export const PluginDirectory = ( { header, main, router, widgets } ) => (
	<div>
		{ header }
		{ main }
		{ router.isActive( '/', true ) &&
			<WidgetArea>
				{ widgets.map( ( widget ) =>
					<TextWidget key={ widget.title } widget={ widget } />
				) }
			</WidgetArea>
		}
	</div>
);

PluginDirectory.propTypes = {
	header: PropTypes.object,
	main: PropTypes.object,
	router: PropTypes.object,
	widgets: PropTypes.arrayOf( PropTypes.object ),
};

PluginDirectory.defaultProps = {
	header: {},
	main: {},
	router: {},
	widgets: [],
};

export default PluginDirectory;
