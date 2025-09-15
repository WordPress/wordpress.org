=== PHPUnit Test Reporter ===
Contributors: octalmage, danielbachhuber, wpamitkumar, kirasong, pfefferle, desrosj, crixu
Tags: phpunit
Requires at least: 4.7
Tested up to: 5.5
Stable tag: 0.2.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Captures and displays test results from the PHPUnit Test Runner

== Description ==

Captures and displays test results from the [PHPUnit Test Runner](https://github.com/WordPress/phpunit-test-runner).

This is the plugin that receives and generates the results displayed on https://make.wordpress.org/hosting/test-results.

For more details, [please read through the project overview](https://make.wordpress.org/hosting/test-results-getting-started/).

== Contributing ==

Contributors are welcome!
Check out the [contribution guidelines](https://github.com/WordPress/phpunit-test-reporter/blob/master/CONTRIBUTING.md) for details, including how to use the build/testing tools.

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

= 0.1.3 (September 23th, 2020) =
* Include errors along with failures on the error report page ([PR](https://github.com/WordPress/phpunit-test-reporter/pull/84)).
* Change to `integer` built-in type for `commit` field, following updates in WordPress 5.5.


= 0.2.0 (September 17th, 2024) =
* Prevent invalid HTML markup on test result pages.
* Add a custom Post_List_Table for the results post type that lacks inline edit/quick edit.
* Don't use _get_list_table() as that create the object and triggers some queries. Including the file directly works just as well.
* Allow for multiple reports per commit for the same test bot.
