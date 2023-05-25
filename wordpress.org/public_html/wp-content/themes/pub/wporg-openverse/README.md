# WordPress.org Openverse Theme

This theme loads Openverse in an `iframe` under the WordPress.org site.

## Development

Follow these steps to set up a local playground for the theme:

0.  Install all the prerequisites.

    1.  **Required:** Node.js 14
    2.  **Required:** Composer
    3.  **Required:** Subversion
    4.  **Recommended:** Docker (to use the automatic setup)

1.  Build the parent theme WordPress.org theme.

    1.  `cd` into the parent theme directory at `../wporg`
        (i.e. `wordpress.org/wordpress.org/public_html/wp-content/themes/pub/wporg`).
    2.  Install all the required `npm` packages.
        ```bash
        $ npm install
        ```
        If you face issues installing Sass, try the following command, and
        `npm install` again.
        ```bash
        $ npm install node-sass@npm:sass
        $ npm install
        ```
    3.  Build the theme assets.
        ```bash
        $ npm run build
        ```
    4.  `cd` back to the Openverse theme directory at `../wporg-openverse`
        (i.e. `wordpress.org/wordpress.org/public_html/wp-content/themes/pub/wporg-openverse`).

2.  Build the MU plugins.

    1.  The mu-plugins are set up as composer dependencies, so install those:
        ```bash
        $ composer install
        ```
    2.  `cd` into the directory at `./mu-plugins/wporg-mu-plugins`
        (i.e. `wordpress.org/wordpress.org/public_html/wp-content/themes/pub/wporg-openverse/mu-plugins/wporg-mu-plugins/`).
    3.  Install all the required `npm` packages.
        ```bash
        $ npm install
        ```
    4.  Build the plugin assets.
        ```bash
        $ npm run build
        ```
    5.  `cd` back to the Openverse theme directory at `../..`
        (i.e. `wordpress.org/wordpress.org/public_html/wp-content/themes/pub/wporg-openverse`).

3.  Tell WordPress to load the `mu-plugins`. Since these are in nested folders, they're not loaded automatically. You'll need to create a new file and `require` them.

    1.  Create a new file `./mu-plugins/loader.php`
    2.  Add the following to this new file:
        ```php
        <?php
        require_once WPMU_PLUGIN_DIR . '/pub/locales.php';
        require_once WPMU_PLUGIN_DIR . '/wporg-mu-plugins/mu-plugins/blocks/global-header-footer/blocks.php';
        require_once WPMU_PLUGIN_DIR . '/wporg-mu-plugins/mu-plugins/skip-to/skip-to.php';
        ```

4.  You can choose to set up a new environment automatically or work in an
    existing environment with manual setup.

    **Automatic:**  
    You can set up the development environment automatically using
    `@wordpress/env`. _This requires Docker._

    1.  Install `@wordpress/env` globally as an `npm` package.
        ```bash
        $ npm install -g @wordpress/env@latest
        ```
    2.  Scaffold a dev environment.
        ```bash
        $ wp-env start
        ```
    3.  Follow the instructions in the console, and then your browser, to set up
        your WordPress install. This site will have the `wporg` (parent) and
        `wporg-openverse` (child) themes installed. For detailed instructions,
        please read [the wp-env docs](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).
    4.  Edit the `.htaccess` file to prevent Apache 404 errors.
        ```bash
        $ wp-env run cli bash
        bash-5.1$ printf "RewriteEngine on\nFallbackResource /index.php\n" > .htaccess
        bash-5.1$ exit
        ```

    **Manual:**  
    If you prefer a manual approach, you can also set up your own WordPress
    instance and load both the `wporg` (parent) and `wporg-openverse` (child)
    themes into the `wp-content/themes` directory. You must also load the MU
    plugins and activate them using the `mu-plugins.php` file.

5.  Set up the locale database. The plugin was installed in step 2, but it pulls from a separate database of locale data.

    1.  Download the SQL file [wporg_locales.sql](https://raw.githubusercontent.com/WordPress/pattern-directory/trunk/.wp-env/data/wporg_locales.sql) to the theme directory.
        ```bash
        curl -O https://raw.githubusercontent.com/WordPress/pattern-directory/trunk/.wp-env/data/wporg_locales.sql
        ```
    2.  Import the file.
        ```bash
        $ wp-env run cli "wp db import wp-content/themes/wporg-openverse/wporg_locales.sql"
        ```

6.  Activate and customize the theme.

    1.  Log into `/wp-admin`. If you used `@wordpress/env`, your username will
        be 'admin' and password will be 'password'.
    2.  Under Appearance > Themes, activate the theme 'WordPress.org Openverse'.
    3.  To change the embed URL, open the customizer at Appearance > Customize
        and update the value in the 'Openverse embed' panel.

7.  Test message passing.

    1.  Change the Openverse embed to
        `/wp-content/themes/wporg-openverse/js/message_test.html`.
    2.  Visit the site and interactively test the messages.

8. Test redirects.

    1.  Visit some test URLs.
        1.  https://ru.wordpress.org/openverse → ...
        2.  https://wordpress.org/openverse/search/?q=dog → ...
    2.  See the target redirect URL as a comment inside the dev tools.
        1.  ... → https://openverse.wordpress.net/ru/
        2.  ... → https://openverse.wordpress.net/search/?q=dog
    3.  Change the language in Settings > General to see how the locale factors
        into the redirect path.

## Related links

- Source code
    - [Openverse](https://github.com/WordPress/openverse)
    - [Openverse catalog](https://github.com/WordPress/openverse-catalog)
    - [Openverse API](https://github.com/WordPress/openverse-api)
    - [Openverse frontend](https://github.com/WordPress/openverse-frontend)
- [Make site](https://make.wordpress.org/openverse/)
