# wporg-gp-translation-events

Here we are implementing Translation Events, as discussed in our Polyglots Make P2 Post: [Translation Events Prototype](https://make.wordpress.org/polyglots/2024/02/28/translation-events-prototype/).

## Development environment
First follow [instructions to install `wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/#prerequisites).

Then install dependencies:

```shell
composer install
```

Then you can run a local WordPress instance with the plugin installed:

```shell
composer dev:start
```

Once the environment is running, you must create the database tables needed by this plugin:

```shell
composer dev:db:schema
```

WordPress is now running at http://localhost:8888, user: `admin`, password: `password`.

### Local environment

If you are not using `wp-env`, you need to add the tables to the database of your local environment. To do this, you can run this command from the plugin folder:

```shell
wp db query < schema.sql
```

### Tests

You can run tests in `wp-env` with the following command:

> Note that `wp-env` must be running.

```shell
composer dev:test
```

If you want to run only one test, you can use the following command:

```shell
composer dev:test -- --filter methods_name
```
