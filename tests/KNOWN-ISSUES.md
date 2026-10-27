# Known issues, risks and open questions (PHP 8.2 port)

Status as of 2026-10-27. Branch: `copilot/copilotupdate-php-compatibility`.

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
| 1.11 | Session cookie `archon` has no `HttpOnly` and no `SameSite`. PHP 5.4's `session_set_cookie_params()` forced HttpOnly off. | `packages/core/lib/livesession.inc.php` `_setCookieParams()` | Live response headers. | **Fixed for PHP 8.2 without code changes:** PHP 8 keeps the ini values when the argument is omitted, so `session.cookie_httponly`/`session.cookie_samesite` in `php.ini` now apply (set in the deployment image; verified behind Traefik: `archon=…; secure; HttpOnly; SameSite=Lax`). |
| 1.12 | Debug footer "Page Generated in … (using N queries)" on every public page. | theme footers | Live survey §H. | Cosmetic / minor information leak; remove from the footers if wanted. |
| 1.13 | IE stylesheet path is doubled (`themes/themes/<theme>/…cluetip.ie.css`). | theme headers | Live survey §H. | Harmless (old IE only). |
| 1.14 | Mojibake (`Ã©`, `â€™`) and literal `?` in some records. | data | Live survey §F. | Probably stored bytes; confirm with the probe (§4, U2). |
| 1.15 | Hard-coded `http://` in links (MARC 856, EAC, account-activation e-mail). | see 1.9, `creators/templates/eac/`, `core/lib/user.inc.php` | Code. | Cosmetic; browsers follow the redirect to https. |
| 1.16 | Creating a book fails on a **fresh** 3.21 install: `Invalid column name 'Identifier'`. | `collections/install/install-mssql.sql` creates `tblCollections_Books` without `Identifier`; only the 3.21 upgrade script (`collections/install/3.21/structure-mssql.sql`) adds it, but `book.inc.php` and `admin/books.php` use it. | CRUD harness in the test environment (`collections/books` create). Production was upgraded, so it probably has the column; a restored `.bak` will show it. | Check production with `archon-testenv sql archives "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='tblCollections_Books'"` after restoring a `.bak`. For fresh installs add the column to `install-mssql.sql`/`install-mysql.sql`. |

## 2. Caused by PHP 8.2 / the new driver — still open or not fully verified

Fatal PHP 8 errors found by the scanners, the crawl and the CRUD harness have been fixed on this branch
(removed functions and syntax such as `each()`, `create_function()` and the `/e` regex modifier, PHP 4
constructors, undefined constants such as `PACKAGE_RESEARCH`, calls on null objects, and the
`mssql` → `sqlsrv` MDB2 driver). What is left:

