# freescout.wordpress.net

[FreeScout](https://freescout.net/) modules for the WordPress.org email helpdesk, which replaces HelpScout.

Each module lives in its own directory in [`Modules/`](Modules).

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
