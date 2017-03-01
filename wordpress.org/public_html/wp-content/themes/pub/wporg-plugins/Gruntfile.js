/* global module:false, require:function, process:object */

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
					new webpack.DefinePlugin( {
						'process.env.NODE_ENV': JSON.stringify( 'production' )
					} ),
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
			watch: {
				devtool: 'sourcemap',
				debug: true,
				watch: true,
				keepalive: true
			}
		},
		postcss: {
			options: {
				map: 'build' !== process.argv[2],
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
					}),
					require('pixrem')
				]
			},
			dist: {
				src: 'css/style.css'
			}
		},
		eslint: {
			files: [
				'client/**/*.js',
				'client/**/*.jsx',

				// External library. For now.
				'!client/**/**/image-gallery/index.jsx'
			]
		},
		sass: {
			options: {
				sourceMap: true,
				// Don't add source map URL in built version.
				omitSourceMapUrl: 'build' === process.argv[2],
				outputStyle: 'expanded'
			},
			dist: {
				files: {
					'css/style.css': 'client/style.scss'
				}
			}
		},
		sass_globbing: {
			my_target: {
				files: { 'client/styles/_components.scss': 'client/components/**/*.scss' },
			},
			options: { signature: false }
		},
		shell: {
			build: {
				command: './node_modules/wpapi/lib/data/update-default-routes-json.js --endpoint=https://wordpress.org/plugins-wp/wp-json --output=./client'
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
			eslint: {
				files: ['<%= eslint.files %>'],
				tasks: ['eslint']
			},
			css: {
				files: ['**/*.scss', 'client/components/**/**.scss'],
				tasks: ['sass_globbing', 'sass', 'postcss', 'rtlcss:dynamic']
			}
		}
	});

	grunt.loadNpmTasks('grunt-sass');
	grunt.loadNpmTasks('grunt-rtlcss');
	grunt.loadNpmTasks('grunt-webpack');
	grunt.loadNpmTasks('grunt-eslint');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-sass-globbing');
	grunt.loadNpmTasks('grunt-shell');

	grunt.registerTask('default', ['eslint', 'sass_globbing', 'sass', 'rtlcss:dynamic']);
	grunt.registerTask('css', ['sass_globbing', 'sass', 'postcss', 'rtlcss:dynamic']);
	grunt.registerTask('build', ['webpack:build', 'css', 'shell:build']);
};
