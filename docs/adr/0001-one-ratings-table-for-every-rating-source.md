# One `ratings` table for every Rating source

Every score Reelio shows lives in a single `ratings` table keyed by `(source, source_id)`, where `source_id` is the identifier that source uses for the title (an IMDb `tt…` id, a TMDB id, or later Reelio's own Movie id for internal ratings). We chose this over a dedicated table per source and over keeping TMDB's score on `movies`, so that adding a Rating source never needs a table, importer, job or display code of its own, and so that every surface reads every source through one query path.

## Considered Options

- **A table per source** (the original `imdb_ratings` plus `vote_average`/`vote_count` on `movies`): simple per source, but each new source repeats the table, staging, swap, job, freshness check and read adapter.
- **TMDB's score stays on `movies`**: it arrives with the Movie fetch for free, but a second read path, and a Movie row is then needed to show a TMDB score at all.

## Consequences

- **What a new source costs** depends on its shape. A full-dataset source keyed by an id Reelio already stores (an IMDb or TMDB id) needs a feed (URL and parser), an enum case with its label, colour and id mapping, and a config entry: no migration. A per-title source such as Rotten Tomatoes (licensed, no public dump) needs its own incremental writer, and a migration if its title id isn't one Reelio already stores.
- **Full-dataset sources** (IMDb's daily dump) replace all of their rows at once, in one transaction: the file streams into a temporary table that lives only for that transaction, is validated, then replaces that source's rows. Postgres readers keep the old rows until the commit, so a bad file never reaches visitors, and the single transaction keeps every statement on one connection behind a transaction-mode pooler. There is no permanent staging table. The daily rewrite leaves dead rows for autovacuum, accepted in exchange for one table.
- **Incremental sources** write single rows: TMDB's score is recorded whenever the Catalog fetches a title, whether or not a Movie row exists. Search and Discover therefore record TMDB scores for the titles they show.
- **TMDB's 6-month cap now covers `ratings` rows with `source = tmdb`**, independently of Movie rows: the cap guard, the purge and `catalog:purge` must clear them too.
- A zero-vote or missing score is never stored, so "absent" means "no row".
- Switching a source off in config hides it everywhere and stops its writes and sync; its rows stay until the next decision.
