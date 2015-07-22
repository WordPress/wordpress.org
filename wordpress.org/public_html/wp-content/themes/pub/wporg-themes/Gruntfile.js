/* jshint node:true */
module.exports = function(grunt) {
	var path = require('path');

	// Load tasks.
	require('matchdep').filterDev(['grunt-*']).forEach( grunt.loadNpmTasks );

	// Project configuration.
	grunt.initConfig({
		rtlcss: {
			options: {
				// rtlcss options
				config: {
					swapLeftRightInUrl: false,
					swapLtrRtlInUrl: false,
					autoRename: false,
					preserveDirectives: true,
					stringMap: [
						{
							name: 'import-rtl-stylesheet',
							search: [ '.css' ],
							replace: [ '-rtl.css' ],
							options: {
								scope: 'url',
								ignoreCase: false
							}
						}
					]
				},
				properties : [
					{
						name: 'swap-dashicons-left-right-arrows',
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
							} else if ( value === '"\\2192"' ) { // Unicode rightwards arrow
								value = '"\\2190"';
							} else if ( value === '"\\2190"' ) { // Unicode leftwards arrow
								value = '"\\2192"';
							}
							return { prop: prop, value: value };
						}
					}
				],
				saveUnmodified: false
			},
			theme: {
				expand: true,
				ext: '-rtl.css',
				src: [
					'style.css',
				]
			},
		},
		uglify: {
			options: {
				ASCIIOnly: true
			},
			js: {
				expand: true,
				ext: '.min.js',
				src: [ 'js/theme.js' ]
			}
		}
	});

	// Register tasks.

	grunt.registerTask( 'build', [
		'rtlcss',
		'uglify'
	] );

	// Default task.
	grunt.registerTask('default', ['build']);

};

