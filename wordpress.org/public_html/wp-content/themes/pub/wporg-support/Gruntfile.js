module.exports = function(grunt) {
	grunt.initConfig({
		pkg: grunt.file.readJSON( 'package.json' ),
		sass: {
			dist: {
				files: {
					'style.css' : 'sass/style.scss'
				},
				options: {
					outputStyle: 'expanded',
					indentType: 'tab',
					indentWidth: 1,
					sourceMap: true
				}
			}
		},
		rtlcss: {
			dist: {
				files: {
					'style-rtl.css' : 'style.css'
				}
			}
		},
		watch: {
			css: {
				files: '**/*.scss',
				tasks: ['sass', 'rtlcss']
			}
		}
	});
	grunt.loadNpmTasks( 'grunt-sass' );
	grunt.loadNpmTasks( 'grunt-rtlcss' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );

	grunt.registerTask( 'build', [ 'sass', 'rtlcss' ] );
	grunt.registerTask( 'default', [ 'build' ] );
};
