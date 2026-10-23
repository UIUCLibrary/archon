# Known issues, risks and open questions (PHP 8.2 port)

Status as of 2026-10-23. Branch: `copilot/copilotupdate-php-compatibility`.

Each row says **whether it is broken today on production (PHP 5.4)** or **was caused by the move to
PHP 8.2 / containers**. Evidence comes from the live-site survey (`tests/research/live-survey/summary.md`),
the PHP 8.2 crawl and CRUD harness (`tests/tools/`), and code reading.

Legend for the "Origin" column:

| Origin | Meaning |
| --- | --- |
| **Pre-existing** | Same behaviour on production today (PHP 5.4). Not caused by the port. Fixing it is optional for the upgrade. |
| **PHP 8.2** | Caused by the newer PHP, or by the `sqlsrv` driver that replaces `mssql`/FreeTDS. |
| **Deployment** | Caused by running in containers / behind Traefik, not by PHP. |
| **Unknown** | Cannot be decided without production data, configuration or logs. |

## 1. Broken today on production (pre-existing; same on PHP 8.2 unless noted)

| # | Problem | Where | Evidence | Suggested action |
| --- | --- | --- | --- | --- |
| 1.1 | **EAD list page returns HTTP 500** (empty body) on all four installs. Cause unknown. | `?p=collections/eadlist` → `packages/collections/pub/eadlist.php` | Live survey §A/§G. | Read the production PHP error log for this URL. Check the PHP 8.2 behaviour in the test environment (see §5). |
| 1.2 | **Accessions list with a repository selected returns HTTP 500.** `countAccessions()` filters on a `RepositoryID` column that `tblAccessions_Accessions` does not have. | `?p=accessions/accessions&setrepositoryid=2`, `packages/accessions/lib/core/archon.inc.php` (`countAccessions`, ~line 357) | Live 500; PHP 8.2 crawl logs `Invalid column name 'RepositoryID'`. | Remove the repository filter or join through `tblCollections_Collections`. |
| 1.3 | **Subjects browse pagination is wrong.** `subjects.php` passes `LIMIT+1, offset` into the `ShowChildren`/`Limit` positions of `searchSubjects()`. Page 1 and "View all" load every subject (archives: 24 s, ~12,500 queries) and drop the last row; pages ≥ 3 always show "Next". | `packages/subjects/pub/subjects.php` → `searchSubjects()` | Live survey §H. | Pass arguments in the right positions. Worth doing: it is the slowest page on production. |
| 1.4 | Browsing past the last page sends a 302 with `header()` but no `exit`, so the page is still rendered. | collections, creators, digital library browse | Live survey §H. | Add `exit` after the redirects (harmless otherwise). |
| 1.5 | Non-existent record IDs (e.g. `controlcard&id=999999`) return 200 with an empty main area instead of 404. | public record pages | Live survey §H. | Optional. |
| 1.6 | **rbml collection list shows the wrong teaser** for collections without Abstract/Scope (the previous collection's text). | `packages/collections/templates/rbml/collectionlist.inc.php` (`$collTeaser` never reset) | Live survey §D. | Reset `$collTeaser` at the top of the loop. |
| 1.7 | EAC export: `entityType` is always `person` (`case "Personal Name" \|\| ...` evaluates to `case true`). `schemaLocation` has trailing spaces. | `packages/creators/templates/eac/` | Live survey §D/§G. | Fix the `switch`. |
| 1.8 | EAD export has no `<?xml ...?>` declaration. | `packages/collections/templates/ead/` | Live survey §G. | Optional. |
| 1.9 | MARC export: field 005 uses the 12-hour `date('h')`; BBCode left raw in 520; 856 links hard-coded `http://`. | `packages/collections/pub/marc.php`, `marcbook.php`, `db/export-marc.inc.php` | Live survey §G. | `date('H')`; strip BBCode; use the request scheme. |
| 1.10 | BBCode shown raw in some list teasers (ihlc, rbml) and MARC. | `bbcode_to_html` callers | Live survey §F. | See PR #5 (§3). |
| 1.11 | Session cookie `archon` has no `HttpOnly` and no `SameSite`. PHP 5.4's `session_set_cookie_params()` forced HttpOnly off. | `packages/core/lib/livesession.inc.php` `_setCookieParams()` | Live response headers. | **Fixed for PHP 8.2 without code changes:** PHP 8 keeps the ini values when the argument is omitted, so `session.cookie_httponly`/`session.cookie_samesite` in `php.ini` now apply (set in the deployment image). |
| 1.12 | Debug footer "Page Generated in … (using N queries)" on every public page. | theme footers | Live survey §H. | Cosmetic / minor information leak; remove from the footers if wanted. |
| 1.13 | IE stylesheet path is doubled (`themes/themes/<theme>/…cluetip.ie.css`). | theme headers | Live survey §H. | Harmless (old IE only). |
| 1.14 | Mojibake (`Ã©`, `â€™`) and literal `?` in some records. | data | Live survey §F. | Probably stored bytes; confirm with the probe (§4, U2). |
| 1.15 | Hard-coded `http://` in links (MARC 856, EAC, account-activation e-mail). | see 1.9, `creators/templates/eac/`, `core/lib/user.inc.php` | Code. | Cosmetic; browsers follow the redirect to https. |

## 2. Caused by PHP 8.2 / the new driver — still open or not fully verified

Fatal PHP 8 errors found by the scanners, the crawl and the CRUD harness have been fixed on this branch
(removed functions and syntax such as `each()`, `create_function()` and the `/e` regex modifier, PHP 4
constructors, undefined constants such as `PACKAGE_RESEARCH`, calls on null objects, and the
`mssql` → `sqlsrv` MDB2 driver). What is left:

| # | Problem | Origin | Impact | Suggested action |
| --- | --- | --- | --- | --- |
| 2.1 | **Warnings and deprecations.** PHP 5.4 notices are now warnings: `Undefined array key`, `Undefined variable`, `Attempt to read property on null`, and 8.2's dynamic-property deprecation for classes other than `AObject`/`ArchonObject`. | PHP 8.2 | Not fatal and not shown to visitors (`display_errors = Off`), but they fill the PHP log. A few hide real logic bugs that PHP 5.4 also hid. | `archon-testenv diagnostics` lists them grouped by file and line; fix the most frequent ones. Counts are in §5. |
| 2.2 | **Text encoding through `sqlsrv`.** The new driver reads `varchar` columns through the SQL Server code page and converts them to UTF-8. If production (FreeTDS) stored raw UTF-8 bytes in `varchar` columns, every non-ASCII character will show as mojibake on PHP 8.2. | PHP 8.2 / Unknown | Potentially every page with accented text. **Biggest open risk.** | Run the read-only probe (`tests/production-probe/`) and restore a real `.bak` into the test environment and compare pages. |
| 2.3 | **Existing password hashes.** Archon uses `crypt()` MD5 (`$1$`) hashes. PHP 8.2 still supports them; the probe's `php.crypt.md5crypt_known_answer` check confirms it. | Unknown | Staff could not log in if hashes from production fail to verify. | Log in with a real account after restoring a `.bak`. |
| 2.4 | Direct requests to include files (e.g. `/config.inc.php`) return 500 instead of a blank page. | PHP 8.2 | None (no output, nothing leaked). | Optionally deny `*.inc.php` in the web server. |
| 2.5 | Vendored library in PR #3: `league/html-to-markdown` **2.2.2** (2015, `HTML_To_Markdown` class) in `themes/ala_redo` and `themes/ihlc`. | Unknown (probably fine) | Read through: plain `DOMDocument` code with no removed PHP functions, so it should run on 8.2. Not executed yet because PR #3 is not on this branch. | Crawl the AtoM/DLS export pages after rebasing PR #3. Upgrading to ^5 later is optional; its class is renamed to `HtmlConverter`. |

## 3. Work that is not on this branch yet

These open PRs are based on the old PHP 5.4 code and will conflict with or need re-testing on this branch:

| PR | Content | Risk on PHP 8.2 |
| --- | --- | --- |
| #3 Theme and template updates – 2024 | The customised **live** templates: Aeon request links, location tables/modals, PDF finding-aid links, AtoM/DLS export pages (`listall-*.inc.php`), `lincoln-table.inc.php`, vendored html-to-markdown. | Never run on PHP 8.2. Must be rebased and crawled; production pages cannot be compared until these templates are in. |
| #4 ArchivesSpace migration fixes | One line in `collection-list.inc.php`. | Low. |
| #5 BBCode regex hotfix | Rewrites `bbcode_to_html` with `preg_replace_callback`; converts `archonobject.inc.php` to LF. | **Conflicts** with this branch, which also rewrote that function for PHP 8 (`/e` modifier removed). Merge by hand, then run `tests/tools/find-regex-issues.php` and the crawl. |
| #6 Read-only mode | `ReadOnlyMode` config flag checked in `Security::verifyPermissions()`. | Low; useful for a staged cut-over (see §6). |

## 4. Unknowns that need production information

The read-only probe (`tests/production-probe/README.md`) answers most of these without changing anything on
the server.

| # | Question | Why it matters | How to answer |
| --- | --- | --- | --- |
| U1 | Why does `collections/eadlist` return 500? | 1.1 | Production PHP error log (`/var/log/httpd/error_log` or the PHP `error_log`). |
| U2 | Are `varchar` columns holding UTF-8 bytes? Collation? | 2.2 | Probe (`db.*` checks), or restore a `.bak`. |
| U3 | SQL Server version and database compatibility level. | `.bak` restore into SQL Server 2022 (needs 2008 or later). | Probe. |
| U4 | Configuration Manager values and enabled packages per install (default template set and theme, `ForceHTTPS`, admin IP restriction, public EAD list, research appointments). | Behaviour behind Traefik (§6), feature parity. | Probe. |
| U5 | Digital files: stored in the database or on disk? Total size? | Volumes and backup for the container deployment. | Probe. |
| U6 | PHP runtime settings (timezone, memory, execution time, upload size). | Parity; the image uses America/Chicago, 512M, 300 s, 32M. | Probe. |
| U7 | Is the live code identical to PR #3 plus master? | Anything else changed on the server is not in git. | Diff the production code tree against PR #3. |
| U8 | Cron jobs and tooling that produce the PDFs on `files.archon.library.illinois.edu`. | Must keep running after the move. | Server crontab. |
| U9 | Outgoing mail: which relay does production use? | Research appointment, account activation and "e-mail us" forms call `mail()`; containers have no local MTA. | Server postfix/sendmail configuration. |
| U10 | Search (`core/search`, `searchmultiple`, PDF search) on real data. | Not exercised against production-size data. | Test after restoring a `.bak`. |

## 5. Measured on PHP 8.2 (test environment, generated sample data)

- CRUD harness: create/read/update/delete for every admin module listed in `tests/tools/crud-scenarios.php`
  (lookups, creators, subjects, classifications, books, locations, AVSAP, collections, collection content,
  digital library with file upload, accessions, users, user groups, researchers, research appointments,
  configuration, public registration, research cart) passes.
- The crawl visits every public route and template set; all pages render except the pre-existing failures
  in §1 (1.2 reproduces as `Invalid column name 'RepositoryID'`).
- Warning/deprecation counts: run `archon-testenv diagnostics` (see `tests/testenv/README.md`).

## 6. Features that can be switched off if something cannot be fixed in time

| Feature | How to switch off | Effect |
| --- | --- | --- |
| Public EAD list (1.1) | Configuration Manager → Collections → "Enable Public EAD List" = No | The page says "Public EAD List not enabled." instead of 500. |
| Research appointments / e-mail forms (U9) | Disable research appointments in Configuration Manager, or configure a relay (`ARCHON_SMTP_HOST` in the container deployment) | No e-mails are attempted. |
| Admin IP restriction behind a proxy | Keep `RestrictAdminByIpRange` off until the proxy passes the client IP (the container deployment does) | Otherwise everyone looks like the proxy's address. |
| Edits during migration | PR #6 read-only mode | Public site stays up while the database is copied. |
