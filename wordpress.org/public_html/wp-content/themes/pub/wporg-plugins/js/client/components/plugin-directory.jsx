import React from 'react';
import { IndexLink } from 'react-router';

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
