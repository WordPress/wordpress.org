const path = require( 'path' );
const webpack = require( 'webpack' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );

const NODE_ENV = process.env.NODE_ENV || 'development';
const SOURCE_MAPS = process.env.SOURCE_MAPS ? true : false;


const externals = {
	react       : 'React',
	'react-dom' : 'ReactDOM',
	lodash      : 'lodash',
};

const webpackConfig = {
	// Must be 'none', 'production', or 'development'.
	mode: NODE_ENV === 'production' ? 'production' : 'development',

	optimization: {
		minimize: true,
	},

	// Disabled by default because they make the re-build process take longer.
	devtool: SOURCE_MAPS ? 'cheap-module-eval-source-map' : 'none',

	entry: {
		blocks: path.resolve( __dirname, 'assets/src/blocks.js' ),
	},
	output: {
		filename : '[name].min.js',
		path     : path.resolve( __dirname, 'assets' ),
	},
	module: {
		rules: [
			{
				test    : /\.jsx?$/,
				exclude : [
					/node_modules/,
				],
				use: 'babel-loader',
			},
			{
				test    : /\.(sc|sa|c)ss$/,
				exclude : [
					/node_modules/,
				],
				use: [
					MiniCssExtractPlugin.loader,
					'css-loader',
					'sass-loader',
				],
			},
		],
	},
	plugins: [
		new MiniCssExtractPlugin( {
			filename: '[name].min.css',
		} ),
		new webpack.DefinePlugin( {
			'process.env.NODE_ENV': JSON.stringify( NODE_ENV ),
		} ),
	],
	externals,
};

module.exports = webpackConfig;
