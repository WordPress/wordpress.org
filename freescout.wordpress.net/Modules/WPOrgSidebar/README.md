# WP.org Sidebar

Shows what WordPress.org knows about a conversation's sender, next to the conversation.

## What it shows

- **WordPress.org:** the sender's profile, links to their account and forum profile, pending signups, and their Slack accounts.
- **Forum Notes:** notes moderators left on the sender's forum profile.
- **Plugins & Themes:** plugins and themes mentioned in the email, and those the sender owns, with their status in the directory.
- **Privacy Requests:** the sender's personal data exports and erasures.

Panels load after the conversation, so a slow WordPress.org never holds it up. A panel with nothing to show is left out, and each one collapses from its heading, like FreeScout's own.

## Setup

Needs `WPORG_API_SECRET`; see [configuration](../../README.md#deployment). The panels come from [`api.wordpress.org/dotorg/freescout/`](../../../api.wordpress.org/public_html/dotorg/freescout), which looks the sender up by their email address.
