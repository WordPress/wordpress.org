/**
 * Gruntfile.js
 *
 * @package WordPressdotorg\Theme_Directory\Theme
 */

/* global module:false, require:function, process:object */

module.exports = function ( grunt ) {
	var isChild = 'wporg' !== grunt.file.readJSON( 'package.json' ).name;

	grunt.initConfig( {
		postcss: {
			options: {
				map: 'build' !== process.argv[ 2 ],
				processors: [
					require( 'autoprefixer' ),
					require( 'cssnano' )( {
						mergeRules: false,
					} ),
				],
			},
			dist: {
				src: 'css/style.css'
			}
		},
		jshint: {
			files: [ 'Gruntfile.js', 'js/**/*.js' ],
			options: grunt.file.readJSON( '.jshintrc' )
		},
		sass: {
			options: {
				implementation: require( 'sass' ),
				sourceMap: true,
				// Don't add source map URL in built version.
				omitSourceMapUrl: 'build' === process.argv[ 2 ],
				outputStyle: 'expanded',
				includePaths: [ './node_modules' ],
			},
			dist: {
				files: {
					'css/style.css': 'css/style.scss',
				},
			},
		},
		sass_globbing: {
			itcss: {
				files: ( function () {
					var files = {};

					['settings', 'tools', 'generic', 'base', 'objects', 'components', 'trumps'].forEach( function( component ) {
						var paths = [ '../wporg/css/' + component + '/**/*.scss', '!../wporg/css/' + component + '/_' + component + '.scss' ];

						if ( isChild ) {
							paths.push( 'css/' + component + '/**/*.scss' );
							paths.push( '!css/' + component + '/_' + component + '.scss' );
						}

						if ( 'components' === component ) {
							paths.push( 'client/components/**/*.scss' );
							paths.push( '!../wporg/css/components/_search.scss' );
							paths.push( '!../wporg/css/components/_main-navigation.scss' );
							paths.push( '!../wporg/css/components/_post-navigation.scss' );
							paths.push( '!../wporg/css/components/_entry-meta.scss' );
							paths.push( '!../wporg/css/components/_widget-area.scss' );
							paths.push( '!../wporg/css/components/_page.scss' );
						}

						if ( 'tools' === component ) {
							paths.push( '!../wporg/css/tools/_kube.scss' );
						}

						if ( 'generic' === component ) {
							paths.push( '!../wporg/css/generic/_kube.scss' );
						}

						files[ 'css/' + component + '/_' + component + '.scss' ] = paths;
					} );

					return files;
				} )(),
			},
			options: { signature: false },
		},
		rtlcss: {
			options: {
				// rtlcss options
				opts: {
					clean: false,
					processUrls: { atrule: true, decl: false },
					stringMap: [
						{
							name: 'import-rtl-stylesheet',
							priority: 10,
							exclusive: true,
							search: [ '.css' ],
							replace: [ '-rtl.css' ],
							options: {
								scope: 'url',
								ignoreCase: false,
							},
						},
					],
				},
				saveUnmodified: false,
				plugins: [
					{
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
										// dashicons-arrow-left
										value = '"\\f139"';
									} else if ( value === '"\\f340"' ) {
										// dashicons-arrow-left-alt
										value = '"\\f344"';
									} else if ( value === '"\\f341"' ) {
										// dashicons-arrow-left-alt2
										value = '"\\f345"';
									} else if ( value === '"\\f139"' ) {
										// dashicons-arrow-right
										value = '"\\f141"';
									} else if ( value === '"\\f344"' ) {
										// dashicons-arrow-right-alt
										value = '"\\f340"';
									} else if ( value === '"\\f345"' ) {
										// dashicons-arrow-right-alt2
										value = '"\\f341"';
									} else if ( value === '"\\2190"' ) { // Unicode left/rightwards arrows
										value = '"\\2192"';
									} else if ( value === '"\\2192"' ) {
										value = '"\\2190"';
									}
									return { prop: prop, value: value };
								},
							},
						],
					},
				],
			},
			dynamic: {
				expand: true,
				cwd: 'css/',
				dest: 'css/',
				ext: '-rtl.css',
				src: [ '**/style.css' ],
			}
		},
		uglify: {
			options: {
				ASCIIOnly: true,
				screwIE8: false,
			},
			js: {
				expand: true,
				cwd: 'js/',
				dest: 'js/',
				ext: '.min.js',
				src: [ 'theme.js' ],
			},
		},
		watch: {
			jshint: {
				files: [ '<%= jshint.files %>' ],
				tasks: [ 'jshint' ],
			},
			css: {
				files: [ '**/*.scss' ],
				tasks: [ 'css' ],
			},
		},
	});

	if ( 'build' === process.argv[2] ) {
		grunt.config.merge( { postcss: { options : { processors: [ require( 'cssnano' ) ] } } } );
	}

	grunt.loadNpmTasks( 'grunt-sass' );
	grunt.loadNpmTasks( 'grunt-rtlcss' );
	grunt.loadNpmTasks( '@lodder/grunt-postcss' );
	grunt.loadNpmTasks( 'grunt-sass-globbing' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-contrib-uglify' );

	grunt.registerTask( 'css', ['sass_globbing', 'sass', 'postcss', 'rtlcss:dynamic'] );
	grunt.registerTask( 'default', ['jshint', 'css', 'uglify:js'] );
	grunt.registerTask( 'build', ['css', 'uglify:js'] );
};
