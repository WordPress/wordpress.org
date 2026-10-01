# WP.org Akismet

Checks new conversations from senders with [Akismet](https://akismet.com/), like the rest of WordPress.org, and teaches Akismet from what agents mark as spam.

## How it works

- **Checking:** when an email starts a new conversation, its sender, subject, and text go to Akismet, with the IP address it entered the mail system from (from its `Received` headers). This happens before the conversation is saved, so spam never sends an auto-reply or notifies anyone. Replies to existing conversations, imported conversations, and email without a public IP address aren't checked.
- **Marking:** by default, Akismet's verdict is only recorded on the conversation. With `WPORG_AKISMET_MARK_SPAM=true`, spam goes straight to the Spam folder.
- **Learning:** when an agent marks a checked conversation as spam that Akismet let through, or takes one out of spam that Akismet caught, Akismet is told, from FreeScout's queue.
- **Failures:** if Akismet can't be reached or gives no verdict, the email comes in as usual, and the error is logged.

## Rolling out

Start with the verdicts only recorded. After a week or two of real email, compare them with what agents marked:

```bash
php artisan wporgakismet:report --days=14
```

Once Akismet rarely disagrees with agents, set `WPORG_AKISMET_MARK_SPAM=true`.

## Setup

Needs `WPORG_AKISMET_KEY`, FreeScout's own Akismet API key, and FreeScout's queue worker running; see [configuration](../../README.md#deployment). Without a key, nothing is checked.

To try it locally, start the environment with a key in `WPORG_AKISMET_KEY`. Email from `akismet-guaranteed-spam@example.com` is always spam; local test email has no public IP address, so add a `Received` header with one.
