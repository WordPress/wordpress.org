require( 'es6-promise' ).polyfill();
require( 'babel-polyfill' );

var NODE_ENV          = process.env.NODE_ENV || 'development';
var path              = require( 'path' );
var webpack           = require( 'webpack' );
var ExtractTextPlugin = require( 'extract-text-webpack-plugin' );
var SystemBellPlugin  = require( 'system-bell-webpack-plugin' );

var webpackConfig = {
	entry : [
		'babel-polyfill',
		'./javascript/tracker/source/tracker.jsx'
	],

	output : {
		path     : path.join( __dirname, 'javascript/tracker/build' ),
		filename : 'tracker.min.js'
	},

	module : {
		loaders : [
			{
				test    : /\.jsx?$/,
				exclude : /node_modules/,
				loader  : 'babel',
				query   : {
					cacheDirectory : true,
					presets        : [ 'es2015', 'react', 'stage-2' ]
				}
			},

			{
				test    : /\.scss$/,
				exclude : /node_modules/,
				loader  : ExtractTextPlugin.extract( 'style-loader', 'css!sass' )
			}
		]
	},

	resolve : {
		extensions : [ '', '.js', '.jsx' ]
	},

	node : {
		fs      : "empty",
		process : true
	},

	plugins : [
		new webpack.DefinePlugin( {
			'process.env' : {
				NODE_ENV : JSON.stringify( NODE_ENV )
			}
		} ),

		new ExtractTextPlugin( 'tracker.min.css' ),
		new SystemBellPlugin()
	],

	watchOptions : {
		poll : true // required to work in a VM, see https://github.com/webpack/webpack/issues/425#issuecomment-53214820
	}
};

if ( NODE_ENV === 'production' ) {
	webpackConfig.plugins.push( new webpack.optimize.UglifyJsPlugin( {
		compress : {
			warnings : false
		}
	} ) );

	webpackConfig.devtool = '#source-map';
}

module.exports = webpackConfig;
