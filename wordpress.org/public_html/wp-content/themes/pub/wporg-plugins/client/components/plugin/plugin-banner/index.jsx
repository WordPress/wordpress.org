/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { connect } from 'react-redux';

/**
 * Internal dependencies.
 */
import { getPlugin } from 'state/selectors';

/**
 *
 * @param {Object} plugin Plugin object.
 * @return {*} React element.
 * @constructor
 */
export const PluginBanner = ( { plugin } ) => {
	const { banners, slug } = plugin;

	if ( ! banners ) {
		return null;
	}

	const banner = banners.low || banners.high;

	if ( ! banner ) {
		return null;
	}

	return (
		<div className="entry-banner">
			<div className="plugin-banner" id={ `plugin-banner-${ slug }` } />
			<style type="text/css">
				{ `#plugin-banner-${ slug } { background-image: url('${ banner }'); }` }
				{ banners.high && '@media ' +
					'only screen and (-webkit-min-device-pixel-ratio: 1.5), only screen and (min-resolution: 144dpi) ' +
					`{ #plugin-banner-${ slug } { background-image: url('${ banners.high }'); } }`
				} }
			</style>
		</div>
	);
};

PluginBanner.propTypes = {
	plugin: PropTypes.object,
};

PluginBanner.defaultProps = {
	plugin: {},
};

export default connect(
	( state ) => ( {
		plugin: getPlugin( state ),
	} ),
)( PluginBanner );
