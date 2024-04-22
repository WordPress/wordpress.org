const RtlCssPlugin = require( 'rtlcss-webpack-plugin' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	plugins: [
		...defaultConfig.plugins,
		new RtlCssPlugin( {
			filename: `[name]-rtl.css`,
		} ),
	],
};
