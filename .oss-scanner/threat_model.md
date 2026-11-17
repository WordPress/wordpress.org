# Threat model: WordPress.org Meta

## What this project does and where untrusted input enters

This repository is the code behind WordPress.org and its sister sites, mirrored from the meta.svn.wordpress.org
Subversion repository. The scan is of `trunk`. Production runs WordPress trunk on PHP 8.4, as one large multisite
network plus a few separate ones.

Most of it is WordPress plugins, themes and mu-plugins:

- `wordpress.org/public_html/wp-content/`: the Plugin Directory (`plugins/plugin-directory`), Theme Directory
  (`plugins/theme-directory`), Support Forums (`plugins/support-forums`, `plugins/wporg-bbp-*`, on bbPress),
  translate.wordpress.org (`plugins/wporg-gp-*`, on GlotPress), the Photo Directory, the handbooks, Make sites,
  the locale ("Rosetta") sites, the themes under `themes/pub/`, and the network-wide mu-plugins in `mu-plugins/pub/`.
- `common/includes/wporg-sso/`: the single sign-on shared by WordPress.org, bbPress.org, BuddyPress.org and the
  locale sites.
- `jobs.wordpress.net/`, `wordpress.tv/`, `browsehappy.com/`, `buddypress.org/`, `wp-themes.com/`.

The rest is not WordPress:

- `api.wordpress.org/public_html/`: the public APIs that every WordPress site calls (core, plugin and theme updates,
  translations, events, patterns), and webhooks and service endpoints under `dotorg/` (GitHub, Slack, SVN, Trac,
  FreeScout, HelpScout). Most of them bootstrap WordPress through `wp-init-ondemand.php`, which lives in a private
  repository, so they can only be read here, not run. The ones with standalone PHPUnit suites can be run (see below).
- `freescout.wordpress.net/`: modules for the FreeScout helpdesk (Laravel) that the plugin and theme review teams
  answer email in.
- `trac.wordpress.org/`, `svn.wordpress.org/`, `profiles.wordpress.org/`, `common/includes/slack/`: Trac templates
  and configuration, Subversion hooks, profile handlers, and Slack integrations. They can only be read here, not run.

### Who can do what

Anyone can create a WordPress.org account, and it works on every site. That is the attacker to assume. Untrusted
input enters through:

- Requests from anonymous visitors, and from any logged-in account with no role on the site: page views, search,
  feeds, the REST API (`/wp-json/`), `admin-ajax.php` and `admin-post.php`, and the APIs under api.wordpress.org.
- Anything a logged-in account can post: forum topics and replies, reviews, translation suggestions, photo
  submissions, job postings, profile fields, and reports.
- **Plugins and themes.** Anyone can submit a plugin or theme, and once it is approved, its owner controls everything
  in its SVN repository: `readme.txt`, plugin headers, `style.css`, `blueprint.json`, screenshots, banners, block
  metadata, and every file in the zip. The directories parse all of it and render it to visitors and reviewers. A
  plugin committer or theme author is an untrusted actor.
- Email into the FreeScout helpdesk, which anyone can send.
- Data fetched from elsewhere: SVN, GitHub, and remote URLs that users supply.

Roles that are appointed, not taken, are trusted: plugin and theme reviewers, forum moderators and keymasters,
translation editors (PTEs and GTEs), locale site editors, team representatives, and site administrators. Roles on
WordPress.org sites are assigned individually; no site grants Contributor, Author or Editor on sign-up.

### Which sites count, and for whom

