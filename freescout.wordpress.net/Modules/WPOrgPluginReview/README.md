# WP.org Plugin Review

Shows the latest plugin review of a conversation in its sidebar, for the plugins team, read from the review emails in FreeScout's database.

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

## WordPress.org

The plugin comes from `api.wordpress.org/dotorg/freescout/plugin-review.php`, which only answers for the plugins team's mailbox (its address starts with `plugins`). Requests are signed with `WPORG_API_URL` and `WPORG_API_SECRET`, like the sidebar's panels, through WPOrgSidebar's client. Without them, the panel shows what the review emails say.

## Issue names and replies

Issues are listed by the short names the team uses, in `Services/IssueNames.php`: the first entry whose part of the title an issue's title contains names it. Priority 2 shows an issue in bold, 1 as it is, and 0 dimmed; issues without a name show their title. Flags with a reply in `Services/FlagReplies.php` (`UPD`, `TRM`, and `OWN`) get a button that copies it, as HTML and as text, for pasting into a reply. When the team's emails change, change them there.

## The index

The module keeps the table `wporgpluginreview_reviews`: which replies are review emails or follow-ups, and of what type, from their Review ID lines. Replies are indexed as they're published, changed (including when merging moves them to another conversation), and deleted, and a conversation's again when it moves to another mailbox, or when the HelpScout import writes it, as neither changes its replies. Conversations deleted for good leave the index. The sidebar reads the latest review from the index; it reads a conversation in full, and indexes it again, when the latest rows' replies changed without their events, and reads the newer replies that may hold a Review ID but aren't indexed.

Switching the module on for the first time runs its migration, which queues indexing the review emails FreeScout already has. After it was switched off for a while, index them again with `php artisan wporgpluginreview:index` (`--now` to do it in the command rather than on the queue).
