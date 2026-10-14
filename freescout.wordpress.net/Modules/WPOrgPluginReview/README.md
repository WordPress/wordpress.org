# WP.org Plugin Review

Shows the latest plugin review of a conversation in its sidebar, for the plugins team, read from the review emails in FreeScout's database. Adds [My Reviews and My Cases](#my-reviews-and-my-cases) folders of the reviews and cases each member is working on.

## Who sees it

Only conversations in the plugins team's mailbox (its address starts with `plugins`) have reviews; replies in other mailboxes aren't indexed, and a conversation moved out of it loses its panel.

With the Teams module, the panel is only for members of the plugins team's teams: those whose names start with "Plugin", like Plugin Reviews and Plugin Security. Without it, everyone who can see the mailbox gets it.

## What it shows

A conversation gets the **Plugin Review** panel once the team has sent it a review email: a reply with a `Review ID:` line, which the team's review tools end every review email with. The emails that follow up on a review, which write `Review:` instead (for changes that weren't made, and for a ZIP in the wrong format), count for whether an update is owed, but the panel keeps showing the review they follow up on.

- **Details** from the latest Review ID: the slug, linked to the plugin's edit page on WordPress.org, the submitter's profile, and how many reviews there were and when.
- **Flags**: the review's own (like `TRM` for naming, and `OWN` for ownership), and `UPD` while the author owes an update: a review asked for one, none approved the plugin, and since the latest review email, WordPress.org hasn't confirmed an upload, or the team wrote to the author again after the latest confirmation. The browser tab's title starts with 🔴 for `UPD`, 🟠 for `TRM`, and 🟤 for `OWN`, in that order.
- **Names**, for `TRM`: the name the plugin was submitted with, and the name and slug the review suggests.
- **Owner**, for `OWN`: the domains of the plugin's Author URI and Plugin URI, marked "DNS verified" when they carry the `wordpressorg-{username}-verification` TXT record the ownership emails ask for. The username is the Review ID's, or the submitter's when it doesn't name one.
- **The plugin as it is now**, from WordPress.org by the Review ID's plugin ID, or by its slug for reviews that don't carry the ID: its status, who's assigned to it, a button that copies its ZIP's URL (with the review info the review tools read, while it's in review), and, once it's released, links to its page and its security scans. A naming review also shows its current name and slug, in green when it's the suggested name and in red when it's still the original; an ownership review shows the submitter, with their email address in green when it's at one of the plugin's domains.
- **Issues** the review lists, under their headings' 🔴 (or the older `##`); warnings, under 🟡, aren't listed. Clicking one scrolls to it in the email.

Quoted text is left out, so an author's reply that quotes a review isn't read as one. What WordPress.org and DNS say is loaded after the page, so neither holds it up.

The tab's title also shortens the plugin directory's subjects, like "R: My Plugin" for "[WordPress Plugin Directory] Review in Progress: My Plugin"; a link at the bottom of the panel switches back to the full title, which the browser remembers.

While an agent writes a reply, a warning shows above it when the reply has a Review ID for a username other than the latest review's and the plugin's submitter's, until they say it's the right one: it would send one author's review to another.

## My Reviews and My Cases

The plugins team's mailbox gets two folders, right after Unassigned and before Mine, set apart from the folders after them, for the same agents as the panel: **My Reviews**, of the open and pending conversations assigned to the Plugin Reviews team, and **My Cases**, of the Plugin Admin team's: questions, guideline reports, replies to rejections, sockpuppets, adoptions, and the like. Each has three sections:

- **Reviews (or Cases) I'm participating in**: ones the agent sent a reply to. Notes and drafts don't count.
- **Reviews (or Cases) I'm mentioned in**: ones with a note that mentions the agent, with the Mentions module's `@` mentions. Mentions of a team the agent is in don't count.
- **Reviews that need a reviewer**, or **Cases that need a volunteer**: ones with `PRTRN` in a note, and ones no member replied to yet: no member of the plugins team's teams, as they are now, so replies from other agents, workflows, and former members don't count. Without the Teams module, any agent's reply does.

Each conversation shows only in the first section it belongs to, the longest waiting first, with its tags and how many threads it has, as FreeScout counts them: emails, not notes. Every one shows, without pages. The reviewer or volunteer at the top switches to another member of the plugins team's teams, to see theirs, and My Cases can be filtered by a tag its conversations have. Agents who may only see the conversations assigned to them see only those here too.

Conversations someone is participating in can be selected, and changed together with FreeScout's bulk actions: status, assignee, and deleting, and the Tags and Workflows modules' tags and manual workflows.

Without the Teams module, both folders are of the mailbox's open and pending conversations, whoever they're assigned to. With it, but without a team of that name, they're empty.

The folders aren't stored: they're added to the mailbox's folders as they're listed. Their counts are how many active conversations the agent is participating in: the ones whose sender wrote last, like core's folders count. Every member's count comes from one query per folder, kept in FreeScout's cache for up to ten minutes, and forgotten sooner when an agent's reply is published or removed, a sender replies, or a conversation in the mailbox changes status, assignee, or mailbox, or is deleted, merged, or imported. Agents who may only see the conversations assigned to them have theirs counted on every page. A page takes one query for which sections every conversation belongs to, and a few to load the ones that do and their tags; with a thousand open conversations, that's a few tens of milliseconds.

Their definitions, like the team and what the sections are called, are in `Services/Worklist.php`.

### Opening conversations

Next to the participating section's heading, two buttons open its active conversations in new tabs: up to 25 at a time, or as many as the agent chooses under **Conversations to open** in their profile, up to 100. Neither opens a conversation whose sender has waited less than 12 hours, since its latest reply, as Waiting Since shows. Hovering or focusing a button marks the conversations it would open, numbered in order.

- **Open Oldest First**: the longest waiting first.
- **Open Progress Based**: the longest waiting first, after each has waited as long as the team's replies to it make it: the more replies, the worse the review went, and the longer the others go first. The team's emails count, its first one included when the team wrote it, and notes don't. 1 or 2 replies wait at least a day, 3 or 4 two days, 5 or 6 four, 7 or 8 five, 9 or 10 six, and more, eight. The `sockpuppet` and `sockpuppeting` tags add four days. A conversation that has waited that long is ahead of one that waited longer but has to wait more: they go by how long they've waited beyond it.

The waits are `OpenOrder::WAIT_DAYS` and `OpenOrder::TAG_WAIT_DAYS`, in `Services/OpenOrder.php`. Browsers open the first tab and block the rest unless the site may open pop-ups; the page says how many were blocked, so the agent can allow them once.

## WordPress.org

The plugin comes from `api.wordpress.org/dotorg/freescout/plugin-review.php`, which only answers for the plugins team's mailbox (its address starts with `plugins`). Requests are signed with `WPORG_API_URL` and `WPORG_API_SECRET`, like the sidebar's panels, through WPOrgSidebar's client. Without them, the panel shows what the review emails say.

## Issue names and replies

Issues are listed by the short names the team uses, in `Services/IssueNames.php`: the first entry whose part of the title an issue's title contains names it. Priority 2 shows an issue in bold, 1 as it is, and 0 dimmed; issues without a name show their title. Flags with a reply in `Services/FlagReplies.php` (`UPD`, `TRM`, and `OWN`) get a button that copies it, as HTML and as text, for pasting into a reply. When the team's emails change, change them there.

## The index

The module keeps the table `wporgpluginreview_reviews`: which replies are review emails or follow-ups, and of what type, from their Review ID lines. Replies are indexed as they're published, changed (including when merging moves them to another conversation), and deleted, and a conversation's again when it moves to another mailbox, as that doesn't change its replies. Conversations deleted for good leave the index. The sidebar reads the latest review from the index; it reads a conversation in full, and indexes it again, when the latest rows' replies changed without their events, and reads the newer replies that may hold a Review ID but aren't indexed.

Switching the module on for the first time runs its migration, which queues indexing the review emails FreeScout already has. After it was switched off for a while, or after a HelpScout import, index them again with `php artisan wporgpluginreview:index` (`--now` to do it in the command rather than on the queue).
