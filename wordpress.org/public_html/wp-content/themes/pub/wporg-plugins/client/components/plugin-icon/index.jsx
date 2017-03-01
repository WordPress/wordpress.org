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
 * @return {*}            React element or null.
 * @constructor
 */
export const PluginIcon = ( { plugin } ) => {
	const { icons, slug } = plugin;
	let icon;

	if ( ! icons ) {
		return null;
	}

	if ( icons.svg ) {
		icon = icons.svg;
	} else if ( icons[ '1x' ] ) {
		icon = icons[ '1x' ];
	} else {
		icon = icons.default;
	}

	return (
		<div className="entry-thumbnail">
			<div className="plugin-icon" id={ `plugin-icon-${ slug }` } />
			<style type="text/css">
				{ `#plugin-icon-${ slug } { background-image: url('${ icon }'); }` }
				{ icons[ '2x' ] && icon !== icons.default
					// eslint-disable-next-line max-len
					? `@media only screen and (-webkit-min-device-pixel-ratio: 1.5), only screen and (min-resolution: 144dpi) { #plugin-icon-${ slug } { background-image: url('${ icons[ '2x' ] }'); } }`
					: ''
				}
			</style>
		</div>
	);
};

PluginIcon.propTypes = {
	plugin: PropTypes.object,
	slug: PropTypes.string,
};

PluginIcon.defaultProps = {
	plugin: {},
	slug: '',
};

export default connect(
	( state, { slug } ) => ( {
		plugin: getPlugin( state, slug ),
	} ),
)( PluginIcon );
