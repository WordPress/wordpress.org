# WP.org Sidebar

Shows what WordPress.org knows about the person a conversation is about, next to the conversation. That's usually the sender; for bounces and Slack notifications it's the account they concern.

## What it shows

- **WordPress.org:** the sender's profile, links to their account and forum profile, pending signups, and their Slack accounts, with deactivated ones marked. It shows in every mailbox.
- **Forum Notes:** notes moderators left on the sender's forum profile.
- **Plugins & Themes:** plugins and themes mentioned in the email, and those the sender owns, with their status in the directory.
- **Privacy Requests:** the sender's personal data exports and erasures.

The other three only show in the mailboxes checked in their section under Manage » Settings (**Forum Notes Panel**, **Plugins & Themes Panel**, **Privacy Requests Panel**), like HelpScout's apps. They start off everywhere. A panel that's off isn't sent the mailbox's conversations.

Panels load after the conversation, so a slow WordPress.org never holds it up. A panel with nothing to show is left out, and each one collapses from its heading, like FreeScout's own.

## Setup

Needs `WPORG_API_SECRET`; see [configuration](../../README.md#deployment). The panels come from [`api.wordpress.org/dotorg/freescout/`](../../../api.wordpress.org/public_html/dotorg/freescout), which is sent the conversation (sender, subject, and recent messages; internal notes only for Plugins & Themes, and text attachments only to find who a bounce is about) and works out whose WordPress.org account it's about.
