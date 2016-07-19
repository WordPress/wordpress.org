import React from 'react';

import PluginSection from '../containers/plugin-section';

export default React.createClass( {
	displayName: 'FrontPage',

	getDefaultProps() {
		return {
			"sections" : [
				{
					"path": "browse/featured/",
					"title": "Featured Plugins"
				},
				{
					"path": "browse/popular/",
					"title": "Popular Plugins"
				},
				{
					"path": "browse/beta/",
					"title": "Beta Plugins"
				}
			]
		};
	},

	render() {
		return (
			<div>
				{ this.props.sections.map( section => <PluginSection key={ section.path } section={ section } /> )}
			</div>
		)
	}
} );
