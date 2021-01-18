# WordPress.org Pattern Directory Theme


## Getting Started

* In the project root, run `composer install` or `composer update` to install the wporg parent theme
* Run `yarn workspaces build` to build all 3 projects
* Optionally, make sure the theme is active: `yarn wp-env run cli "theme activate pattern-directory"`


To install the wporg parent theme without composer:

```sh
svn co https://meta.svn.wordpress.org/sites/trunk/wordpress.org/public_html/wp-content/themes/pub/wporg path/to/wp-content/themes/wporg
```


## Development

To build the CSS files, run `yarn start` in this directory, or `yarn workspaces wporg-pattern-directory-theme start` anywhere in the project. This triggers a watch script so your files will build as you work, with sourcemapping.

Run `yarn workspaces build` before committing to subversion, to generate the production-ready build files of each project. Make sure not to commit the *.css.map files.
