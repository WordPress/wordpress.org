// Set the source directory, prevents an undefined warning in the webpack config.
process.env.WP_SRC_DIRECTORY = 'src';

module.exports = function ( grunt ) {
	const isChild = 'wporg' !== grunt.file.readJSON( 'package.json' ).name;
	const defaultWebpackConfig = require( '@wordpress/scripts/config/webpack.config' );
	const isProduction = process.env.NODE_ENV === 'production';

	const getSassFiles = () => {
		const files = {};
		const components = [ 'settings', 'tools', 'generic', 'base', 'objects', 'components', 'utilities' ];

		components.forEach( function ( component ) {
			const paths = [
				'../wporg/css/' + component + '/**/*.scss',
				'!../wporg/css/' + component + '/_' + component + '.scss',
			];

			if ( isChild ) {
				paths.push( 'css/' + component + '/**/*.scss' );
				paths.push( '!css/' + component + '/_' + component + '.scss' );
			}

			files[ 'css/' + component + '/_' + component + '.scss' ] = paths;
		} );

		return files;
	};

	const rtlcssPluginSwapDashiconArrows = {
		name: 'swap-dashicons-left-right-arrows',
		priority: 10,
		directives: {
			control: {},
			value: [],
		},
		processors: [
			{
				expr: /content/im,
				action: function ( prop, value ) {
					if ( value === '"\\f141"' ) {
						// replace dashicons-arrow-left with -right.
						value = '"\\f139"';
					} else if ( value === '"\\f340"' ) {
						// replace dashicons-arrow-left-alt with -right-alt.
						value = '"\\f344"';
					} else if ( value === '"\\f341"' ) {
						// replace dashicons-arrow-left-alt2 with -right-alt2.
						value = '"\\f345"';
					} else if ( value === '"\\f139"' ) {
						// replace dashicons-arrow-right with -left.
						value = '"\\f141"';
					} else if ( value === '"\\f344"' ) {
						// replace dashicons-arrow-right-alt with -left-alt.
						value = '"\\f340"';
					} else if ( value === '"\\f345"' ) {
						// replace dashicons-arrow-right-alt2 with -left-alt2.
						value = '"\\f341"';
					}
					return { prop, value };
				},
			},
		],
	};

	grunt.initConfig( {
		postcss: {
			options: {
				map: ! isProduction,
				processors: [
					require( 'autoprefixer' )( {
						cascade: false,
						grid: true,
					} ),
					require( 'cssnano' )( {
						mergeRules: false,
					} ),
				],
			},
			dist: {
				src: 'css/style.css',
			},
			rtl: {
				options: {
					processors: [ require( 'rtlcss' )( null, [ rtlcssPluginSwapDashiconArrows ] ) ],
				},
				src: 'css/style.css',
				dest: 'css/style-rtl.css',
			},
		},

		sass: {
			options: {
				implementation: require( 'sass' ),
				sourceMap: ! isProduction,
				omitSourceMapUrl: isProduction,
				outputStyle: 'expanded',
			},
			dist: {
				files: {
					'css/style.css': 'css/style.scss',
				},
			},
		},

		sass_globbing: {
			itcss: {
				files: getSassFiles(),
			},
			options: { signature: false },
		},

		webpack: {
			myConfig: defaultWebpackConfig,
		},

		watch: {
			css: {
				files: [ '**/*.scss', '../wporg/css/**/*scss' ],
				tasks: [ 'css' ],
			},
			javascript: {
				files: [ 'src/**/*.js' ],
				tasks: [ 'webpack' ],
			},
		},

		clean: {
			build: [ 'build/*' ],
			css: [ 'css/*.css', 'css/*.css.map' ],
		},
	} );

	if ( isProduction ) {
		grunt.config.merge( { postcss: { options: { processors: [ require( 'cssnano' ) ] } } } );
	}

	grunt.loadNpmTasks( 'grunt-sass' );
	grunt.loadNpmTasks( '@lodder/grunt-postcss' );
	grunt.loadNpmTasks( 'grunt-sass-globbing' );
	grunt.loadNpmTasks( 'grunt-contrib-clean' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-webpack' );

	grunt.registerTask( 'css', [ 'sass_globbing', 'sass', 'postcss' ] );
	grunt.registerTask( 'js', [ 'webpack' ] );

	grunt.registerTask( 'default', [ 'clean', 'css', 'js' ] );
	grunt.registerTask( 'build', [ 'clean', 'css', 'js' ] );
};
