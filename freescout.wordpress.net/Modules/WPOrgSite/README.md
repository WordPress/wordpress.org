# WP.org Site

Adapts FreeScout to how WordPress.org runs it. Small changes to FreeScout's own screens go here, rather than into a module each.

## What it changes

- **Modules page:** only installed modules are listed, without FreeScout's Modules Directory and Marketplace. They can't be updated or deleted there; that happens on the server. Switching them on and off still works.
- **Modules page layout:** the module cards are laid out in columns, as many as the window fits.
- **Settings kept in `.env`:** FreeScout saves some of Manage » Settings to `.env`, like the time zone, the language, conversation numbers, and the fetching interval, and so do modules for some of theirs. The server's `.env` is read-only, so saving one failed, and with it the rest of its section. They show read-only, with their value and a note to ask the systems team, and saving a section leaves them out, so the rest of it saves. It's in `Services/EnvSettings.php` and `Public/js/settings.js`.
- **Missing scripts and stylesheets:** a file in core's script or style bundle that isn't on the server is left out, and logged. Otherwise core's Minify throws, and core leaves the whole bundle out of the page, jQuery and all, so nothing that needs JavaScript works. It's in `Services/Assets.php`.
- **HelpScout's links:** [a HelpScout link with FreeScout's address opens the conversation it was imported as](#helpscouts-links).
- **Branding:** the WordPress mark in the top left and, on a blue tile, as the favicon. The login page keeps FreeScout's logo, so it doesn't pass for a WordPress.org login, redrawn as an SVG so it's sharp on high-resolution screens.
- **Saved replies:** [chosen from the keyboard](#saved-replies-from-the-keyboard), with `/` in the editor.
- **Discarding a reply or note:** an empty one, without text, images, or attachments, is discarded at once, without asking. Otherwise, core's "Discard this draft?" dialog opens next to the discard button, rather than at the top of the window, a long way from it; so it does for the drafts' Discard links. For an empty one, the dialog is answered without showing, so core discards it as it would. **Escape** discards it too, as its button does, from the editor or with nothing in particular focused, not from the form's other fields, like the recipients': in the dialog, Yes has the focus, so Enter confirms, and Escape again cancels, back to where the caret was. Escape that closes something first, like the Saved Replies menu, only closes that. It's in `Public/js/discard.js`.
- **Sidebar:** [collapses to its icons](#collapsing-the-sidebar), on desktops.
- **Pinned note:** agents who choose so in their profile see [a conversation's latest note at its top](#pinned-note).
- **Pinned header:** [a conversation's header can stay in view](#pinned-header) while the agent reads the emails below it.
- **Alternate conversation design:** agents who choose so in their profile get [conversations laid out otherwise](#alternate-conversation-design), oldest email first, with Reply and Note at the bottom.
- **Conversations:** [keyboard shortcuts](#keyboard-shortcuts); the status and assignee selectors pinned to the top of the window while their menu is open, further down long conversations where the toolbar has scrolled out of view, so they can be chosen there, like after pressing `s` or `a`; the subject at 18px and the threads' authors at 16px, rather than core's 23px and 18px, so more of the conversation fits; and clicking the number next to the subject copies it, without the `#`. Its tooltip says when it's copied; where the browser doesn't let the page write to the clipboard, it copies through a selected field instead. It's all in `Public/js/conversation.js`.

## HelpScout's links

HelpScout's links, `https://secure.helpscout.net/conversation/{ID}/{number}/`, are all over Slack, Trac, and docs. Imported conversations keep HelpScout's number, but not its ID. With FreeScout's address in front, with or without the number, such a link redirects to the conversation it was imported as.

- **The IDs** are in the `wporgsite_helpscout_conversations` table, so they outlive the importer. Its migration copies the importer's table, and later imports are added through the `eloquent.saved` event of the importer's model. It's in `Services/HelpScoutLinks.php`.
- **`/conversation/{ID}/{number}`** is this module's route, in `Http/Controllers/HelpScoutLinksController.php`; the number is ignored.
- **`/conversation/{ID}`** is core's route. Where core answers with a 404, `Http/Middleware/RedirectHelpScoutLinks.php` redirects instead, so FreeScout's own conversations are untouched.
- **Agents** are redirected once logged in, and only to a conversation they may see; otherwise, it's a 404.

## Keyboard shortcuts

In a conversation, while the agent isn't typing in a field or the editor, and no modal is open:

| Key | Does |
| --- | --- |
| `r` | Opens the reply form, and scrolls to it when it's out of view. |
| `n` | Opens the note form, the same way. |
| `t` | Opens the Tags module's tag field. |
| `w` | Opens the Workflows module's Run Workflow, in mailboxes with manual workflows. |
| `m` | Opens Merge. |
| `s` | Opens the status menu, or closes it. |
| `a`, `p`, `c` | With the status menu open: makes the conversation active, pending, or closed. |
| `a` | With the status menu closed: opens the assignee menu, or closes it. The arrow keys and Enter choose in it. |
| `!` | Marks the conversation as spam, with the status menu open or not. |
| `k` | The previous conversation in the folder (core's "Newer"). |
| `j` | The next conversation in the folder (core's "Older"). |

The buttons' tooltips show their key, like "Reply (r)"; menu items, which have no tooltip, like the statuses, Workflow, and Merge, show it next to their name. Shortcuts with Ctrl, Alt, or Cmd, and capitals typed with Shift, are left alone; Caps Lock doesn't matter.

The shortcuts click FreeScout's own buttons and links, and the modules', so they do what a click does: core's checks and hooks run, a shortcut whose button the agent doesn't have, or whose module is off, does nothing, and changing the status goes on to the next conversation if the agent's settings say so. Like a click, `r` or `n` with a form already open don't switch it to the other kind, which would leave its draft behind; they bring the open one back into view instead. Choosing the status the conversation already has only closes the menu.

## Saved replies from the keyboard

With the Saved Replies module, which adds the Saved Replies menu to the reply and note editors' toolbar, a reply can be picked without the mouse. It's all in `Public/js/saved-replies.js`.

- **`/`** at the start of a line, the text's or a new one's after Enter or Shift+Enter, opens the menu instead of typing a `/`. Anywhere else, like in "and/or", "asd/", or after a space, it's a `/`. The editor says "Type `/` for saved replies" while it's empty.
- **The search**, which the module adds once a mailbox has more than 20 saved replies, gets the focus whenever the menu opens, `/` or a click, and starts empty, rather than with the last search. Without it, `/` puts the focus on the first reply.
- **↓ and ↑** go from the search to the replies and back up to it. Typing on a reply types into the search, at its end, and Backspace deletes the search's last character, so the agent can go on refining it.
- **Enter** inserts the reply that has the focus, where the `/` was. In the search, it inserts the first reply found; with nothing searched, it does nothing. Without this, Enter in the search would send the reply form, which the search is in: core's form has a hidden submit button.
- **Escape** closes the menu, back to where the caret was. After a `/`, the next `/` is a `/`, so a line can start with one, like a path.

The keys in the menu don't reach the conversation's shortcuts, so typing `j` there doesn't go to another conversation.

## Collapsing the sidebar

On desktops, 992px wide and up, where the sidebar is next to the page, a round button on the line between them collapses it; it shows while the pointer is on the sidebar, or the keyboard on the button. Expanded, as it always was, is the default.

- **Collapsed**, the sidebar takes only the width of its icons, animating there. The folders and menu items show only their icons; the title, like the mailbox's name, shows its initial on a color of its own; and the mailbox's email address is hidden. The mailbox's settings and new conversation buttons go one above the other, and buttons without an icon, like New User, are hidden.
- **While the pointer or the keyboard is in it**, or one of its menus is open, it opens to its full width over the page, after a moment so passing over it doesn't, without moving the page. It closes again as they leave.
- **Expand sidebar**, the same button, gives it its space back.

The browser remembers it, in `localStorage` (`wporgsite.sidebar`), so it's per browser, not per agent. A script in the page's head applies it before the page shows, so a collapsed sidebar doesn't show expanded first; it's in `Public/js/sidebar.js` and `Public/css/site.css` otherwise. Below 992px, core's own menu takes its place, and nothing changes.

## Pinned note

Notes keep the context of a conversation, but core shows the newest threads first, so newer emails push them down, out of sight. An agent who checks **Latest note: Pin it to the top of the conversation** in their profile, off by default, sees a copy of a conversation's latest note above its threads, once an email came after it. Only status changes or drafts after it leave it near enough to the top, and nothing is pinned.

- **The copy** shows the note as its thread does, with its author, when, links to its attachments, and **Go to the note**, which goes to it.
- **The note itself** stays where it is, dimmed, labeled **Pinned to the top**, which goes back up to the copy. It's at full strength while the pointer or the keyboard is on it, or the copy's link went to it.

The choice is kept with core's options, as `wporgsite_pin_note_{user ID}`, and forgotten when the user is deleted. It's in `Services/PinnedNote.php`, through core's `conversation.before_threads` and `thread.info.prepend` hooks, with the views in `Resources/views/`. Print views are left as they are.

## Pinned header

A pushpin button at the end of a conversation's toolbar pins its header, the toolbar, the subject, and the reply and note forms, to the top of the window, so it stays in view while the agent scrolls through the emails below it. The same button unpins it. Unpinned, the default, the header scrolls away as in core. The browser remembers it, in `localStorage` (`wporgsite.header`), so it's per browser, not per agent, and the script in the page's head applies it before the page shows, as for the sidebar.

- **The editor**, pinned, keeps the header within 55% of the window, so a good part of the emails still shows below it: rather than growing with the reply, it scrolls within what's left of that.
- **Use the whole window for the editor**, a button next to Save Draft while the header is pinned, gives the editor all the window, for long replies; again, it gives it less.
- **The status and assignee selectors** are in the header, so they stay in view with it rather than pinning themselves.
- **Opening a form** doesn't scroll the page, as core does to bring it into view: pinned, it's in view already, and it would only move the emails.

Core's `#app` has `overflow: hidden`, which the header would stick to, and which never scrolls; pinned, it's `overflow: clip`, which clips the same. It's in `Public/js/header.js` and `Public/css/site.css`.

## Alternate conversation design

An agent who checks **Conversation design: Alternate conversation design** in their profile, off by default, gets conversations laid out otherwise:

- **A pre-header**, always in view at the top: the subject on one line, the tags, and the actions, Reply and Note aside, whose menus, like Tag's and More Actions', open to the left, as they're at its end.
- **The subject block** below it, as in core, scrolling away with the threads; its tags are in the pre-header.
- **At the top of the sidebar**, which stays in view and scrolls on its own: the number, which copies itself when clicked, the previous and next conversations, and the assignee and status selectors, which `a` and `s` open there.
- **The threads oldest first**, opening at the newest, the end of the conversation; unless the address points at a thread.
- **Reply and Note buttons** at the bottom, floating over the threads as they scroll, without a background of their own, which `r` and `n` click too. The form they open takes their place there, as one floating box the conversation's width, with the editor at its top, and, for replies, the recipients, and Cc/Bcc as a button at the start of the editor's toolbar, whose fields open in the box, above the editor; notes have neither. The space under the editor for its errors, like an empty reply's, is only there while there are any. The box grows with the text up to 55% of the conversation's height under the pre-header, after which it scrolls; the button next to Save Draft gives it all of it. Closing the form brings the buttons back. At the end of the conversation, the space they take is under the threads, and the conversation keeps the newest threads above the form as it grows. The form's menus open up, into the threads.
- **Scrolling:** the page doesn't scroll itself; the conversation does, with its scrollbar by its side, and each sidebar on its own, the left one without a scrollbar. The navigation slides away while the conversation is scrolled from its top, and its WordPress mark stays where it was, at the top of the left sidebar.
- **The pinned note**, for agents who pin it too, is at the bottom, by the newest threads, as the threads go the other way; the note itself is labeled **Pinned to the bottom**.

The page's footer is hidden. Below 1100px, where core stacks the customer sidebar, it goes above the conversation, taking up to 35% of the height. The header can't be pinned, as the pre-header is.

The body gets the `wporgsite-alt-conversation` class, through core's `body.class` hook. `Public/js/alt-conversation.js` then moves core's own elements, so their handlers go with them and core's code still finds them, as it runs at the end of the page, before core and the other scripts set it up, and gives the page the `wporgsite-alt-layout` class that `Public/css/site.css` lays it out by. If anything it moves isn't there, it leaves the page as core lays it out. The choice is kept as `wporgsite_alt_conversation_{user ID}`, in `Services/AltConversation.php`.

## Setup

Nothing to configure.
