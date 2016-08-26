import React from 'react';

export default React.createClass( {
	displayName: 'DownloadButton',

	render() {
		return (
			<span>
				<a className="plugin-download button download-button button-large" href={ this.props.plugin.download_link } itemProp="downloadUrl">
					Download
				</a>
				<meta itemProp="softwareVersion" content={ this.props.plugin.version } />
				<meta itemProp="fileFormat" content="application/zip" />
			</span>
		)
	}
} );
