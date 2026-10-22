# wporg-gp-translation-events

Here we are implementing Translation Events, as discussed in our Polyglots Make P2 Post: [Translation Events Prototype](https://make.wordpress.org/polyglots/2024/02/28/translation-events-prototype/).

## Development environment

This plugin is part of the translate.wordpress.org development environment in `environments/translate` at the root of this repository, which runs it alongside GlotPress and the other translate.wordpress.org plugins. From the `environments` directory:

```shell
npm run translate:env start
```

See `environments/README.md` for details.

## Tests

The test suite runs in a dedicated wp-env test environment. From the `environments` directory:

```shell
npm run translate:test
```

If you want to run only one test, start the test environment once and then run PHPUnit directly with a filter:

```shell
npm run translate:test:env -- run tests-cli --env-cwd=wp-content/plugins/wporg-gp-translation-events phpunit -- --filter method_name
```
