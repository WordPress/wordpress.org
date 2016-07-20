/* global module:false, require:function */

var webpack       = require( 'webpack' ),
	webpackConfig = require( './webpack.config' );

module.exports = function( grunt ) {
	grunt.loadNpmTasks('grunt-postcss');

	grunt.initConfig({
		webpack: {
			options: webpackConfig,
			build: {
				plugins: webpackConfig.plugins.concat(
					new webpack.optimize.DedupePlugin(),
					new webpack.optimize.UglifyJsPlugin( {
						compress: { warnings: false }
					} )
				),
				output: {
					path: 'js/'
				}
			},
			'build-dev': {
				devtool: 'sourcemap',
				debug: true
			},
			'watch-dev': {
				devtool: 'sourcemap',
				debug: true,
				watch: true,
				keepalive: true
			}
		},
		postcss: {
			options: {
				processors: [
					require('autoprefixer')({
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
					})
				]
			},
			dist: {
				files: {
					'css/style.css': 'css/style.css'
				}
			}
		},
		jshint: {
			files: [
				'Gruntfile.js',
				'js/**/*.js',
				'!js/theme.js'
			],
			options: grunt.file.readJSON('.jshintrc')
		},
		sass: {
			options: {
				outputStyle: 'expanded'
			},
			dist: {
				files: {
					'css/style.css': 'sass/style.scss'
				}
			}
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
									if ( value === '"\\f141"' ) { // dashicons-arrow-left
										value = '"\\f139"';
									} else if ( value === '"\\f340"' ) { // dashicons-arrow-left-alt
										value = '"\\f344"';
									} else if ( value === '"\\f341"' ) { // dashicons-arrow-left-alt2
										value = '"\\f345"';
									} else if ( value === '"\\f139"' ) { // dashicons-arrow-right
										value = '"\\f141"';
									} else if ( value === '"\\f344"' ) { // dashicons-arrow-right-alt
										value = '"\\f340"';
									} else if ( value === '"\\f345"' ) { // dashicons-arrow-right-alt2
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
				tasks: ['sass']
			},
			rtl: {
				files: ['**/style.css'],
				tasks: ['postcss', 'rtlcss:dynamic']
			},
			livereload: {
				options: { livereload: true },
				files: [ 'css/style.css' ]
			}

		}
	});

	grunt.loadNpmTasks('grunt-sass');
	grunt.loadNpmTasks('grunt-rtlcss');
	grunt.loadNpmTasks('grunt-webpack');
	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-contrib-watch');

	grunt.registerTask('default', ['jshint', 'sass', 'rtlcss:dynamic']);
	grunt.registerTask('build', ['webpack:build']);

};