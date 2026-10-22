# Live Archon survey (archon.library.illinois.edu, 2026-10-22)

This is a read-only survey of the five public Archon instances. It was used to design the synthetic test fixtures and
to find behaviour the PHP 8.2 port has to preserve. It is kept here so later sessions don't have to crawl the live
sites again. **Read `summary.md` before re-crawling anything.**

| key | public URL | theme | template set | database |
|---|---|---|---|---|
| ihlc | https://archon.library.illinois.edu/ihlc/ | ihlc | ihlc | own |
| libweb | https://archon.library.illinois.edu/archives/?settheme=library_web&setrepositoryid=0 | library_web | illinois | archives (all repositories) |
| sousa2 | https://archon.library.illinois.edu/archives/index.php?settheme=sousa2&setrepositoryid=2 | sousa2 | illinois | archives, RepositoryID 2 |
| rbml | https://archon.library.illinois.edu/rbml/ | rbml | rbml | probably own |
| ala | https://archon.library.illinois.edu/ala/ | ala_redo | ala | own |

Production banner: Apache 2.4.6 (CentOS), **PHP 5.4.16**, "Archon Version 3.21 rev-3". All pages are served as UTF-8.

## Files

- `summary.md`: the findings, in these sections:
  - A–H: instances, records, hierarchy, creators/subjects, digital library, encoding quirks, exports, infrastructure.
  - Fixture recommendations.
  - Unknowns that only the database or infra team can answer.
- `urls.txt`: every route pattern seen on each instance, with an example URL and whether it was fetched. Use it as the crawler's route inventory.
- `requests.log`: audit trail of all 233 requests (time, number, instance, name, status, bytes, seconds, content type, URL).
- `html/`: 51 representative responses, byte for byte.
  - `INDEX.txt` maps each file to its source URL.
  - The three finding aids over 1 MB are gzipped (`*.html.gz`).
  - `headers/` holds some response headers, with session ids redacted.
  - Image previews (`*.jpg`) were not committed; `INDEX.txt` still lists their URLs.
- `scripts/`: the helper scripts.
  - `fetch.sh` is a rate-limited (≥ 1.2 s), GET-only fetcher. It refuses `f=`, admin, login and account URLs and stops after 400 requests. Its output directory is `$SURVEY_OUT`.
  - The `*.py` files are ad-hoc parsers for counts, finding aids, quirks and the URL inventory.

## Rules for any further survey

- GET only. Never request `f=` actions, admin, login, register or account pages, or anything that changes state.
- Keep at least 1.2 s between requests, use one cookie jar per instance, and stop after a few hundred requests.
- Don't probe pagination exponentially. The subjects pagination bug means pages from 3 upward are never empty (see `summary.md` §H).
- The live templates are customised beyond this repository (Aeon request links, location tables, "PDF Box/Folder List"), so compare structure, not exact HTML.
- The fixtures must be synthetic. Use the shapes, sizes and quirks recorded here, never the live text.
