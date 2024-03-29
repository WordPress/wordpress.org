/**
 * Gruntfile.js
 *
 * @package WordPressdotorg\Theme
 */

/* global module:false, require:function, process:object */

require( 'es6-promise' ).polyfill();

module.exports = function ( grunt ) {
	var isChild = 'wporg' !== grunt.file.readJSON( 'package.json' ).name;

	grunt.initConfig({
		postcss: {
			options: {
				map: 'build' !== process.argv[2],
				processors: [
					require( 'autoprefixer' )( {
						overrideBrowserslist: [
							'Android >= 2.1',
							'Chrome >= 21',
							'Edge >= 12',
							'Explorer >= 7',
							'Firefox >= 17',
							'Opera >= 12.1',
							'Safari >= 6.0'
						],
						cascade: false
					} ),
					require('cssnano')({
						mergeRules: false
					})
				]
			},
			dist: {
				src: 'css/style.css'
			}
		},
		jshint: {
			files: [ 'Gruntfile.js', 'js/**/*.js' ],
			options: grunt.file.readJSON( '.jshintrc' )
		},
		uglify: {
			options: {
				ASCIIOnly: true,
				screwIE8: false
			},
			js: {
				expand: true,
				cwd: 'js/',
				dest: 'js/',
				ext: '.min.js',
				src: '**/*.js',
			}
		},
		sass: {
			options: {
				implementation: require( 'sass' ),
				sourceMap: true,
				// Don't add source map URL in built version.
				omitSourceMapUrl: 'build' === process.argv[2],
				outputStyle: 'expanded',
				includePaths: [ './node_modules' ],
			},
			dist: {
				files: {
					'css/style.css': 'css/style.scss'
				}
			}
		},
		sass_globbing: {
			itcss: {
				files: (function() {
					var files = {};

					['settings', 'tools', 'generic', 'base', 'objects', 'components', 'trumps'].forEach( function( component ) {
						var paths = [ '../wporg/css/' + component + '/**/*.scss', '!../wporg/css/' + component + '/_' + component + '.scss' ];

						if ( isChild ) {
							paths.push( 'css/' + component + '/**/*.scss' );
							paths.push( '!css/' + component + '/_' + component + '.scss' );
						}

						files[ 'css/' + component + '/_' + component + '.scss' ] = paths;
					} );

					return files;
				})()
			},
			options: { signature: false }
		},
		rtlcss: {
			options: {
				// rtlcss options.
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
								ignoreCase: false
							}
						} // phpcs:ignore Generic.WhiteSpace.ScopeIndent.IncorrectExact
					]
				},
				saveUnmodified: false,
				plugins: [
					{
						name: 'swap-dashicons-left-right-arrows',
						priority: 10,
						directives: {
							control: {},
							value: []
						},
						processors: [
							{
								expr: /content/im,
								action: function( prop, value ) {
									if ( value === '"\\f141"' ) { // dashicons-arrow-left.
										value = '"\\f139"';
									} else if ( value === '"\\f340"' ) { // dashicons-arrow-left-alt.
										value = '"\\f344"';
									} else if ( value === '"\\f341"' ) { // dashicons-arrow-left-alt2.
										value = '"\\f345"';
									} else if ( value === '"\\f139"' ) { // dashicons-arrow-right.
										value = '"\\f141"';
									} else if ( value === '"\\f344"' ) { // dashicons-arrow-right-alt.
										value = '"\\f340"';
									} else if ( value === '"\\f345"' ) { // dashicons-arrow-right-alt2.
										value = '"\\f341"';
									}
									return { prop: prop, value: value };
								}
							} // phpcs:ignore Generic.WhiteSpace.ScopeIndent.IncorrectExact
						]
					} // phpcs:ignore Generic.WhiteSpace.ScopeIndent.IncorrectExact
				]
			},
			dynamic: {
				expand: true,
				cwd: 'css/',
				dest: 'css/',
				ext: '-rtl.css',
				src: ['**/style.css']
			}
		},
		watch: {
			jshint: {
				files: ['<%= jshint.files %>'],
				tasks: ['jshint']
			},
			css: {
				files: ['**/*.scss'],
				tasks: ['css']
			}
		}
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
	grunt.registerTask( 'default', ['jshint', 'css'] );
	grunt.registerTask( 'build', ['css', 'uglify:js'] );
};
