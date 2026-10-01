# freescout.wordpress.net

[FreeScout](https://freescout.net/) modules for the WordPress.org email helpdesk, which replaces HelpScout.

| Module | What it does |
|---|---|
| [WPOrgAkismet](Modules/WPOrgAkismet) | Checks new conversations from senders with Akismet, and teaches it from what agents mark as spam. |
| [WPOrgSidebar](Modules/WPOrgSidebar) | Shows the sender's WordPress.org profile, forum notes, plugins and themes, and privacy requests next to each conversation. |
| [WPOrgSite](Modules/WPOrgSite) | Adapts FreeScout to how WordPress.org runs it: modules can't be updated or deleted from the Modules page, and its cards fill the width in columns. |
| [WPOrgSSO](Modules/WPOrgSSO) | Agents log in with their WordPress.org account, through login.wordpress.org; there's no other way in. Every user is connected to a WordPress.org account, and new users are created from one. Name, email, and avatar are updated from it at every login. |
| [WPOrgWebhooks](Modules/WPOrgWebhooks) | Sends conversation events to WordPress.org, which records them as contributor stats. |

WPOrgSidebar, WPOrgWebhooks, and WPOrgSSO talk to [`api.wordpress.org/dotorg/freescout/`](../api.wordpress.org/public_html/dotorg/freescout), which does the WordPress.org lookups.

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

Configuration, in FreeScout's `.env`. Required:

| Variable | Value |
|---|---|
| `WPORG_API_SECRET` | Shared secret; must match `FREESCOUT_SECRET` on api.wordpress.org. |
| `WPORG_SSO_IDP_CERT` | The identity provider's signing certificate, on one line, with or without the BEGIN/END lines. Until it and `WPORG_API_SECRET` are set, logins stay as they are, so users can be connected first. Once they are, new logins go through WordPress.org, and sessions from before go on for a day; Manage » System » Tools » Logout Users ends them sooner. After that day, switching enforcement off and on again ends every session from while it was off, including a password login of whoever switches it back on: have break-glass or a connected account ready. |
| `WPORG_AKISMET_KEY` | FreeScout's own Akismet API key. Without it, `WPOrgAkismet` checks nothing, and spam comes in like any other email. |

Optional:

| Variable | Default | Value |
|---|---|---|
| `WPORG_API_URL` | `https://api.wordpress.org/dotorg/freescout/` | Where the helpdesk endpoints are. |
| `WPORG_SSO_IDP_ENTITY_ID` | `https://login.wordpress.org` | The identity provider's entity ID, from its settings page on login.wordpress.org. |
| `WPORG_SSO_IDP_URL` | `https://login.wordpress.org/wp-login.php?action=idp` | The identity provider's login URL, from the same page. |
| `WPORG_SSO_PASSWORD_LOGIN` | `false` | Break-glass: `true` lets administrators log in with a FreeScout password at `/login?password=1`. `php artisan wporgsso:password <email>` gives them one; password reset emails stay closed. Every such login is logged. |

FreeScout's queue worker must be running (FreeScout's standard cron entry starts it), since `WPOrgWebhooks` sends events, `WPOrgSSO` updates avatars, and `WPOrgAkismet` reports to Akismet from the queue.

Behind a proxy, set core's `APP_TRUSTED_PROXIES`, so `WPOrgSSO` rate-limits its login endpoints per visitor rather than for everyone at once. Leave `SESSION_SAME_SITE` unset or `lax`: with `strict`, the browser drops the session cookie on the way back from login.wordpress.org, and every login fails. `WPOrgSSO` also hands logins over and forgets connections through FreeScout's cache, so every web server and `artisan` need the same cache store, and `artisan` runs as the web server user.
