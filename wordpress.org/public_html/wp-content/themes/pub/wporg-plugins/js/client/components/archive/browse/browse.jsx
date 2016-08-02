import React from 'react';

import ContentNone from 'components/content-none';
import PluginCard from 'components/plugin-card';

export default React.createClass( {
	displayName: 'ArchiveBrowse',

	render() {
		if ( this.props.plugins && this.props.plugins.length ) {
			return (
				<div>
					<header className="page-header">
						<h1 className="page-title">Browse: <strong>{ this.props.params.type }</strong></h1>
						<div className="taxonomy-description"></div>
					</header>
					{ this.props.plugins.map( slug =>
						<PluginCard key={ slug } slug={ slug } />
					) }
				</div>
			)
		}

		return <ContentNone { ...this.props } />;
	}
} );
