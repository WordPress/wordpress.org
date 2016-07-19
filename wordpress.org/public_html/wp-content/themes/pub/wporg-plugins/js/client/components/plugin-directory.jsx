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
				<SiteHeader />
				<SiteMain>
					{ this.props.children }
				</SiteMain>
			</div>
		)
	}
} );
