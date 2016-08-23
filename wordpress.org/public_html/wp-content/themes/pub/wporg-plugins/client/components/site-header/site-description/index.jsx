import React from 'react';
import { IndexLink } from 'react-router';

export default React.createClass( {
	displayName: 'SiteDescription',

	render() {
		if ( this.props.isHome ) {
			return <p className="site-description">Extend your WordPress experience with 40,000 plugins.</p>;
		} else {
			return <span />;
		}
	}
} );
