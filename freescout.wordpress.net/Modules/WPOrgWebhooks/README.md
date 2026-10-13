# WP.org Webhooks

Sends conversation events to WordPress.org, which records them as contributor stats for the agents who handled them, and keeps a copy of the conversations that the plugin directory reads.

## What it sends

An event, the conversation, its mailbox, and the agent with the WordPress.org account WPOrgSSO connected them to, when:

- a conversation comes in, or an agent starts one;
- the sender or an agent replies (an undone reply isn't counted);
- a conversation is assigned, changes status, or moves to another mailbox;
- conversations are merged, or one is moved to Deleted (a conversation merged into another counts as the merge), or restored from it.
- an agent adds a note, which WordPress.org doesn't credit to them, but reads for the plugins and themes it mentions.

Events of conversations `WPOrgHelpScoutImport` imported count for stats, but don't carry the conversation for WordPress.org's copy until WordPress.org is pointed at their mailbox (the `wporgwebhooks.copy` filter); see its README.

Not sent:

- imported threads as they're written, which the service they came from counted already (what agents do with them in FreeScout is sent);
- spam from senders (agents marking spam is sent).

Automations, like Workflows, have no WordPress.org account, so their events are sent without an agent.

Each event also carries the conversation as WordPress.org keeps it, read when the event is sent, so a late or retried one says what's current: its mailbox, state, number, subject, status, preview, and dates, and the sender. The event's own mailbox, which stats count, stays the one it happened in.

For the plugins and themes they mention, a reply also carries the threads from the previous reply up to it (itself, and the notes before it), a note itself, and a conversation new to the copy, restored, taken out of spam, or moved to another mailbox, its newest 50 threads; other events carry none, as the copy keeps what earlier ones mentioned. When WordPress.org has no copy of a conversation yet, it takes nothing from an event without all of its threads, and asks for them: the event goes again at once with them, and only that one counts. Threads go as text, with their links' addresses, up to 256 KB an event, cutting the oldest short; an event api.wordpress.org's web server still refuses for its size goes again without them.

A merge carries the ID of the conversation merged away, and every event of an imported conversation the HelpScout ID whose copy it replaces, as does a merge for the conversation merged away (from the `wporgwebhooks.helpscout_id` filter, which `WPOrgHelpScoutImport` answers, also for conversations deleted for good since). The copy names the HelpScout conversations it replaced, which keeps HelpScout's webhook from writing them back; later events don't replace them again. Whether an event carries the conversation at all is up to the `wporgwebhooks.copy` filter, which `WPOrgHelpScoutImport` answers for imported conversations. That copy is the `wporg_helpscout` table HelpScout's webhook filled, which the plugin directory lists a plugin's emails from, and finds review conversations in; deleted conversations, and spam, are taken out of it.

Events go out from FreeScout's queue, and are retried if WordPress.org can't be reached.

## Setup

Needs `WPORG_API_SECRET`, and FreeScout's queue worker running; see [configuration](../../README.md#deployment). By default, events go to [`api.wordpress.org/dotorg/freescout/webhook.php`](../../../api.wordpress.org/public_html/dotorg/freescout/webhook.php).

The local development environment's setup switches this module off when pointed at the real api.wordpress.org, so test conversations don't count.
