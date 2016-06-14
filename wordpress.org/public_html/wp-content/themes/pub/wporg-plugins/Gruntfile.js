/*global module:false*/
module.exports = function(grunt) {

	grunt.initConfig({
		jshint: {
			files: [
				'Gruntfile.js',
				'js/**/*.js'
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
			js: {
				files: ['<%= jshint.files %>'],
				tasks: ['jshint']
			},
			css: {
				files: ['**/*.scss'],
				tasks: ['sass']
			},
			rtl: {
				files: ['**/style.css'],
				tasks: ['rtlcss:dynamic']
			}
		}
	});

	grunt.loadNpmTasks('grunt-sass');
	grunt.loadNpmTasks('grunt-rtlcss');
	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-contrib-watch');

	grunt.registerTask('default', ['jshint', 'sass', 'rtlcss:dynamic']);
};