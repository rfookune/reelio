# Confirm IMDb permission for the non-commercial ratings dataset

Type: task
Status: claimed
Map: [Reelio MVP](../map.md)

## Question

Will IMDb confirm in writing that Reelio, a free, permanently non-commercial public website, may display IMDb ratings and vote counts from its non-commercial datasets (`title.ratings.tsv.gz`) on Movie pages, with the credit "Information courtesy of IMDb (https://www.imdb.com). Used with permission."?

This is HITL: the human emails IMDb's licensing department, using the contact on IMDb's ["Can I use IMDb data in my software?"](https://help.imdb.com/article/imdb/general-information/can-i-use-imdb-data-in-my-software/G5JTRESSHJBBHTGX) help page. The email should say plainly what Reelio does and doesn't do: shows one rating per Movie with the credit, doesn't offer bulk download or an API, and earns no revenue. The human then records IMDb's answer here.

A refusal is a **launch** blocker, not a spec blocker. The fallbacks are the paid IMDb API on AWS Data Exchange or the TMDB rating only. See [OMDb ratings: coverage, terms and limits](./03-omdb-ratings.md).

## Comments

- Checked IMDb's ["Can I use IMDb data in my software?"](https://help.imdb.com/article/imdb/general-information/can-i-use-imdb-data-in-my-software/G5JTRESSHJBBHTGX) page on 2026-11-04. Three things the email must deal with:
    - **No contact address is given.** The page points only to an "IMDb Licensing information" link on developer.imdb.com. Find the licensing contact or form there; I didn't find an email address.
    - **The wording is "personal and non-commercial" use**, and the data may not be "altered/republished/resold/repurposed to create any kind of online/offline database of movie information". Reelio's design imports the whole `title.ratings.tsv.gz` into its own table every day ([Movie identity, URLs and local storage](./09-movie-identity-and-storage.md)) and shows ratings on a public site. A public site isn't "personal use", which is why the written permission is needed. The email must say plainly that the full file is loaded into a database and only one rating and vote count per Movie is displayed.
    - **Attribution:** "Information courtesy of IMDb (https://www.imdb.com). Used with permission." IMDb can withdraw permission at any time, so the ratings display must be removable without breaking Movie pages (see [Catalog and Rating source boundary](./10-source-boundary.md)).

- Email draft (2026-11-04). Send it through the contact you find on the IMDb licensing page:

    > **Subject:** Permission request: displaying IMDb ratings from the non-commercial datasets on a free movie site
    >
    > Hello IMDb licensing team,
    >
    > I'm building a free, public movie website (working name "Reelio"; the final name may change). It helps people discover movies, keep a private watchlist, and see where a movie is streaming. It will never earn money: no ads, affiliate links, paid tier, sponsorships or data sales, and that is a permanent decision, not a launch-phase one.
    >
    > I'd like your written permission to show IMDb's rating and vote count on each movie page, using the `title.ratings.tsv.gz` file from IMDb's non-commercial datasets, with this credit next to it: "Information courtesy of IMDb (https://www.imdb.com). Used with permission."
    >
    > To be clear about what the site does: it loads the whole ratings file into its database once a day, matches it to movies by IMDb id, and displays one rating and vote count per movie page, linked to IMDb. It doesn't offer a bulk download, an API, or search by rating, it doesn't alter the figures, and it doesn't show the other dataset files' data. It will stop displaying the ratings immediately if you withdraw permission.
    >
    > Could you confirm whether this use is permitted, and whether any conditions apply beyond the credit above?
    >
    > Thank you,
    > Rhyan

- What closes this task: IMDb's written reply, pasted or summarised here with its date. A refusal is a launch blocker, not a spec blocker. The fallbacks are the TMDB rating only, or the paid IMDb API on AWS Data Exchange ([OMDb ratings: coverage, terms and limits](./03-omdb-ratings.md)).
