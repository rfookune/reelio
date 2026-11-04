# Catalog and Rating source boundary

Type: grilling
Status: open
Blocked by: 02, 03, 09
Map: [Reelio MVP](../map.md)

## Question

What is the shape of the module(s) that isolate Reelio from TMDB and OMDb?

- Interface and data objects (the PRD's `MovieProvider` is illustrative only).
- One abstraction or separate Catalog source and Rating source seams.
- Timeouts, bounded retries, and how source failures surface as explicit states to pages.
- Where caching sits and how cache keys incorporate country and language.
- How the boundary is faked in tests.

Use the `codebase-design` skill.
