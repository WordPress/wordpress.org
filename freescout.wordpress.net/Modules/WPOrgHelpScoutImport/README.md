# WP.org HelpScout Import

Copies a HelpScout mailbox's conversations into a FreeScout mailbox, so a team's history moves with its email. Administrators run it under **Manage » HelpScout Import**; nobody needs shell access to the server.

## How it works

- **Importing:** pick a HelpScout mailbox and a FreeScout mailbox, and choose **Import**. The import runs on FreeScout's queue, 25 conversations at a time, and the page shows its progress, refreshing itself while it runs. It can be paused and resumed; it goes on where it stopped. **Cancel** stops it for good, so the mailbox can take another import.
- **Importing again:** the first import of a mailbox copies everything. Importing it again copies only what HelpScout changed since the last finished import started: new conversations, replies, notes, status, and assignee. It looks 15 minutes further back, in case the clocks disagree. Import once while HelpScout is still live, and once more after the mailbox's email has moved. **Everything again, not only what changed** imports the whole mailbox again; what's imported already isn't duplicated.
- **What comes across:**
  - conversations with their subject, status, assignee (a person or a team), who closed them, CC and BCC, and their original dates;
  - senders' HelpScout profiles, read once per sender: their other emails, organization, job title, phones, websites, social profiles, address, background (as notes), and photo from a social profile, which is copied. Gravatars are left to WPOrgSidebar, which keeps senders' WordPress.org avatars up to date, and HelpScout's generated placeholders are left out. HelpScout's location goes in the address when there's none. What FreeScout has no field for, like chat handles, age, gender, and HelpScout's customer properties that have a value, is kept in the sender's meta;
  - every email, reply, and note, with its own sender or agent, its recipients, and its attachments, except those HelpScout found a virus in;
  - replies HelpScout hid from what the sender is sent, or that bounced, as notes;
  - images pasted into emails, which HelpScout keeps on its own image host: they're copied, so they don't go with the account, up to 100 MB per conversation. Images linked from elsewhere keep their links, and HelpScout's read-tracking image is removed;
  - each email's Message-ID, so a sender who replies to an old HelpScout email lands in its conversation. HelpScout keeps them for 2 years, so older emails have none. An email FreeScout has already, like one sent to two mailboxes, keeps its Message-ID where it is; if it's in the same conversation, it isn't added again;
  - HelpScout's conversation number, which people quote, unless a FreeScout conversation already has it;
  - HelpScout's tags, with the Tags module on: tags FreeScout doesn't have yet are created in the module's color nearest HelpScout's;
  - HelpScout's custom fields, with the Custom Fields module on: each mailbox gets all of HelpScout's fields, in HelpScout's order, with their types, dropdown options, and whether they're required, or uses one it has by the same name, and conversations get their values. Dropdown options HelpScout added later are added too. Numbers and dates that aren't ones are left out, and commas are taken out of values for fields FreeScout splits at commas;
  - importing again brings tags and values up to date with HelpScout's, but what agents changed stays: HelpScout's new tags are added, and tags HelpScout removed are taken away if the import added them; values are set, changed, or cleared where they're still what the last import gave them. Tags, fields, and dropdown options deleted in FreeScout aren't added again. Without the modules, tags and custom field values are kept in the importer's own table; once they're on, **Everything again** gives every imported conversation its tags and values;
  - the mailbox's saved replies for email, once its conversations are done, with the Saved Replies module on. Their placeholders, like `{%customer.firstName,fallback=there%}`, work in FreeScout as they are, and their images are copied like those in emails. Importing again brings them up to date with HelpScout's, unless they were changed or deleted in FreeScout, and copies only images that changed; importing the mailbox into another FreeScout mailbox gives that one the saved replies too; a saved reply with the name of one FreeScout has already is left out. Those that fail are listed with the run's error, and tried again by importing the mailbox again.
- **What doesn't:**
  - spam, drafts, and conversations HelpScout deleted. A conversation marked spam in HelpScout after it was imported becomes spam in FreeScout too, unless agents worked on it there;
  - HelpScout's line items ("assigned to", "closed by", workflows that ran);
  - workflows: HelpScout's API gives only their names, not their conditions or actions. Set them up again in FreeScout's Workflows module;
  - saved replies' categories, and those only for chat: HelpScout's API doesn't give the categories, so saved replies come across as one list, in HelpScout's order;
  - phone calls and forwards become notes, since FreeScout has no thread type for them. Senders without an email, like callers, are imported without one.
