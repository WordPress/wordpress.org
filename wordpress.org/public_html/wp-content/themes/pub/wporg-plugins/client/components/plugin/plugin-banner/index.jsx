import React from 'react';

export default React.createClass( {
	displayName: 'PluginBanner',

	render() {
		const { banners, slug } = this.props.plugin;
		let banner;

		if ( ! banners ) {
			return <div />;
		}

		banner = banners[ 'low' ] ? banners[ 'low' ] : banners[ 'high' ];

		if ( ! banner ) {
			return <div />;
		}

		return (
			<div className="entry-banner">
				<div className="plugin-banner" id={ `plugin-banner-${ slug }` }></div>
				<style type='text/css'>
					{ `#plugin-banner-${ slug } { background-image: url('${ banner }'); }` }
					{ banners[ 'high' ] ?
						`@media only screen and (-webkit-min-device-pixel-ratio: 1.5) { #plugin-banner-${ slug } { background-image: url('${ banners[ 'high' ] }'); } }` : ''
					} }
				</style>
			</div>
		)
	}
} );
