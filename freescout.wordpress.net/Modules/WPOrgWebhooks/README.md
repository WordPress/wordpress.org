# WP.org Webhooks

Sends conversation events to WordPress.org, which records them as contributor stats for the agents who handled them.

## What it sends

An event, the conversation, its mailbox, and the agent with the WordPress.org account WPOrgSSO connected them to, when:

- a conversation comes in, or an agent starts one;
- the sender or an agent replies (an undone reply isn't counted);
- a conversation is assigned, changes status, or moves to another mailbox;
- conversations are merged, or one is moved to Deleted (a conversation merged into another counts as the merge).

Not sent:

- imported conversations, which the service they came from counted already (what agents do with them in FreeScout is sent);
- spam from senders (agents marking spam is sent).

Automations, like Workflows, have no WordPress.org account, so their events are sent without an agent.

Nothing from the emails themselves is sent. Events go out from FreeScout's queue, and are retried if WordPress.org can't be reached.

## Setup

Needs `WPORG_API_SECRET`, and FreeScout's queue worker running; see [configuration](../../README.md#deployment). By default, events go to [`api.wordpress.org/dotorg/freescout/webhook.php`](../../../api.wordpress.org/public_html/dotorg/freescout/webhook.php).

The local development environment's setup switches this module off when pointed at the real api.wordpress.org, so test conversations don't count.
