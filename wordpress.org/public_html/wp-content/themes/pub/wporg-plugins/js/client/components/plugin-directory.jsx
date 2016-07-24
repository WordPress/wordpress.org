import React from 'react';
import { IndexLink } from 'react-router';

/**
 * Internal dependencies.
 */
import SiteHeader from '../containers/site-header';
import SiteMain from './site-main';

export default React.createClass( {
	displayName: 'PluginDirectory',

	render() {
		return (
			<div>
				{ this.props.header }
				{ this.props.main }
			</div>
		)
	}
} );