| # | Problem | Origin | Impact | Suggested action |
| --- | --- | --- | --- | --- |
| 2.1 | **Warnings and deprecations.** PHP 5.4 notices are now warnings: `Undefined array key`, `Undefined variable`, `Attempt to read property on null`, and 8.2's dynamic-property deprecation for classes other than `AObject`/`ArchonObject`. | PHP 8.2 | Not fatal and not shown to visitors (`display_errors = Off`), but they fill the PHP log. A few hide real logic bugs that PHP 5.4 also hid. | `archon-testenv diagnostics` lists them grouped by file and line; fix the most frequent ones. Counts are in §5. |
| 2.2 | **Text encoding through `sqlsrv`.** The new driver reads `varchar` columns through the SQL Server code page (Windows-1252) and converts them to UTF-8. Production's settings are now known: FreeTDS `client charset = UTF-8`, `DatabaseEncodingUTF8 = true`, `mssql.charset` not set. FreeTDS then does the same conversion. With these settings, the old-stack test (§7, 7.4) returned the same text as PHP 8.2. Text stored as single bytes is correct on both. Text stored as raw UTF-8 bytes is double-encoded on both: the mojibake already on the live sites (1.14) looks the same on 8.2. | Expected to match (to be confirmed on real data) | Expected: none. Characters outside Windows-1252 that the live pages show correctly (`ḷ`, `ġ`, `ō`, U+0301, U+200B) cannot be stored in a Windows-1252 `varchar`. They must be in `nvarchar`/`ntext` columns or stored as HTML character references, and both read the same on 8.2 (U2). | Run the migration API comparison (`tests/migration-api/`) and the probe's `profile` section on a restored `.bak` (`tests/testenv/README.md`, "Testing a production backup"). |
| 2.3 | **Existing password hashes.** Archon uses `crypt()` MD5 (`$1$`) hashes. PHP 8.2 still supports them; the probe's `php.crypt.md5crypt_known_answer` check confirms it. | Unknown | Staff could not log in if hashes from production fail to verify. | Log in with a real account after restoring a `.bak`. |
| 2.4 | Direct requests to include files (e.g. `/config.inc.php`) return 500 instead of a blank page. | PHP 8.2 | None (no output, nothing leaked). | The container deployment (`docker-compose*.yml`) denies `*.inc.php` (403). The host-Apache test environment still returns 500. Production can add the same `FilesMatch` rule. Production also serves the `_dev/` developer scripts; the containers do not copy them. |
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
| U2 | Production's driver settings are known (2.2). Still open: how many `varchar` values hold raw UTF-8 bytes (mojibake on both stacks), and where the characters outside Windows-1252 are stored (`nvarchar` columns or `&#…;` references). | 2.2, 1.14 | Probe `profile` section on a restored `.bak` (`utf8_bytes_or_mojibake`, `non_latin1` and `cp1252_mojibake` per column, and the column types). |
| U3 | SQL Server version and database compatibility level. | `.bak` restore into SQL Server 2022 (needs 2008 or later). | Probe. |
| U4 | Configuration Manager values and enabled packages per install (default template set and theme, `ForceHTTPS`, admin IP restriction, public EAD list, research appointments). | Behaviour behind Traefik (§6), feature parity. | Probe. |
| U5 | Digital files: stored in the database or on disk? Total size? | Volumes and backup for the container deployment. | Probe. |
| U6 | PHP runtime settings (timezone, memory, execution time, upload size). | Parity; the image uses America/Chicago, 512M, 300 s, 32M. | Probe. |
| U7 | Is the live code identical to PR #3 plus master? | Anything else changed on the server is not in git. | Diff the production code tree against PR #3. |
| U8 | Cron jobs and tooling that produce the PDFs on `files.archon.library.illinois.edu`. | Must keep running after the move. | Server crontab. |
| U9 | Outgoing mail: which relay does production use? | Research appointment, account activation and "e-mail us" forms call `mail()`; containers have no local MTA. | Server postfix/sendmail configuration. |
| U10 | Search (`core/search`, `searchmultiple`, PDF search) on real data. | Not exercised against production-size data. | Test after restoring a `.bak`. |

## 5. Measured on PHP 8.2 (test environment, generated sample data)

- CRUD harness (`archon-testenv crud archives`, generated `small` sample data, fresh install): 30 of 42 scenarios pass. Report: `/var/log/archon/crud-archives.json`. The failures are listed below. None is confirmed as a PHP 8.2 error; most are the harness expecting fixture data that the generated sample data does not contain:
  - `collections/books`: fatal `Invalid column name 'Identifier'`, pre-existing upstream schema gap (1.16).
  - `accessions`: "Processing Priority ID 1 not found" and `locations` delete blocked by location entries: the sample data has no priority 1 and its accessions use the shared location. Harness/data assumption.
  - `avsap`: "Format Base/Composition is required"; `core/configuration`: "37 is not a valid Non-Negative Number"; `usergroups` DefaultPermissions 0 vs 1; `collections` `OtherURL`/`RelatedMaterialsURL` gain an `http://` prefix; creators related-description value: the app's validation or normalisation differs from what the harness posts or expects. Harness expectation; to be confirmed against production behaviour.
  - `digitallibrary` file store "Invalid DigitalContentID", `core/sessions` "Session ID not defined", `core/mypreferences` "Unknown command: store", public registration returning HTTP 200 instead of a redirect: **not yet triaged** (could be harness or PHP 8.2). Re-run with `--only=REGEX --verbose` to investigate.
  - Diagnostics during the run: 1 fatal (the books one above), 2 warnings, 1451 former notices (PHP 8 warnings that PHP 5.4 reported as notices).
- The crawl visits every public route and template set; all pages render except the pre-existing failures
  in §1 (1.2 reproduces as `Invalid column name 'RepositoryID'`).
- Warning/deprecation counts: run `archon-testenv diagnostics` (see `tests/testenv/README.md`).

## 6. Features that can be switched off if something cannot be fixed in time

