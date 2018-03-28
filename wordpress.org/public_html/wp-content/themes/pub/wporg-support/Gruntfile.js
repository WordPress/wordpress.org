module.exports = function(grunt) {
	var autoprefixer = require( 'autoprefixer' );
	var scsssyntax = require( 'postcss-scss' );

	grunt.initConfig({
		pkg: grunt.file.readJSON( 'package.json' ),
		sass: {
			dist: {
				files: {
					'style.css': 'sass/style.scss',
				},
				options: {
					outputStyle: 'expanded',
					indentType: 'tab',
					indentWidth: 1,
					sourceMap: true,
				},
			},
		},
		postcss: {
			options: {
				map: false,
				processors: [
					autoprefixer({
						browsers: [ 'extends @wordpress/browserslist-config' ],
						cascade: false,
					}),
				],
				syntax: scsssyntax,
				failOnError: true,
			},
			dist: {
				expand: true,
				src: [ 'sass/**/*.scss' ],
			},
		},
		rtlcss: {
			dist: {
				files: {
					'style-rtl.css': 'style.css',
				},
			},
		},
		watch: {
			css: {
				files: '**/*.scss',
				tasks: ['sass', 'rtlcss'],
			},
		},
	});
	grunt.loadNpmTasks( 'grunt-postcss' );
	grunt.loadNpmTasks( 'grunt-sass' );
	grunt.loadNpmTasks( 'grunt-rtlcss' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );

	grunt.registerTask( 'build', [ 'postcss', 'sass', 'rtlcss' ]);
	grunt.registerTask( 'default', [ 'build' ]);
};
