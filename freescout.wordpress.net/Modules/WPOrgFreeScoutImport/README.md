# WP.org FreeScout Import

Copies mailboxes' conversations from other FreeScouts, like WordCamp US's at scout.wc-us.org, into mailboxes here, so a team's history moves with its email. Administrators run it under **Manage » FreeScout Import**; nobody needs shell access to this server.

## How it works

- **Sources:** each FreeScout imported from is a source, with its address, its API & Webhooks module's global API key, and a name for the pages. Add as many as there are FreeScouts to move. What's imported is matched up per source: two FreeScouts number their conversations, threads, and users alike, so the same numbers from another source are other conversations, threads, and users. A source can be deleted only while nothing was imported from it; after that, forget its API key instead.
- **Importing:** pick a source's mailbox and a mailbox here, and choose **Import**. The import reads the source's API, 25 conversations at a time, on FreeScout's queue, and the page shows its progress, refreshing itself while it runs. It can be paused and resumed; it goes on where it stopped. **Cancel** stops it for good. One import runs at a time.
- **Importing again:** the first import of a mailbox copies everything. Importing it again copies only what changed there since the last finished import started, looking 15 minutes further back, in case the clocks disagree. Import once while the source is still live, and once more after the mailbox's email has moved. **Everything again, not only what changed** imports the whole mailbox again; what's imported already isn't duplicated.
- **Conversation numbers:** every conversation gets a new number here, like new email does, so none clashes with the numbers here, or with those HelpScout's imports still bring. The import never takes the **Next Conversation #** set for live email. The number it had there is kept: **Find a conversation** on the import page opens it, by source and number.
- **What comes across:**
  - conversations with their subject, status, assignee, who closed them, CC and BCC, and their dates;
  - every email, reply, and note, with its own sender or agent, its recipients, and its attachments;
  - status changes and assignments, as line items, so the Reports module counts closes;
  - images pasted into emails, which the source keeps: they're copied, up to 100 MB per conversation. Images linked from elsewhere keep their links. Only files on the source's own host are downloaded;
  - each email's Message-ID, from the uploaded file (see Setup), so a sender who replies to an old email lands in its conversation. Senders' emails keep their own; replies the source sent keep the one it made up for them, which this FreeScout finds like any other. An email this FreeScout has already keeps its Message-ID where it is;
  - tags, with the Tags module on, in their color there. Importing again adds tags added there since, and takes away those removed there if the import added them; what agents changed here stays.
- **What doesn't:** spam, drafts, and deleted conversations; other line items, like moves and merges; saved replies, workflows, custom folders, and mailbox settings, which the API doesn't give: set those up again by hand. Senders come across with their name and email only.
- **Conversations agents worked on here:** once a conversation has a reply, note, or change made here, importing it again only adds the new threads. Its status and assignee stay this FreeScout's. What automatic workflows note when they run doesn't count. A conversation moved to another mailbox there moves here too, unless agents worked on it.
- **Users:** every user's replies, notes, and assignments are credited to a user here: the one chosen on the source's **Users** page, under **Manage » FreeScout Import**, or else the one with the same email, or else a new, disabled one that keeps their name. Active users get access to the mailboxes an import credits them with something in.
  - **Connecting in bulk:** on a source's Users page, **Download CSV** lists its users with their users here. Fill in its `wporg_username` column for those who'll work here, upload or paste it, and **Check** it: that shows, for each row, the WordPress.org account's name and email next to the user's, and what connecting would do, without doing it. **Connect** then connects each one's user here to the account, creating it if they have none yet (before their mailboxes are imported, too), or switching it on, with access to the mailboxes their imported conversations are in, if an import created it disabled. Someone whose account is connected to a user already is credited to that user instead, so they don't get a second one.
    - Connecting lets the account's owner log in as that user. So only users an import created, or whose email is the account's, are connected in bulk, and administrators only by their email; anyone else is connected on their profile.
    - A CSV connects each user once, each account once, and each user here to one account; the check flags rows that would do more.
    - A row whose account has neither the user's name nor their email there is highlighted, and only connected if its box is checked.
  - Users who won't work here stay disabled. Someone with a user here under another email gets a second one unless they're chosen on the Users page first: FreeScout can't merge users. Choosing someone after an import credits what's imported for them to the user chosen; with the Reports module on, it computes those conversations' metrics again. What nobody in particular did is credited to "FreeScout Import", a disabled robot user.
- **Nothing reacts to it, except scheduled workflows:** conversations are marked as imported, and written without the events new email fires. Nothing is sent, nobody is notified, workflows aren't triggered, and `WPOrgAkismet` leaves them alone. The Workflows module still runs automatic workflows every hour over the conversations they apply to, imported ones too, unless they have the condition **Imported** is **No**.
- **Failures:**
  - a conversation that can't be imported is counted, logged, and listed with its error under the run; the rest of the page goes on. Once the run is done, **Retry failed** imports them again;
  - a job that dies, like by running out of memory or time, is tried again after 5 minutes. A conversation whose import keeps stopping the job is counted as failed after 5 tries;
  - when the source is down, the page is tried again every 5 minutes; after an hour of that, the run stops, to be resumed;
  - when it refuses the API key, the import stops: fix it, then **Resume**;
  - a run without progress for 15 minutes, like when the queue worker was killed, is marked stalled: **Resume** it.

## Setup

- On the FreeScout to import from, under **Manage » API & Webhooks**, copy its global API key. On the import page, **Add a source** with its https address, like `https://scout.wc-us.org`, and the key. The key is kept encrypted with the app key, and isn't shown again; leave it empty to keep it when editing the source, unless its address changes to another host, which forgets it. It reads everything there, so **Forget the API key** after the source's last import.
- FreeScout's queue worker must be running.
- Switch on the Tags module before importing, and give automatic workflows the condition **Imported** is **No** (see above).
- **Message-IDs:** the API doesn't give them, so copy `bin/export-message-ids.php` to the source's server, and run it there with the path of that FreeScout: `php export-message-ids.php /var/www/html/scout.wc-us.org | gzip > message-ids.csv.gz`. Upload the file in the source's **Message-IDs** column on the import page. Threads imported already get theirs on the queue; those imported later get theirs as they're imported. Before the last import after the email moves, run it and upload it again, for the emails since.
- Once no FreeScout is left to move, switch the module off, and remove its code in a later deploy. Its tables keep the old numbers, which **Find a conversation** needs.

To try it locally, add a source on the import page. The import only reads from its sources.
