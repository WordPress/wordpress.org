module.exports = function(grunt) {
	var autoprefixer = require( 'autoprefixer' );

	grunt.initConfig({
		pkg: grunt.file.readJSON( 'package.json' ),
		sass: {
			dist: {
				files: {
					'style.css': 'sass/style.scss',
				},
				options: {
					implementation: require( 'sass' ),
					indentType: 'tab',
					indentWidth: 1,
					outputStyle: 'expanded',
					sourceMap: true,
				},
			},
		},
		stylelint: {
			scss: {
				options: {
					customSyntax: 'postcss-scss'
				},
				expand: true,
				src: [
					'sass/**/*.scss',
					'!sass/_normalize.scss',
					'!sass/mixins/_breakpoint.scss',
					'!sass/mixins/_modular-scale.scss',
				],
			}
		},
		postcss: {
			options: {
				map: false,
				processors: [
					autoprefixer({
						overrideBrowserslist: [ 'extends @wordpress/browserslist-config' ],
						cascade: false,
					}),
				],
				failOnError: true,
			},
			dist: {
				expand: true,
				src: [ 'style.css' ],
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
				tasks: ['stylelint', 'sass', 'rtlcss'],
			},
		},
	});
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( '@lodder/grunt-postcss' );
	grunt.loadNpmTasks( 'grunt-rtlcss' );
	grunt.loadNpmTasks( 'grunt-sass' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.registerTask( 'build', [ 'sass', 'postcss', 'rtlcss' ]);
	grunt.registerTask( 'default', [ 'build' ]);
};
