### WordPress.org Plugins

#### Developing

```
npm install
npm start # Watch and rebuild the stylesheet, blocks, and JS client.
```

The Sass partials under `client/styles/{settings,tools,objects,components}/` are
aggregated by hand-maintained `_*.scss` index files. When adding a new partial,
remember to `@import` it from the matching index file, or it won't be bundled.

#### Committing

Before committing changes, please create a build version to keep the file size down.

```
npm run build
svn ci
```
