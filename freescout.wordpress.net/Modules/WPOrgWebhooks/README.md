# WP.org Webhooks

Sends conversation events to WordPress.org, which records them as contributor stats for the agents who handled them.

## What it sends

An event, the conversation, its mailbox, and the agent, when:

- a conversation comes in, or an agent starts one;
- the sender or an agent replies (an undone reply isn't counted);
- a conversation is assigned, changes status, or moves to another mailbox;
- conversations are merged, or one is deleted.

Nothing from the emails themselves is sent. Events go out from FreeScout's queue, and are retried if WordPress.org can't be reached.

## Setup

Needs `WPORG_API_SECRET`, and FreeScout's queue worker running; see [configuration](../../README.md#deployment). Events go to [`api.wordpress.org/dotorg/freescout/webhook.php`](../../../api.wordpress.org/public_html/dotorg/freescout/webhook.php).

Local environments pointed at the real api.wordpress.org switch this module off, so test conversations don't count.
