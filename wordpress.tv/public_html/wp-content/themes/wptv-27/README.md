# WordPress.tv Theme (wptv-27)

Block theme for [WordPress.tv](https://wordpress.tv/), the WordPress community site that publishes videos from WordCamps, meetups and other community events.

wptv-27 replaces the legacy classic theme (`wptv2`). It is built with Full Site Editing (block templates, template parts, patterns and `theme.json`), and relies on WordPress core features instead of hand-written code wherever possible. Its first release aims to keep `wptv2`'s content, data model and URLs. The deliberate differences, such as the legacy API returning at most 100 videos per request, are listed in the project's [decision log](https://github.com/nilovelez/wptv-27/blob/trunk/docs/decisions.md).

## Documentation

The project documentation lives in [nilovelez/wptv-27](https://github.com/nilovelez/wptv-27): current status and [handoff](https://github.com/nilovelez/wptv-27/blob/trunk/docs/handoff.md), [plan](https://github.com/nilovelez/wptv-27/blob/trunk/docs/plan.md), [decisions](https://github.com/nilovelez/wptv-27/blob/trunk/docs/decisions.md), [route inventory](https://github.com/nilovelez/wptv-27/blob/trunk/docs/routes.md) and the migration tests.

## Constraints

WordPress.tv runs on the WordPress.com multisite. That means:

- No custom plugins can be installed. Only the plugins available on the multisite can be used, so every site-specific feature lives in this theme.
- Videos are hosted on VideoPress.
- Several VideoPress helpers only exist on WordPress.com. They are always called behind `function_exists()` checks, so the theme also runs in local environments.

See [`docs/wpcom-constraints.md`](https://github.com/nilovelez/wptv-27/blob/trunk/docs/wpcom-constraints.md) for details.

The most important rule: **existing URLs must not change**. See [`docs/routes.md`](https://github.com/nilovelez/wptv-27/blob/trunk/docs/routes.md).

## Development

To run the theme locally with sample content and its PHPUnit suite, use the WordPress.tv environment in [`environments/`](https://github.com/WordPress/wordpress.org/tree/trunk/environments): `npm run wptv:env start` and `npm run wptv:test`.

## Language

All project content is written in **US English (en_US)**, following the WordPress standard: code, function names, comments, translatable strings, commit messages and documentation.

## License

GNU General Public License v2 or later.
