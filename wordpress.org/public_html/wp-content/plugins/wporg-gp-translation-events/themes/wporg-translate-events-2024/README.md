# Theme for translate.wordpress.org/events

This is the theme used by the [Translations Events](https://translate.wordpress.org/events) section of the `translate.wordpress.org` site (from now on referred to as `wporg-translate`).

> Note that currently not all pages are using this theme yet, work is ongoing to rework pages so they use this theme.

## Context
This section provides context useful to understand why this theme is structured the way it is, and how it integrates into the wider environment at `wporg-translate`.

### Themes at `wporg-translate`
The `wporg-translate` site does not use WordPress themes in the traditional way. Instead, requests are handled by a `Route`, which then renders the template of the requested page. In a traditional WP site, WP itself would decide which page to render, and (for example) apply the header and footer of the currently-active theme.

At `wporg-translate` this is not the case, the `Route` and the template of the page completely control the markup being rendered, and the styles being used.

### How it used to work

> Note that currently most pages at `wporg-translate` still work this way. Work is ongoing to rework pages so they work as described in the [next section](#how-it-works-now-with-this-theme).

As described above, a `Route` intercepts the request, then calls the PHP file of the template of the requested page. That PHP file registers whatever styles and scripts are needed, then renders the markup of the full page, including header and footer.

The templates and styles are provided by the following plugins:

- `GlotPress`
- `wporg-gp-customizations`
- `wporg-gp-translation-events`
- Maybe other `wporg-gp-*` plugins

### How it works now, with this theme
This new behaviour is enabled for a given page when the developer adds a call to `$this->use_theme()` in the `Route` of said page. This results in this theme being "faked" as the currently-active theme for only the ongoing request (see [`Theme_Loader` in `wporg-gp-translation-events`](https://github.com/WordPress/wporg-gp-translation-events/blob/trunk/includes/theme-loader.php)).

In this case, when the `Route` intercepts the request, instead of calling the PHP template file, it instead renders a block provided by this theme, that is specific to the page to render ( e.g. `wporg-translate-events-2024/page-events-my-events`). This block renders:

- The header that is common to all pages.
- The content of the specific page being rendered.
- The footer.
