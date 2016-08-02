import React from 'react';

import ContentNone from 'components/content-none';
import PluginCard from 'components/plugin-card';

export default React.createClass( {
	displayName: 'Search',

	render() {
		if ( ! this.props.plugins ) {
			return <div>{ 'Loading...' }</div>;
		}

		if ( 0 === this.props.plugins.length ) {
			return <ContentNone { ...this.props } />;
		}

		return (
			<div>
				<header className="page-header">
					<h1 className="page-title">Search results for: <strong>{ this.props.params.searchTerm }</strong></h1>
					<div className="taxonomy-description"></div>
				</header>
				{ this.props.plugins.map( slug =>
					<PluginCard key={ slug } slug={ slug } />
				) }
			</div>
		);
	}
} );
