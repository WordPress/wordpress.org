# WP.org Akismet

Checks new conversations from senders with [Akismet](https://akismet.com/), like the rest of WordPress.org, and teaches Akismet from what agents mark as spam.

## How it works

- **Checking:** when an email starts a new conversation, its sender, subject, and text go to Akismet, with the IP address of the server that handed it to WordPress.org (from its `Received` headers, which senders can't fake past WordPress.org's own servers). This happens while the conversation is created, before auto-replies and notifications, so spam never sends an auto-reply or notifies anyone. Replies to existing conversations, imported conversations, and email without a public IP address aren't checked.
- **Marking:** spam goes straight to the mailbox's Spam folder, and Akismet's verdict is recorded on the conversation.
- **Learning:** when an agent marks a checked conversation as spam that Akismet let through, or takes one out of spam that Akismet caught, Akismet is told, from FreeScout's queue. Automations, like Workflows, don't count as agents.
- **Checking on it:** `php artisan wporgakismet:report --days=14` counts the conversations agents took out of spam, and the spam they marked that Akismet let through.
- **Failures:** if Akismet can't be reached or gives no verdict, the email comes in as usual, and the error is logged.

## Setup

Needs `WPORG_AKISMET_KEY`, FreeScout's own Akismet API key, and FreeScout's queue worker running; see [configuration](../../README.md#deployment). Without a key, nothing is checked.

To try it locally, start the environment with a key in `WPORG_AKISMET_KEY`. Email from `akismet-guaranteed-spam@example.com` is always spam; local test email has no public IP address, so add a `Received` header with one.