| Feature | How to switch off | Effect |
| --- | --- | --- |
| Public EAD list (1.1) | Configuration Manager → Collections → "Enable Public EAD List" = No | The page says "Public EAD List not enabled." instead of 500. |
| Research appointments / e-mail forms (U9) | Disable research appointments in Configuration Manager, or add a mail relay to the image (not built yet: the containers have no MTA, so `mail()` fails) | No e-mails are attempted. |
| Admin IP restriction behind a proxy | Keep `RestrictAdminByIpRange` off until the proxy passes the client IP (the container deployment does) | Otherwise everyone looks like the proxy's address. |
| Edits during migration | PR #6 read-only mode | Public site stays up while the database is copied. |

## 7. Migration API (archon-migration)

**Method.** `tests/migration-api/harvest.php` repeats every request that archon-migration's `ArchonClient` makes. It was run against two stacks reading the same SQL Server 2022 database:

* the unmodified `master` code on CentOS 7 with PHP 5.4.16, `php-mssql`, FreeTDS 1.4 and PEAR MDB2;
* this branch on PHP 8.2 with `sqlsrv`.

The database held the generated sample data plus rows with chosen bytes: Windows-1252 text, raw UTF-8 bytes, and decimals below 1. The old stack was run in six configurations: three FreeTDS `client charset` settings, each with `DatabaseEncodingUTF8 = true` and with the setting left out. `compare.php` compared each harvest with PHP 8.2's.

**Results.** In every configuration, paging, record counts, HTTP status codes (apart from 7.1), dates and the structure of every record were identical. These were the differences:

| # | Difference | Origin | Effect on the migration | Suggested action |
| --- | --- | --- | --- | --- |
| 7.1 | **`core/enums&enum_type=countries` returned HTTP 500.** `enum-list.inc.php` called `array_walk($arrEnumbatch, 'RemoveCountries')`, but `RemoveCountries()` does not exist. PHP 5.4 gave a warning (hidden by `error_reporting(0)`) and left the list unchanged. PHP 8 throws a `TypeError`. | PHP 8.2 | Without the fix, `getEnumList()` gets null and the countries list is dropped. `ASpaceEnumUtil.getASpaceCountryCode()` then falls back to `"US"` for every repository and user country. | **Fixed on this branch:** the line is removed, so the output is identical to PHP 5.4. |
| 7.2 | **Decimals below 1 lost the leading zero:** `.50` instead of `0.50`, and `.00` instead of `0.00`. `sqlsrv` formats `decimal` that way by default. Affected: Collections `Extent`, collection location `Extent`, Accessions `ReceivedExtent`/`UnprocessedExtent`, accession location `Extent`, and the AVSAP `decimal(3,2)`/`decimal(9,2)` columns. Public pages showed it too. | PHP 8.2 (driver) | Without the fix, `ASpaceMapper` copies the string as-is into the ArchivesSpace extent `number` and the location extent note. Amounts of 1 or more are unchanged. | **Fixed on this branch:** the adapter connects with the `sqlsrv` option `FormatDecimals` (sqlsrv 5.6 and later), which returns `0.50` as the `mssql` driver did. |
| 7.3 | **Digital files differ.** MDB2's portability `RTRIM` on the old stack strips trailing spaces, tabs, CR, LF, NUL and vertical tab from the file bytes. The new adapter returns the stored bytes. In the sample data, 12 of 41 files differ: PDFs that end in `%%EOF\n`. | Pre-existing (old stack loses bytes) | On PHP 8.2 the migrated files are the stored files. The old stack truncates any file that ends in those bytes. Both stacks add a trailing `1` (`echo print` in `digitalfileblob.inc.php`). | Nothing on 8.2. For the extra `1`: change `echo print` to `echo`, or have the migration drop the last byte. |
| 7.4 | **Text, numbers and nulls depend on production's configuration.** See the table below. Production uses the first row (FreeTDS `client charset = UTF-8`, `DatabaseEncodingUTF8 = true`). | Expected to match | None expected: in that configuration the old stack returned the same text, numbers and NULLs as 8.2. | Confirm with `tests/migration-api/` on a restored `.bak`. |
| 7.5 | `DatabaseEncodingUTF8 = false` does not work on 8.2. `sqlsrv` fails with "Negative field length calculated in buffered result set" (HTTP 500) on any batch with non-ASCII text, and the text that does come back is mis-encoded. Leaving the setting out of `config.inc.php` has the same effect as `false`. | PHP 8.2 (driver) | Lists stop early. | Keep `DatabaseEncodingUTF8 = true`, as production, the test environment and the deployment do. |
| 7.6 | The API only works with the session cookie. Without it, `LiveSession::verifySession()` runs `new User2x(...)`, a class that exists only in the upgrade code, and returns HTTP 500. `ArchonClient` keeps cookies, so the migration works. | Pre-existing | None, as long as the cookie reaches PHP. Archon sets the cookie path from `SCRIPT_NAME`, so behind a proxy the path PHP sees must be a prefix of the public path. | Keep the same path inside and outside the proxy, as the container deployment does. |

