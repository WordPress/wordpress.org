import React from 'react';

export default React.createClass( {
	displayName: 'SiteMain',

	render() {
		return (
			<main id="main" className="site-main" role="main">
				{ this.props.children }
			</main>
		)
	}
} );
