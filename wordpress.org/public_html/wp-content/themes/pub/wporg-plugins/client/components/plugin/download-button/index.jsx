/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { connect } from 'react-redux';
import { identity } from 'lodash';
import { localize } from 'i18n-calypso';

/**
 * Internal dependencies.
 */
import { getPlugin } from 'state/selectors';

export const DownloadButton = ( { plugin, translate } ) => (
	<span>
		<a
			className="plugin-download button download-button button-large"
			href={ plugin.download_link }
			itemProp="downloadUrl"
		>
			{ translate( 'Download' ) }
		</a>
		<meta itemProp="softwareVersion" content={ plugin.version } />
		<meta itemProp="fileFormat" content="application/zip" />
	</span>
);

DownloadButton.propTypes = {
	plugin: PropTypes.object,
	translate: PropTypes.func,
};

DownloadButton.defaultProps = {
	plugin: {},
	translate: identity,
};

export default connect(
	( state ) => ( {
		plugin: getPlugin( state ),
	} ),
)( localize( DownloadButton ) );
