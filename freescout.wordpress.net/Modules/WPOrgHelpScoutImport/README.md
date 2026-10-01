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
  - HelpScout's conversation number, which people quote, unless a FreeScout conversation already has it;
  - HelpScout's tags and custom fields, kept in the module's own table until the Tags and Custom Fields modules can take them.
- **What doesn't:**
  - spam and drafts;
  - HelpScout's line items ("assigned to", "closed by", workflows that ran);
  - phone calls and forwards become notes, since FreeScout has no thread type for them.
- **FreeScout users are never created from HelpScout.** Each HelpScout user's replies and notes are credited to the FreeScout user chosen on **Manage » HelpScout Import » Agents**, or else to the one with the same email. A HelpScout user without either gets no FreeScout user: their replies and notes are credited to "HelpScout Import", a disabled robot user, and each thread keeps their name. Imported conversations keep the credit they got, so match people before importing.
- **Agents page:** one list of HelpScout's users for the whole account, since a choice applies to every mailbox; it can be narrowed to one mailbox's users. For each, it shows who they're credited to, suggests the only FreeScout user with the same name, and lets you search FreeScout's users. Everything on it is saved at once.
  - **New users from WordPress.org:** with WP.org SSO on, entering someone's WordPress.org username creates a FreeScout user from that account, connected to it, the same way Manage » Users » New User does through WP.org SSO. They're disabled, so former agents keep their credit without being able to log in, unless **Can log in** is checked. An account already connected to a user is that user.
  - **Teams:** HelpScout lists its teams among its users, but they never write anything. They're listed apart, and conversations assigned to a team are imported unassigned.
  - **Before an import,** if any of the mailbox's HelpScout users would be credited to "HelpScout Import", the import page says how many, and starts only once that's confirmed.
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
- Conversation numbers: under Manage » Settings » General, set **Conversation Number** to **Custom…** (`APP_CUSTOM_NUMBER=true`), or FreeScout shows and searches its internal IDs instead. Before the first live email, set the **Next Conversation #** that appears well above HelpScout's numbers, like 2,000,000: HelpScout keeps numbering the mailboxes that haven't moved yet. Imports leave that setting alone.
- After the last mailbox has moved, switch the module off, and remove its code in a later deploy. Imported conversations keep HelpScout's numbers. Its tables map HelpScout's conversation IDs to FreeScout's: convert anything that still links to HelpScout before removing it.

To try it locally, start the environment with `WPORG_HELPSCOUT_APP_ID` and `WPORG_HELPSCOUT_APP_SECRET` set. The import only reads from HelpScout.
