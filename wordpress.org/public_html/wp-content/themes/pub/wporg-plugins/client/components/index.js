import { connect } from 'react-redux';
import { withRouter } from 'react-router';

import PluginDirectory from './plugin-directory';

const mapStateToProps = () => ( {
	widgets: [
		{
			title: 'Add Your Plugin',
			text: 'The WordPress Plugin Directory is the largest directory of free and open source WordPress plugins. Find out how to host your plugin on WordPress.org.'
		},
		{
			title: 'Create a Plugin',
			text: 'Building a plugin has never been easier. Read through the Plugin Developer Handbook to learn all about WordPress plugin development.'
		},
		{
			title: 'Stay Up-to-Date',
			text: 'Plugin development is constantly changing with each new WordPress release. Keep up with the latest changes by following the Plugin Review Team’s blog.'
		},
	]
} );

export default withRouter( connect( mapStateToProps )( PluginDirectory ) );
