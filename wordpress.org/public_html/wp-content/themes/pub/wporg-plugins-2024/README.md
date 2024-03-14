### WordPress.org Plugins

#### Developing

```
npm install
grunt watch # Run linters, build Sass, JS client, etc.
```

#### Committing

Before committing changes to `js/client`, please create a build version to keep the file size down.

```
grunt build
svn ci
```
