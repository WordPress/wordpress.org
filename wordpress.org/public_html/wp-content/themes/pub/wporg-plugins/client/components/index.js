/**
 * External dependencies.
 */
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import { localize } from 'i18n-calypso';

/**
 * Internal dependencies.
 */
import PluginDirectory from './plugin-directory';

export default withRouter( localize( connect(
	( state, { translate } ) => ( {
		widgets: [
			/* eslint-disable max-len */
			{
				title: translate( 'Add Your Plugin' ),
				text: translate( 'The WordPress Plugin Directory is the largest directory of free and open source WordPress plugins. Find out how to host your plugin on WordPress.org.' ),
			},
			{
				title: translate( 'Create a Plugin' ),
				text: translate( 'Building a plugin has never been easier. Read through the Plugin Developer Handbook to learn all about WordPress plugin development.' ),
			},
			{
				title: translate( 'Stay Up-to-Date' ),
				text: translate( 'Plugin development is constantly changing with each new WordPress release. Keep up with the latest changes by following the Plugin Review Team’s blog.' ),
			},
			/* eslint-enable max-len */
		],
	} ),
)( PluginDirectory ) ) );
