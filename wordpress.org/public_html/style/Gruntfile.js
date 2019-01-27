/**
 * Gruntfile.js
 *
 * @package WordPressdotorg\Style
 */

/* global module:false, require:function, process:object */

require( 'es6-promise' ).polyfill();

module.exports = function( grunt ) {
	grunt.initConfig({
		postcss: {
			options: {
				map: { inline: false },
				processors: [
					require( 'autoprefixer' )( {
						browsers: [
							'Android >= 2.1',
							'Chrome >= 21',
							'Edge >= 12',
							'Explorer >= 7',
							'Firefox >= 17',
							'Opera >= 12.1',
							'Safari >= 6.0'
						],
						cascade: false
					} )
				]
			},
			dist: {
				src: 'wp4.css'
			}
		},
		jshint: {
			files: [ 'Gruntfile.js', 'js/*.js', 'trac/*.js', 't!rac/*.min.js' ],
			options: grunt.file.readJSON( '.jshintrc' )
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
						}
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
							}
						]
					}
				]
			},
			dynamic: {
				expand: true,
				ext: '-rtl.css',
				src: ['wp4.css']
			}
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
				src: [
					'navigation.js',
				]
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
	//	grunt.config.merge( { postcss: { options : { processors: [ require( 'cssnano' )() ] } } } );
	}

	grunt.loadNpmTasks( 'grunt-rtlcss' );
	grunt.loadNpmTasks( 'grunt-postcss' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-contrib-uglify' );

	grunt.registerTask( 'js', ['uglify:js'] );
	grunt.registerTask( 'css', ['postcss', 'rtlcss:dynamic'] );
	grunt.registerTask( 'default', ['jshint', 'css', 'js'] );
	grunt.registerTask( 'build', ['css', 'js'] );
};
