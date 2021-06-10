module.exports = function( grunt ) {
	const isChild = 'wporg' !== grunt.file.readJSON( 'package.json' ).name;
	const defaultWebpackConfig = require( '@wordpress/scripts/config/webpack.config' );

	const getSassFiles = () => {
		const files = {};
		const components = [ 'settings', 'tools', 'generic', 'base', 'objects', 'components', 'utilities' ];

		components.forEach( function( component ) {
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

	grunt.initConfig( {
		postcss: {
			options: {
				map: 'build' !== process.argv[ 2 ],
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
		},

		sass: {
			options: {
				implementation: require( 'sass' ),
				sourceMap: true,
				// Don't add source map URL in built version.
				omitSourceMapUrl: 'build' === process.argv[ 2 ],
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
	} );

	if ( 'build' === process.argv[ 2 ] ) {
		grunt.config.merge( { postcss: { options: { processors: [ require( 'cssnano' ) ] } } } );
	}

	grunt.loadNpmTasks( 'grunt-sass' );
	grunt.loadNpmTasks( '@lodder/grunt-postcss' );
	grunt.loadNpmTasks( 'grunt-sass-globbing' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-webpack' );

	grunt.registerTask( 'css', [ 'sass_globbing', 'sass', 'postcss' ] );
	grunt.registerTask( 'js', [ 'webpack' ] );

	grunt.registerTask( 'default', [ 'css', 'webpack' ] );
	grunt.registerTask( 'build', [ 'css', 'webpack' ] ); // Automatically runs "production" steps
};
