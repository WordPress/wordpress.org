# WP.org Search

Speeds up FreeScout's conversation search, which times out on the history imported from HelpScout.

## Why core's search is slow

Core compares the query with every message using `LIKE '%…%'`, combined with `OR` across conversations, senders, and threads, so the database reads every thread in every mailbox the agent can see. The paginator then runs the same query again to count the results, sending a row back for every match.

## How it works

- **Always a date:** a search with a query and no other filters always gets core's `After` filter, so the database only reads the threads of conversations started since then. It's a year ago unless the agent picks a date. The filter shows on the search page, with a note. Removing or clearing it brings the default back, since searching everything is what times out. Any other filter searches without a date.
- **One query:** the results and their total come from one query, with `COUNT(*) OVER()`, instead of running the search twice. That needs MariaDB 10.2 or MySQL 8.
- **Time limit:** on MariaDB, a search stops after 20 seconds (`max_statement_time`), well before the web server gives up. It comes back empty, with a note asking the agent to pick a later After date or add another filter.
- **Failures:** if anything goes wrong, core's own search runs, and the error is logged.
