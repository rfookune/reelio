# 12: Browse analytics event

Status: resolved

Parent: [spec](../spec.md).

**Blocked by:** 03

**What to build:** A Browse results page records one analytics event on its first page, carrying which filters were used (names only), the tab and the result count, so that filter use can be understood. It never carries values tied to a person. The existing `title_searched` event is unchanged and still records only searches by Name.

- [x] A new event is recorded once for the first page of a Browse results page, and not for later pages
- [x] It carries the names of the filters used, the tab and the result count, and no filter values that identify a person
- [x] A reload that only refreshes the viewer's facts records nothing
- [x] `title_searched` still records only searches by Name
- [x] Feature tests cover the event on the first page, the absence on later pages and reloads, and that it carries no values tied to a person
