### WordPress.org Plugins

#### Developing

```
npm install
grunt webpack:watch-dev # Build JS client.
grunt watch # Run linters, build Sass, etc.
```

#### Committing

Before committing changes to `js/client`, please create a build version to keep the file size down.

```
grunt build
svn ci
```