**7.4 in detail.** PHP 8.2 runs with `DatabaseEncodingUTF8 = true` (7.5). It returns text stored as single Windows-1252 bytes correctly (`é`, `“`, `€`). Text stored as raw UTF-8 bytes comes back double-encoded (`Café` becomes `CafÃ©`). Numbers are JSON numbers and NULL columns are JSON `null`. Production today returns:

| FreeTDS `client charset` | `DatabaseEncodingUTF8` | Text stored as single bytes | Text stored as raw UTF-8 bytes | Numbers and NULL columns |
| --- | --- | --- | --- | --- |
| `UTF-8` (**production**) | `true` (**production**) | Same as 8.2 | Same as 8.2 (double-encoded) | Same as 8.2 |
| `CP1252` | `true` | Whole field `null` | Correct | Same as 8.2 |
| not set (ISO-8859-1) | `true` | Whole field `null` | Correct, unless a character's UTF-8 bytes include 0x80–0x9F (`“`, `–`, `€`, `ł`); then the whole field is `null` | Same as 8.2 |
| not set (ISO-8859-1) | left out (Archon's default) | Correct, except `?` for `“ ” ‘ ’ – — € …` | Double-encoded | Strings (`"17"`); NULL becomes `""` |
| `CP1252` | left out | Correct, except `“ ” ‘ ’ – — € …` become invisible control characters | Double-encoded | Strings; NULL becomes `""` |
| `UTF-8` | left out | Double-encoded | Double-encoded twice | Strings; NULL becomes `""` |

* **`null` text.** In the rows marked "whole field `null`", the current migration already receives `null` for those fields. archon-migration's `getString()` turns `null` into the text `"null"`.
* **Strings and `""`** (does not apply to production, which sets `true`). If the setting is left out, every value it sends is a string and NULL columns arrive as `""`. On 8.2, `getString()` on those fields returns `"null"` instead of `""`. The migration skips empty notes, but it does not skip `"null"`. In the sample data the NULL fields were subject `Parent`, creator `CreatorRelationships` and relationship `Description` (the migration checks these for `"null"`), classification `Description`, and digital-file `Title`.
* **Production configuration (confirmed 2026-10-27).** `/etc/freetds.conf` sets `client charset = UTF-8`, `mssql.charset` is commented out in `php.ini`, and `config.inc.php` sets `DatabaseEncodingUTF8 = true`: the first row. An earlier version of this file guessed the `CP1252` + `true` row, because the live pages show `ḷ`, `ġ`, `ō`, U+0301 and U+200B correctly. The confirmed settings rule that out. With `client charset = UTF-8`, raw UTF-8 bytes in a `varchar` column come out double-encoded, as the existing mojibake (1.14) does. Those characters must therefore be in `nvarchar`/`ntext` columns, which the stock schema does not use but a local change could, or be stored as HTML character references (`&#7735;`) in fields that are printed without escaping. Both read the same on 8.2. One check remains: `client charset` only applies if it is in `[global]` or in the section named like `$_ARCHON->db->ServerAddress`. Production also forces TDS 7.0 (`TDSVER=70` in `start.inc.php` unless `VersionTDS` is set), while `sqlsrv` uses TDS 7.4. The old-stack test returned correct Windows-1252 text in this row (`“`, `€`), so FreeTDS used the server's code page there. Whether it does the same under TDS 7.0 on production has not been checked; the comparison on the `.bak` will show it.
* **How to check on the restored `.bak`.** The differential test settles it: in this configuration it should report no `null-in-*`, `type`, `double-encoded` or `question-marks` differences. To see the stored bytes of one record, run `SELECT CONVERT(varbinary(200), Title) FROM tblCollections_Collections WHERE ID = …`: `C3A9` is a UTF-8 `é`, and `E9` is a single-byte `é`.
