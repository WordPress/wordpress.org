# WordPress.org Openverse Theme

This theme loads Openverse in an `iframe` under the WordPress.org site.

## Development

Follow these steps to set up a local playground for the theme:

0.  Install all the prerequisites.

    1.  **Required:** Node.js.
    2.  **Recommended:** Docker (to use the automatic setup)

1.  Build the parent theme WordPress.org theme.

    1.  `cd` into the parent theme directory at `../wporg` 
        (i.e. `wordpress.org/wordpress.org/public_html/wp-content/themes/pub/wporg`).
    3.  Install all the required `npm` packages.
        ```bash
        $ npm install
        ```
    3.  Build the theme assets.
        ```bash
        $ npm run build
        ```
    4.  `cd` back to the Openverse theme directory at `../wporg-openverse`
        (i.e. `wordpress.org/wordpress.org/public_html/wp-content/themes/pub/wporg-openverse`).

2.  You can choose to set up a new environment automatically or work in an
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
        please read [their docs](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).

    **Manual:**  
    If you prefer a manual approach, you can also set up your own WordPress
    instance and load both the `wporg` (parent) and `wporg-openverse` (child)
    themes into the `wp-content/themes` directory.

3.  Activate and customize the theme.

    1.  Log into `/wp-admin`.
    2.  Under Appearance > Themes, activate the theme 'WordPress.org Openverse'.
    3.  To change the embed URL, open the customizer at Appearance > Customize
        and update the value in the 'Openverse embed' panel.

4.  Test message passing.

    1.  Change the Openverse embed to
        `/wp-content/themes/wporg-openverse/js/message_test.html`.
    2.  Visit the site and interactively test the messages.

## Related links

- Source code
    - [Openverse](https://github.com/WordPress/openverse)
    - [Openverse catalog](https://github.com/WordPress/openverse-catalog)
    - [Openverse API](https://github.com/WordPress/openverse-api)
    - [Openverse frontend](https://github.com/WordPress/openverse-frontend)
- [Make site](https://make.wordpress.org/openverse/)
