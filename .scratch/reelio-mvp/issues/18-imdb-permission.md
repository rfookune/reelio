# Confirm IMDb permission for the non-commercial ratings dataset

Type: task
Status: open
Map: [Reelio MVP](../map.md)

## Question

Will IMDb confirm in writing that Reelio, a free, permanently non-commercial public website, may display IMDb ratings and vote counts from its non-commercial datasets (`title.ratings.tsv.gz`) on Movie pages, with the credit "Information courtesy of IMDb (https://www.imdb.com). Used with permission."?

This is HITL: the human emails IMDb's licensing department, using the contact on IMDb's ["Can I use IMDb data in my software?"](https://help.imdb.com/article/imdb/general-information/can-i-use-imdb-data-in-my-software/G5JTRESSHJBBHTGX) help page. The email should say plainly what Reelio does and doesn't do: shows one rating per Movie with the credit, doesn't offer bulk download or an API, and earns no revenue. The human then records IMDb's answer here.

A refusal is a **launch** blocker, not a spec blocker. The fallbacks are the paid IMDb API on AWS Data Exchange or the TMDB rating only. See [OMDb ratings: coverage, terms and limits](./03-omdb-ratings.md).
