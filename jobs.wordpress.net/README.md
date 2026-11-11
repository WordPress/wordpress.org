# jobs.wordpress.net

The JobsWP [plugin](public_html/wp-content/plugins/jobswp) and [theme](public_html/wp-content/themes/jobswp) powering [jobs.wordpress.net](https://jobs.wordpress.net/), the official WordPress job board.

## Block theme redesign

A block theme rebuild of the site is in progress alongside the classic theme. It isn't active on production.

| Path | What it is |
|---|---|
| [`themes/wporg-jobs-2026`](public_html/wp-content/themes/wporg-jobs-2026) | The block theme, "Jobs Theme 2026". Presentation only: `theme.json` design tokens, templates, parts and one small stylesheet per block type. |
| [`plugins/wporg-jobs-blocks`](public_html/wp-content/plugins/wporg-jobs-blocks) | Companion plugin: Block Bindings sources for job data, the `wporg-jobs/open-to-work` block, Interactivity API filtering for the homepage job browser, the job form partials and site-level hooks. Meant to move into `jobswp` eventually. |
| [`content/pages`](content/pages) | Block markup for the pages the theme expects. Page content lives in the database, so these aren't deployed. |

It requires WordPress 7.1 or later (per-viewport style states, and the core accordion, tabs and terms-query blocks). Jetpack Subscriptions and Stats are optional.

To try it, activate `wporg-jobs-blocks` and then the "Jobs Theme 2026" theme, and set up these pages from `content/pages/`. The local environment already creates FAQ, Feedback, Post a Job and Remove a Job, so update those pages rather than adding new ones: navigation and the form hooks rely on their slugs.

- **Home** (`home`): create it with `home.html`, template "Landing", and set it as the static homepage.
- **FAQ** (`faq`): replace the content with `faq.html`. Default template.
- **Feedback** (`feedback`): replace the content with `feedback.html`. Template "Wide page".
- **Post a Job** (`post-a-job`): no content, template "Wide page". The Jobs plugin adds the form.
- **Remove a Job** (`remove-a-job`): replace the content with `remove-a-job.html`. Template "Wide page". The companion plugin adds the form.

The local environment below makes both available without activating them.

## Development

Run the site locally with sample job categories and posts, and run the plugin's tests:

```bash
cd ../environments
npm install
npm run jobs:env start   # http://localhost:8888
npm run jobs:env stop    # the test environment uses the same port
npm run jobs:test
```

See the [environments guide](../environments/README.md#jobs) for details.

Contributions follow the usual Meta workflow: open a pull request on the GitHub mirror, referencing a Meta Trac ticket.

## Deployment

Deploys are manual. Committing to the Meta SVN repository doesn’t update the site: someone with SSH access to the server runs `svn up` in the site’s checkout to make a change live.
