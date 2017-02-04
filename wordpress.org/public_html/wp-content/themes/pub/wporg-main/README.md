### WordPress.org

This Theme serves as a parent theme for all themes used on WordPress.org.
The idea is to collect commonly used styles and components here, for all child themes to use.

#### Getting Started

1. Create a child theme and specify `wporg` as the template for it.
1. Copy `package.json`, `Gruntfile.js`, `.jshinignore`, and `.jshintrc` into your child theme.
1. Replace project-specific information in `package.json`.
1. Run `npm install` (this can take a little while).
1. Run `grunt css` to create the CSS folder structure.
1. Copy `css/style.scss` into your child theme.

Running `grunt watch` or `grunt css` now will pull in all Sass files from parent and child theme.

#### Developing

```
grunt watch
```
Watches JavaScript and Sass files for changes to run linters and builds Sass, etc. 
 
#### Committing

Before committing changes, please create a build version to keep the file size down.

```
grunt build
svn ci
```
