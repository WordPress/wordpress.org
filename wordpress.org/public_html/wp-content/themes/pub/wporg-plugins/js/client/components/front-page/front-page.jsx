import React from 'react';

/**
 * Internal dependencies.
 */
import PluginSection from './plugin-section/index';

export default React.createClass( {
	displayName: 'FrontPage',

	render() {
		return (
			<div>
				{ this.props.sections.map( section => <PluginSection key={ section.path } section={ section } /> ) }
			</div>
		)
	}
} );
