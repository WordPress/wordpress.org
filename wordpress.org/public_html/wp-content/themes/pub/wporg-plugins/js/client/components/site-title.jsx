import React from 'react';
import { IndexLink } from 'react-router';

export default React.createClass( {
	displayName: 'SiteTitle',

	render() {
		if ( this.props.isHome ) {
			return <h1 className="site-title"><IndexLink to="/" rel="home">Plugins</IndexLink></h1>;
		} else {
			return <p className="site-title"><IndexLink to="/" rel="home">Plugins</IndexLink></p>;
		}
	}
} );
