import React from 'react';

/**
 * Internal dependencies.
 */

export default React.createClass( {
	displayName: 'PluginIcon',

	render() {
		const { icons, slug } = this.props.plugin;
		let icon;

		if ( icons[ '1x' ] ) {
			icon = icons[ '1x' ]
		} else if ( icons.svg ) {
			icon = icons.svg;
		} else {
			icon = icons.default;
		}

		return (
			<div className="entry-thumbnail">
				<div className="plugin-icon" id={ `plugin-icon-${ slug }` }></div>
				<style type='text/css'>
					{ `#plugin-icon-${ slug } { background-image: url('${ icon }'); } .plugin-icon { background-size: contain; height: 128px; width: 128px; }` }
					{ icons[ '2x' ] && icon !== icons.default ?
						`@media only screen and (-webkit-min-device-pixel-ratio: 1.5) { #plugin-icon-${ slug } { background-image: url('${ icons[ '2x' ] }'); } }` : ''
					} }
				</style>
			</div>
		)
	}
} );
