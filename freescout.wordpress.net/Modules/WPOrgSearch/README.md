# WP.org Search

Speeds up FreeScout's conversation search, which times out on the history imported from HelpScout.

## Why core's search is slow

Core compares the query with every message using `LIKE '%…%'`, combined with `OR` across conversations, senders, and threads, so the database reads every thread in every mailbox the agent can see. The paginator then runs the same query again to count the results, sending a row back for every match.

## How it works

- **One query:** the results and their total come from one query, with `COUNT(*) OVER()`, instead of running the search twice. That needs MariaDB 10.2 or MySQL 8.
- **Without the full-text index:** a search with a query and no filters only covers conversations started in the past year, through core's `After` filter, so the database only reads that year's threads. The filter shows on the search page, with a note. Clearing its date searches everything; so does any other filter.
- **With the full-text index:** messages are looked up in the index instead. The IDs of matching conversations come first, each from one source: messages through the index, and subjects, senders' addresses, names, and the conversation number from columns without message text. Core's query then only adds the filters, modules' too, and joins threads only for filters that need them. There's no default date.
- **Matching with the index:** words match from their beginning (`obenl` finds `Obenland`), every word is required, and `"quoted phrases"` match as phrases. Text splits into words at anything that isn't a letter, digit, or underscore: `jane@example.org` requires `jane`, `example`, and `org`. Words shorter than the database's `innodb_ft_min_token_size` (3 by default) can't be looked up: a query with no other words searches like it does without the index.
- **Failures:** if anything goes wrong, core's own search runs, and the error is logged.

## Adding the full-text index

The database administrators add it; it isn't a migration, since adding it locks `threads` against writes while the table is rebuilt (the first full-text index on a table rebuilds it). Pause fetching email and imports, or use `pt-online-schema-change`.

1. Optionally, before building it, set `innodb_ft_min_token_size` (needs a restart) and `innodb_ft_enable_stopword`: changing them later means rebuilding the index.
2. Add it, with the table prefix if there's one:

   ```sql
   ALTER TABLE threads ADD FULLTEXT threads_search_ft (body, `from`, `to`, cc, bcc);
   ```

3. The module notices within an hour, or right after `php artisan freescout:clear-cache`.

After large deletions, `OPTIMIZE TABLE threads` with `innodb_optimize_fulltext_only` on purges deleted rows from the index. `ALTER TABLE threads DROP INDEX threads_search_ft` goes back to searching without it.