- **Conversations agents worked on in FreeScout:** once a conversation has a reply, note, or change made in FreeScout, importing it again only adds HelpScout's new threads. Its status and assignee stay FreeScout's.
- **Conversations HelpScout moved to another mailbox** move to the FreeScout mailbox that mailbox is imported into, with their values in its custom fields, unless agents worked on them in FreeScout: then they stay where they are, and only get HelpScout's new threads. Retrying a failed conversation that's in another HelpScout mailbox now leaves it to that mailbox's import.
- **Users:** every HelpScout user's replies, notes, and assignments are credited to a FreeScout user:
  - the one chosen on **Manage » HelpScout Import » Users**, or else the one with the same email;
  - or else a new one. Before an import creates users for a mailbox's HelpScout users, the import page lists them, and starts once that's confirmed. They can log in, and get access to the mailbox imported into; so do HelpScout users with a FreeScout user already.
  - HelpScout no longer lists users deleted from it, but their threads still name them. When an import meets them, they get a disabled user. Disable the others the same way once a mailbox has moved, if they won't use FreeScout.
  - New users have no password, and their email is HelpScout's until they're connected to their WordPress.org account. Connecting them takes their name, email, and avatar from WordPress.org, as for every user, and lets them log in. Users who won't log in, like former agents, don't need a WordPress.org account.
  - **Connecting in bulk:** on the Users page, **Download CSV** lists HelpScout's users with their FreeScout users. Fill in its `wporg_username` column, upload or paste it, and **Check** it: that shows, for each row, the WordPress.org account's name and email next to the HelpScout user's, and what connecting would do, without doing it. **Connect** then connects each one's FreeScout user to the account, creating it if they have none yet (before their mailbox is imported, too). Someone whose account is connected to a FreeScout user already is credited to that user instead, so they don't get a second one.
    - Connecting lets the account's owner log in as that user. So only users an import created, or whose email is the account's, are connected in bulk, and administrators only by their email; anyone else is connected on their profile.
    - A CSV connects each HelpScout user once, each account once, and each FreeScout user to one account; the check flags rows that would do more.
    - A row whose account has neither the HelpScout user's name nor their email is highlighted, and only connected if it's ticked. One at a time, users can be connected on their profile, or with `php artisan wporgsso:connect`.
  - Someone with a FreeScout user under another email gets a second one unless they're chosen on the Users page first: FreeScout can't merge users. Choosing someone after an import credits what's imported for them to the user chosen.
  - What HelpScout did itself, without a user, is credited to "HelpScout Import", a disabled robot user.
- **Teams:** conversations assigned to a HelpScout team are assigned to the FreeScout team chosen on the Users page, or else to the one with the same name. FreeScout's teams come from its Teams module: create them there first. Without either, they're imported unassigned, and choosing a team later assigns them.
- **Nothing reacts to it:** conversations are marked as imported, and written without the events new email fires. Nothing is sent, nobody is notified, no workflow runs, and `WPOrgAkismet` and `WPOrgWebhooks` leave them alone.
- **HelpScout's rate limit:** the whole HelpScout account shares one limit, and wppluginsteam.org and the reviewers' tools use it too. The import leaves 100 requests a minute to them, and waits for the next minute when only those are left. A conversation too big to read in one minute's share is read again on its next try, waiting for the limit as it goes, but only while no outgoing email is waiting in FreeScout's queue.
- **Failures:**
  - a conversation that can't be imported is counted, logged, and listed with its error under the run; the rest of the page goes on. Once the run is done, **Retry failed** imports them again;
  - a job that dies, like by running out of memory or time, is tried again after 5 minutes. A conversation whose import keeps stopping the job, or HelpScout keeps failing for, is counted as failed after 5 tries, so the run goes on;
  - when HelpScout is down, the page is tried again every 5 minutes; after an hour of that, the run stops, to be resumed;
  - when HelpScout refuses the app's credentials, the import stops: fix them, then **Resume**;
  - a run without progress for 15 minutes, like when the queue worker was killed, is marked stalled: **Resume** it.

## Setup

- It needs a HelpScout app: in HelpScout, **Your Profile » My Apps » Create My App**, with any redirect URL. Set its ID and secret in FreeScout's `.env` as `WPORG_HELPSCOUT_APP_ID` and `WPORG_HELPSCOUT_APP_SECRET`. The app can read everything its HelpScout user can see, so delete it after the last import.
- FreeScout's queue worker must be running.
- Conversation numbers: under Manage » Settings » General, set **Conversation Number** to **Custom…** (`APP_CUSTOM_NUMBER=true`), or FreeScout shows and searches its internal IDs instead. Before the first import, set the **Next Conversation #** that appears well above HelpScout's numbers, like 2,000,000: HelpScout keeps numbering the mailboxes that haven't moved yet, and conversations whose number FreeScout has given away already keep a number of FreeScout's. The import page warns until both are set, and doesn't start an import before the Next Conversation # is. Imports leave that setting alone.
- Teams: install the Teams module, and create the teams, before importing mailboxes with conversations assigned to teams.
- Saved replies, tags, and custom fields: switch on the Saved Replies, Tags, and Custom Fields modules before importing.
- After the last mailbox has moved, switch the module off, and remove its code in a later deploy. Imported conversations keep HelpScout's numbers. Its tables map HelpScout's conversation IDs to FreeScout's: convert anything that still links to HelpScout before removing it.

To try it locally, start the environment with `WPORG_HELPSCOUT_APP_ID` and `WPORG_HELPSCOUT_APP_SECRET` set. The import only reads from HelpScout.
