# jobs.wordpress.net

The plugins and themes powering [jobs.wordpress.net](https://jobs.wordpress.net/), the official WordPress job board.

| Path | What it is |
|---|---|
| [`plugins/jobswp`](public_html/wp-content/plugins/jobswp) | The JobsWP plugin: the job post type, the Post a Job and Remove a Job forms, moderation, the contact form and the email digest. |
| [`themes/jobswp`](public_html/wp-content/themes/jobswp) | The classic theme. Active on production. |
| [`themes/wporg-jobs-2026`](public_html/wp-content/themes/wporg-jobs-2026) | “Jobs Theme 2026”, a block theme. Not active on production. Presentation only: `theme.json` design tokens, templates, parts and one small stylesheet per block type. |
| [`plugins/wporg-jobs-blocks`](public_html/wp-content/plugins/wporg-jobs-blocks) | The plugin the block theme needs: Block Bindings sources for job data, the `wporg-jobs/open-to-work` block, Interactivity API filtering for the homepage job browser, the job form partials and site-level hooks. Requires `jobswp`. |

## Block theme

The block theme and its plugin require WordPress 7.1 or later and PHP 8.0. Jetpack Subscriptions and Stats are optional.

To use it, activate `wporg-jobs-blocks`, then “Jobs Theme 2026”, and set up these pages with the block markup in [`environments/jobs/pages`](../environments/jobs/pages). Page content lives in the database, so it isn’t deployed with the theme. Navigation and the forms rely on the slugs.

- **Home** (`home`): content from `home.html`, template “Landing”, set as the static homepage.
- **FAQ** (`faq`): content from `faq.html`, default template.
- **Feedback** (`feedback`): content from `feedback.html`, template “Wide page”.
- **Post a Job** (`post-a-job`): no content, template “Wide page”. The JobsWP plugin adds the form.
- **Remove a Job** (`remove-a-job`): content from `remove-a-job.html`, template “Wide page”. `wporg-jobs-blocks` adds the form.

The local environment does all of this when it starts.

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
