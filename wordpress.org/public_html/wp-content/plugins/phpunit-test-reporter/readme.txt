=== PHPUnit Test Reporter ===
Contributors: octalmage, danielbachhuber, wpamitkumar, mikeschroder, pfefferle
Tags: phpunit
Requires at least: 4.7
Tested up to: 5.4.1
Stable tag: 0.1.2
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Captures and displays test results from the PHPUnit Test Runner

== Description ==

Captures and displays test results from the [PHPUnit Test Runner](https://github.com/WordPress/phpunit-test-runner).

For more details, [please read through the project overview](https://make.wordpress.org/hosting/test-results-getting-started/).

== Contributing ==

There’s a Docker environment with several tools built in for testing.
To configure it, run `make` and it will automatically run `docker-compose`.
After that, to view the test environment, visit http://localhost:8080.

Usage:
- `make` or `make start`:  Builds a Docker environment for testing.
- `make stop`: Stops Docker test environment.
- `make shell`: SSH to Docker test environment.
- `make test`: Runs `php-unit` and `phpcs` in Docker test environment.

There is also a [Grunt](https://gruntjs.com/) command for updating the `README.md` file for Github
after updating `readme.txt`.

Usage:
- `npm install`: Installs necessary dependencies.
- `grunt readme`: Generates `README.md` with
  `[grunt-wp-readme-to-markdown](https://github.com/stephenharris/wp-readme-to-markdown)`.

== Changelog ==

= 0.1.0 (August 21st, 2017) =
* Initial release.

= 0.1.1 (May 12th, 2020) =
* Updates for coding standards.
* Update local and Travis CI builds.
* Reduce number of revisions in index, while increasing max reporters shown.
* Add contributor documentation.

= 0.1.2 (May 18th, 2020) =
* Only report result status when tests were actually run. Port of
  [meta #7227](https://meta.trac.wordpress.org/changeset/7227) to plugin.
* Upgrade packages for `grunt readme` to Grunt ^1.1.0 and
  grunt-wp-readme-to-markdown ^2.0.1, and update plugin version in `package.json`.
* Upgrade Docker environment to use `wordpress:5.4.1-php7.4` image.
* Correct contributor list in `readme.txt` and update `README.md`.