The WordPress HackerOne program (https://hackerone.com/wordpress) decides what counts, site by site, with the
narrower eligibility announced at
https://make.wordpress.org/security/2026/09/01/updates-to-the-wordpress-vulnerability-disclosure-program/, which
applies to all of this repository. Find the row for where the code runs:

| Where the code runs | Counts when the attacker is | Highest severity |
|---|---|---|
| wordpress.org and its subdomains: the directories, forums, Photo Directory, translate, login, make, developer, learn, the locale sites | Anonymous, any account with the role every new account gets, or a plugin or theme owner | Critical |
| api.wordpress.org, profiles.wordpress.org, bbpress.org, buddypress.org | Anonymous | Critical |
| Trac and the Subversion hooks (`trac.wordpress.org/`, `svn.wordpress.org/`) | Anonymous | Low, or High when it lets the attacker change source code |
| jobs.wordpress.net, freescout.wordpress.net, and the other `*.wordpress.net` sites | Anonymous | Low |
| wordpress.tv, browsehappy.com, wp-themes.com | Not covered: wordpress.tv runs on WordPress.com, and the others are not in the program | None |

Where only an anonymous attacker counts, a finding that needs an account still counts if it gives a clear path to
administrator or to code execution on the server.

## Components that matter most / least

Most important:

- Authentication and the SSO: login redirects, cross-domain tokens, `redirect_to` handling, application passwords,
  two-factor checks.
- Capability checks, nonces, and REST permission callbacks. A common real defect is a check against a **global**
  capability where it must be **per object** (this plugin's committers, this forum's moderators). Check secondary
  read paths separately: feeds, REST search and collection endpoints, sitemaps, oEmbed, and the AJAX twin of an
  action often miss a check the primary path has.
- Output escaping of anything a plugin or theme owner controls, on directory pages and in the admin screens
  reviewers use.
- Zip, SVN and file handling in the directories: plugin and theme uploads, the SVN importers, readme and header
  parsing, and anything that builds a path or a shell command from plugin data (look for `escapeshellarg()`).
- SQL built through `$wpdb` and the GlotPress and bbPress query layers.
- Secrets and signed requests: webhook signatures, shared secrets, and the tokens in subscription and unsubscribe
  links.
- Server-side requests to URLs a user supplies.
- Private data: email addresses, private and pending content, unreleased plugin code, closed plugins' reasons, and
  security reports.

Out of scope:

- `environments/` and `.oss-scanner/`: local development tooling that never runs in production. Its mocks deliberately
  stand in for production services, and its dev-login helpers deliberately skip authentication.
- Tests, and third-party code bundled in the repository (`vendor/`, `libs/`, and copies of external projects), except
  where the way WordPress.org uses it creates the vulnerability.
- WordPress core, Gutenberg, bbPress, BuddyPress, GlotPress, Trac, Jetpack and other plugins installed from elsewhere.
  Report those upstream.

`.github/` is in scope: the CI and pull request workflows run against untrusted pull requests.

## How to exercise it

The image has a working copy of every local environment in `environments/` (its `README.md` describes each one),
built the way `wp-env` would build it, but on one Apache and one MariaDB instead of in Docker containers.

- Run `wporg start` first. It starts MariaDB, Apache, and FreeScout's mail servers and mock API, and is safe to run
  again. `wporg stop` stops them.
- `wporg sites` lists each site, its URL, and the environment config it was built from. Each WordPress site is at
  `http://localhost:<port>/`; use `localhost`, not `127.0.0.1`, as WordPress redirects to its own URL. Sites ending in
  `-test` exist to run test suites.
- Every WordPress site has `admin` / `password` (Administrator, or Super Admin on multisite). Some environments
  seed more accounts: the Support Forums seed one per forum role (all with the password `password`), translate seeds
  `translator`, a plain subscriber. `environments/README.md` lists them.
- `wporg run <site> wp ...` runs WP-CLI in a site as the web server's user. `wporg run <site> --cwd=wp-content/... --
  <command>` runs anything else there. Plugins, themes and mu-plugins that come from this repository are symlinks into
  `/src`, so a patch to `/src` takes effect on the next request.
- PHPUnit: `cd /src/environments && npm run <name>:test`, for each `*:test` script in `environments/package.json`
  (`npm run plugins:test`, `npm run support:test`, ...). They work as they do with Docker: `wp-env` here is a stand-in
  that runs commands in the sites listed by `wporg sites`. The standalone suites in `.github/unit-tests-suites.yml`
  run with `/src/vendor/bin/phpunit` from their working directory. `wporg check --tests` runs every suite.
- FreeScout is at `http://127.0.0.1:8890/`. Log in with WordPress.org: the mock login page offers `admin`,
  `reviewer`, `no2fa` and `blocked`. Send email into it over SMTP to `greenmail:3025`, for example to
  `plugins@wordpress.test`; it is fetched within a minute. Mail it sends lands in Mailpit at `http://127.0.0.1:8891/`.
  Run its tests with `wporg run freescout -- bash /srv/env/bin/test.sh`.
- PHP errors go to `/var/log/php-error.log`, Apache's to `/var/log/apache2/<site>-error.log`.

What differs from production: the environments stand in for production-only services with mocks under
`environments/mocks/` and each environment's `mu-plugins/` (profiles, ratings, two-factor, locales tables), and SSO
across domains is not reproduced on the WordPress sites. `WP_DEBUG` is on locally and off in production. A finding
that only works because of a mock, a local setting, or the missing SSO is not a finding; confirm it on the production
code path.

### Reproducers must not fabricate the attacker's state

Set up only what a legitimate administrator would: create users at the roles the attacker could really hold, set real
options, add content. Then drive the attack **as the claimed actor**, through the normal interface. Do not mint a
nonce or a token as an administrator, flip the target option through the database or WP-CLI, grant the attacker a
role or capability they are supposed to gain, or drive the action from an administrator's session. Reading the
database to observe the outcome is fine. If the attack only works through such a shortcut, it is not a finding.

To act as a plugin owner, create the plugin and add the attacker's account as a committer, as plugin review would on
approval; then change the plugin only through what a committer controls.

For XSS, show that the payload executes in a browser context, not only that it appears in the HTML.

## How you rate severity

Rate a finding with this table, then cap it at the highest severity for its site in the table above. Only attackers
who count on that site apply.

| Finding | Severity |
|---|---|
| Code execution on the server, unchained | Critical |
| Taking over another account, or gaining a trusted role, unchained | Critical |
| SQL injection | High |
| Stored XSS that runs for other users, including reviewers and moderators | High |
| Reading private data: email addresses, private or pending content, unreleased plugin code, security reports | High |
| Writing to a plugin, theme, or translation the attacker does not own | High |
| SSRF that reads responses from internal hosts | High |
| Reflected XSS, or CSRF that makes another user change real state | Medium |
| Anything else with a real security impact | Medium |
| Open redirect that leaks a token or credential | Medium |
| Blind SSRF, or other low-impact issues | Low |
| No real security impact | Informative |

A finding that needs a role only an administrator can grant (reviewer, moderator, translation editor, locale editor,
Contributor or higher) is out of scope, unless it leads to a high-severity escalation with a real security impact,
such as a clear path to administrator or to code execution on the server. Rate that by what the attacker reaches.

One authenticated role doing what another authenticated role normally can is not a finding on its own, for example a
plugin's support rep doing what its committers can, or one forum role doing another's moderation. It counts only when
it leads to a high-impact escalation, such as writing to code that ships to sites.

Rules by class:

- **Validity comes before severity.** A finding counts only if it names the actor, how that actor gets their access on
  that site, what they can already do through the normal interface, and what they gain. No gain means no finding.
- **Deserialization:** name a real gadget in installed code. Object injection with no available gadget chain is
  Informative.
- **SSRF:** needs proof that an internal host returned different data. Requests to attacker-controlled public URLs,
  or only to cloud metadata, do not count.
- **CSRF:** needs realistic cross-origin exploitation under default `SameSite` cookies, and a state change the attacker
  benefits from.
- **Sanitizer or encoding bypasses** with no demonstrated sink are Informative.
- **Open redirects** are Informative unless they leak a token or credential, or chain into a proven exploit.

## Anything to leave alone

- Anything on wordpress.tv, browsehappy.com or wp-themes.com.
- Anything that needs an appointed role, or an account where only anonymous attackers count, except as described above.
- Findings that are not severe (Low or Informative) on a wordpress.org subdomain, such as translate, make, developer,
  learn or a locale site, or on any other low-impact site. The directories, forums and Photo Directory are on
  wordpress.org itself.
- Bypassing plugin or theme review, plugin release confirmation, or other directory checks: that is a review bypass,
  not a vulnerability.
- Bypassing a moderation control where the attacker gains nothing, such as comment or forum moderation. Content that
  was public before it was marked as spam staying reachable afterwards.
- Username, user ID and author enumeration, by any mechanism.
- Intentionally public data: plugin and theme metadata, public profiles, statistics.
- Broken links to external resources in documentation or a profile, without a significant security impact.
- Exposing an unapproved comment or trackback through social engineering, session theft, brute force, or similar.
- Version disclosure, path disclosure from errors, warnings or notices, missing security headers, and missing rate
  limiting.
- Brute force, denial of service, memory exhaustion, phishing, text injection and social engineering. Wikis, Trac and
  the forums exist for users to edit.
- Self-XSS, and script execution by a user who holds `unfiltered_html`.
- Mixed-content warnings for passive assets, and clickjacking with minimal security impact.
- Vulnerabilities in Composer or npm `devDependencies`, unless there is a practical way to exploit them remotely.
- A vulnerability in a plugin or theme hosted in the directories, rather than in the directories' own code.
- Theoretical issues with no demonstrated, significant security impact.
- Findings that need `WP_DEBUG` or `display_errors` on, a server misconfiguration, or an already-compromised host.
