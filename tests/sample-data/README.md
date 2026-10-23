# Synthetic sample data

`generate.php` fills a freshly installed Archon 3.21 database (SQL Server) with invented records shaped like one of the four UIUC installs. The shapes come from the survey of the live sites in `tests/research/live-survey/summary.md`: field lengths, date and extent formats, tree depths, and the share of records with digital content, creators and subjects. The records themselves are invented.

The test environment (`tests/testenv`) runs the generator for every site that has no `backups/<site>.bak`. `tests/tools/install-database.php` (Docker harness) also runs it, with `--profile=archives --size=small`.

## Usage

```
php tests/sample-data/generate.php --database=archon_ihlc --profile=ihlc [--size=small|standard|large] [--seed=N]
```

| Setting | Meaning |
|---|---|
| `--profile` | `archives` (University Archives plus the Sousa/American Music repository), `ihlc`, `rbml` or `ala` |
| `--size` | `small`: about a tenth of `standard`, for quick starts. `standard` (default): a few hundred collections. `large`: record counts close to the live sites. The counts are in `SampleGenerator::$Profiles`. |
| `--seed` | Random seed. The default is derived from profile and size, so a given profile and size always produces the same records. |
| `ARCHON_TEST_MSSQL_HOST`, `ARCHON_TEST_MSSQL_USER`, `ARCHON_TEST_MSSQL_SECRET` | SQL Server connection (defaults: `mssql`, `sa`) |
| `ARCHON_TEST_ADMIN_SECRET` | Password for the `teststaff`, `testreader` and `testresearcher` accounts |

The script needs the `sqlsrv` and `gd` extensions. It writes everything in one transaction, and it refuses to run against a database that already has collections.

## What it creates

* **Lookups:** repositories, extent units, material types, level/container types (including File, Reel, Volume, Oversize Folder and Steamer Trunk), languages, research purposes and researcher types, and the configuration values of the live sites.
* **Collections** with classifications, locations, creators, subjects, languages and finding aid fields. The content trees are box/folder, series, deep (several levels), music (reels and scores) and alphanumeric.
* **Digital content** with generated image files and thumbnails, **accessions**, **books** (rbml only), and **research appointments and carts**.
* **Accounts:** `teststaff` (Power Users), `testreader` (read-only staff), `testresearcher` (public researcher), and invented researchers.
* **Edge cases**, so that the PHP 8 port is exercised on the kinds of data production really has:
  * a disabled collection, and a duplicate whose title ends in "-test"
  * two collections that share a long teaser abstract
  * a collection without extent or dates
  * double-encoded text ("CafÃ© Society Records")
  * markup characters in titles and notes (`& " < >`)
  * a very long title with an empty identifier
  * Windows-1252 characters
  * two "Encoding sample" collections whose `varchar` values hold raw UTF-8 bytes, as Archon writes them when `DatabaseEncodingUTF8` is on
  * a content note that starts with a zero-width space

## Files

| File | Purpose |
|---|---|
| `generate.php` | Command-line entry point |
| `lib/SampleGenerator.php` | Profiles, record counts, and everything that is generated |
| `lib/SampleDatabase.php` | Batched inserts through sqlsrv, `IDENTITY_INSERT` handling, transaction |
| `lib/SampleText.php` | Invented names, titles, dates, extents and paragraphs |
| `lib/SampleFiles.php` | Generated images for digital content |
