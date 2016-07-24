import React from 'react';

/**
 * Internal dependencies.
 */
import ContentNone from 'components/content-none';

export default React.createClass( {
	displayName: 'ArchiveBrowse',

	render() {
		let content = <ContentNone { ...this.props } />;

		if ( false /*this.props.plugins.length*/ ) {
			content = <header className="page-header">
				<h1 className="page-title"></h1>
				<div className="taxonomy-description"></div>
			</header>

			content += this.props.plugins.map( plugin => <PluginCard key={ plugin.slug } plugin={ plugin } /> );
		}

		return (
			<div>{ content }</div>
		)
	}
} );
