import React from 'react';

export default React.createClass( {
	displayName: 'PluginBanner',

	render() {
		const { banners, slug } = this.props.plugin;
		let banner;

		if ( ! banners ) {
			return <div />;
		}

		if ( banners[ '1x' ] ) {
			banner = banners[ '1x' ]
		} else if ( banners.svg ) {
			banner = banners.svg;
		}

		if ( ! banner ) {
			return <div />;
		}

		return (
			<div className="entry-banner">
				<div className="plugin-banner" id={ `plugin-banner-${ slug }` }></div>
				<style type='text/css'>
					{ `#plugin-banner-${ slug } { background-image: url('${ banner }'); }` }
					{ banners[ '2x' ] ?
						`@media only screen and (-webkit-min-device-pixel-ratio: 1.5) { #plugin-banner-${ slug } { background-image: url('${ banners[ '2x' ] }'); } }` : ''
					} }
				</style>
			</div>
		)
	}
} );
