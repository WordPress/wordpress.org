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
		watch: {
			js: {
				files: ['<%= jshint.files %>'],
				tasks: ['jshint']
			},
			css: {
				files: ['**/*.scss'],
				tasks: ['sass']
			}
		}
	});

	grunt.loadNpmTasks('grunt-sass');
	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-contrib-watch');

	grunt.registerTask('default', ['jshint', 'sass']);
};