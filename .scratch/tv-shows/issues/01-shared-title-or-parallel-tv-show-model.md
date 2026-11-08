# Shared title model or a parallel TV Show model

Type: grilling
Blocked by: none
Status: claimed

## Question

Should TV Shows and Movies share one catalog entity (a generalised "Title" with a kind), or should TV Shows be a parallel model with their own tables, with the shared parts (genres, Availability, ratings, user facts) factored where it pays? Weigh against the glossary, which avoids "Title" for Movie, the single ratings table ([ADR 0001](../../../docs/adr/0001-one-ratings-table-for-every-rating-source.md)), `user_movies`, the Catalog source interface, and the later ratings, likes and filters work. Offer an ADR if the answer is hard to reverse.
