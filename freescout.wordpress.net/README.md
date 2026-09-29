# freescout.wordpress.net

[FreeScout](https://freescout.net/) modules for the WordPress.org email helpdesk, which replaces HelpScout.

| Module | What it does |
|---|---|
| [WPOrgSidebar](Modules/WPOrgSidebar) | Shows the sender's WordPress.org profile, forum notes, plugins and themes, and privacy requests next to each conversation. |
| [WPOrgWebhooks](Modules/WPOrgWebhooks) | Sends conversation events to WordPress.org, which records them as contributor stats. |

Both talk to [`api.wordpress.org/dotorg/freescout/`](../api.wordpress.org/public_html/dotorg/freescout), which does the WordPress.org lookups.

## Development

Run FreeScout locally with these modules, test mail, and a mock of the api.wordpress.org endpoints:

```bash
cd environments
npm install
npm run freescout:start   # http://127.0.0.1:8890, admin@wordpress.test / password
npm run freescout:test
```

See the [environments guide](../environments/README.md#freescout-helpdesk) for details, and [AGENTS.md](AGENTS.md) for the rules modules must follow. The most important one: FreeScout core is updated independently of this repository, so modules must survive core changes and fail softly.

Contributions follow the usual Meta workflow: open a pull request on the GitHub mirror, referencing a Meta Trac ticket.

## Deployment

FreeScout core is installed and updated by the systems team. This folder only adds modules:

1. Check out `freescout.wordpress.net/Modules/` as FreeScout's `Modules/` folder. Modules installed through FreeScout's UI, like premium ones, sit next to ours as unversioned folders.
2. After every deploy, and after every FreeScout core update, run `php artisan freescout:after-app-update` as the web server user. It clears FreeScout's caches, runs migrations, and restarts the queue worker.
3. An admin switches new modules on under Manage » Modules. To remove a module, switch it off there first, and remove its code in a later deploy.

Configuration, in FreeScout's `.env`:

| Variable | Value |
|---|---|
| `WPORG_API_URL` | `https://api.wordpress.org/dotorg/freescout/` (the default) |
| `WPORG_API_SECRET` | Shared secret; must match `FREESCOUT_SECRET` on api.wordpress.org. |

FreeScout's queue worker must be running (FreeScout's standard cron entry starts it), since `WPOrgWebhooks` sends events from the queue.
