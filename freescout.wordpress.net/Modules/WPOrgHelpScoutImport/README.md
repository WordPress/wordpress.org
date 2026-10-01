# WP.org HelpScout Import

Copies a HelpScout mailbox's conversations into a FreeScout mailbox, so a team's history moves with its email. Administrators run it under **Manage » HelpScout Import**; nobody needs shell access to the server.

## How it works

- **Importing:** pick a HelpScout mailbox and a FreeScout mailbox, and choose **Import**. The import runs on FreeScout's queue, 25 conversations at a time, and the page shows its progress. It can be paused and resumed; it goes on where it stopped.
- **Importing again:** the first import of a mailbox copies everything. Importing it again copies only what HelpScout changed since the last finished import started: new conversations, replies, notes, status, and assignee. It looks 15 minutes further back, in case the clocks disagree. Import once while HelpScout is still live, and once more after the mailbox's email has moved.
- **What comes across:**
  - conversations with their subject, status, assignee, who closed them, CC and BCC, and their original dates;
  - every email, reply, and note, with its own sender or agent, its recipients, and its attachments;
  - images pasted into emails, which HelpScout keeps on its own image host: they're copied, so they don't go with the account. Images linked from elsewhere keep their links, and HelpScout's read-tracking image is removed;
  - each email's Message-ID, so a sender who replies to an old HelpScout email lands in its conversation;
  - HelpScout's conversation number, tags, and custom fields, kept in the module's own table until the Tags and Custom Fields modules can take them.
- **What doesn't:**
  - spam and drafts;
  - HelpScout's line items ("assigned to", "closed by", workflows that ran);
  - phone calls and forwards become notes, since FreeScout has no thread type for them.
- **Agents:** HelpScout users are matched to FreeScout users by email. **Check agents** lists the ones without a FreeScout user; add them before importing to credit them. Their replies and notes are otherwise credited to "HelpScout Import", a disabled robot user, and the thread keeps their name.
- **Nothing reacts to it:** conversations are marked as imported, and written without the events new email fires. Nothing is sent, nobody is notified, no workflow runs, and `WPOrgAkismet` and `WPOrgWebhooks` leave them alone.
- **Importing again is safe:** only threads that weren't imported yet are added. A conversation deleted in FreeScout since stays deleted.
- **HelpScout's rate limit:** the whole HelpScout account shares one limit, and wppluginsteam.org and the reviewers' tools use it too. The import leaves 100 requests a minute to them, and waits for the next minute when only those are left.
- **Failures:**
  - a conversation that can't be imported is counted, logged, and shown with the run; the rest of the page goes on;
  - when HelpScout is down, the page is tried again every 5 minutes;
  - when HelpScout refuses the app's credentials, the import stops: fix them, then **Resume**.

## Setup

- It needs a HelpScout app: in HelpScout, **Your Profile » My Apps » Create My App**, with any redirect URL. Set its ID and secret in FreeScout's `.env` as `WPORG_HELPSCOUT_APP_ID` and `WPORG_HELPSCOUT_APP_SECRET`. The app can read everything its HelpScout user can see, so delete it after the last import.
- FreeScout's queue worker must be running.
- After the last mailbox has moved, switch the module off, and remove its code in a later deploy. Its tables stay, with HelpScout's IDs and numbers for every imported conversation.

To try it locally, start the environment with `WPORG_HELPSCOUT_APP_ID` and `WPORG_HELPSCOUT_APP_SECRET` set. The import only reads from HelpScout.
