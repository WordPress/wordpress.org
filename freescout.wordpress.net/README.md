# freescout.wordpress.net

[FreeScout](https://freescout.net/) modules for the WordPress.org email helpdesk, which replaces HelpScout.

| Module | What it does |
|---|---|
| [WPOrgSidebar](Modules/WPOrgSidebar) | Shows the sender's WordPress.org profile, forum notes, plugins and themes, and privacy requests next to each conversation. |
| [WPOrgWebhooks](Modules/WPOrgWebhooks) | Sends conversation events to WordPress.org, which records them as contributor stats. |
| [WPOrgSSO](Modules/WPOrgSSO) | Agents log in with their WordPress.org account, through login.wordpress.org; there's no other way in. Every user is connected to a WordPress.org account, and new users are created from one. Name, email, and avatar are updated from it at every login. |

All three talk to [`api.wordpress.org/dotorg/freescout/`](../api.wordpress.org/public_html/dotorg/freescout), which does the WordPress.org lookups.

## Development

Run FreeScout locally with these modules, test mail, and a mock of the api.wordpress.org endpoints:

```bash
cd environments
npm install
npm run freescout:start   # http://127.0.0.1:8890, log in as "admin" at the mock WordPress.org login
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
| `WPORG_SSO_IDP_ENTITY_ID` | The identity provider's entity ID, from its settings page on login.wordpress.org. Default: `https://login.wordpress.org`. |
| `WPORG_SSO_IDP_URL` | The identity provider's login URL, from the same page. Default: `https://login.wordpress.org/wp-login.php?action=idp`. |
| `WPORG_SSO_IDP_CERT` | The identity provider's signing certificate, without the BEGIN/END lines. Until it and `WPORG_API_SECRET` are set, logins stay as they are, so users can be connected first. |
| `WPORG_SSO_PASSWORD_LOGIN` | Break-glass: `true` lets administrators log in with a FreeScout password at `/login?password=1`. `php artisan wporgsso:password <email>` gives them one; password reset emails stay closed. Off by default; every such login is logged. |

FreeScout's queue worker must be running (FreeScout's standard cron entry starts it), since `WPOrgWebhooks` sends events and `WPOrgSSO` updates avatars from the queue.
