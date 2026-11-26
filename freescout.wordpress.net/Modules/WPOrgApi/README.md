# WP.org API

Answers requests from WordPress.org, which reads from FreeScout what only FreeScout has. The other modules talk to WordPress.org; this one is talked to.

## Endpoints

- **`POST /wporgapi/saved-reply`:** a mailbox's saved reply, by name: `{"mailbox_id": 3, "name": "Closed: Security Exploit"}` gets `{"name": …, "text": …}`, the reply's HTML, from the Saved Replies module. The plugin directory's scripts write their emails from them, so the plugins team keeps the texts up to date in FreeScout. It answers 404 when the mailbox has no such reply, and 503 while the Saved Replies module is off.

Requests are JSON, signed with an HMAC-SHA256 of the body in `X-WPOrg-Signature`, with the secret the modules share with WordPress.org. They name the endpoint, as `endpoint`, and when they were sent, as `sent_at`, and are refused after 5 minutes. They aren't in FreeScout's `web` middleware, so they need no session or CSRF token, and they're throttled to 60 a minute.

## Setup

Needs `WPORG_API_SECRET`, which must match `FREESCOUT_SECRET` on WordPress.org; see [configuration](../../README.md#deployment). Without it, every request is refused.
