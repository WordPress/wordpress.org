# jobs.wordpress.net

The JobsWP [plugin](public_html/wp-content/plugins/jobswp) and [theme](public_html/wp-content/themes/jobswp) powering [jobs.wordpress.net](https://jobs.wordpress.net/), the official WordPress job board.

## Development

Run the site locally with sample job categories and posts, and run the plugin's tests:

```bash
cd ../environments
npm install
npm run jobs:env start   # http://localhost:8888
npm run jobs:test
```

See the [environments guide](../environments/README.md#jobs) for details.

Contributions follow the usual Meta workflow: open a pull request on the GitHub mirror, referencing a Meta Trac ticket.

## Deployment

Deploys are manual. Committing to the Meta SVN repository doesn’t update the site: someone with SSH access to the server runs `svn up` in the site’s checkout to make a change live.
