# Archon production probe (read-only)

`archon-probe.php` is a single self-contained PHP file that surveys an Archon
installation and prints a JSON report. It is the "next best thing" to a
sanitized database dump: it records the runtime (PHP, extensions, ini, PEAR
MDB2, FreeTDS), the Archon configuration (credentials redacted), the file
manifest, the database schema/options, and **aggregate** data-shape metrics
(NULL vs. empty strings, encodings, HTML/BBCode, sizes). It does **not** export
row content, except for small lookup/configuration tables. Those are the same
values the public site shows, with e-mails and secrets masked.

It runs unchanged on the production stack (PHP 5.3/5.4 + `mssql` + FreeTDS) and
on PHP 8.2 + `sqlsrv`. The test environment runs it with `tests/bin/archon-test probe`
to produce a baseline.

## Read-only guarantees

* Every SQL statement must pass `probe_assert_readonly()`. It allows only
  `SELECT`/`WITH`, `DBCC USEROPTIONS` and a few session-only `SET` options.
  It rejects `INSERT/UPDATE/DELETE/MERGE/INTO/CREATE/ALTER/DROP/EXEC/...` and
  multiple statements.
* The session runs `SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED`,
  `SET DEADLOCK_PRIORITY LOW` and `SET LOCK_TIMEOUT 10000`. It takes no shared
  locks, cannot block the application, and is always the deadlock victim.
* Nothing is written to disk. `config.inc.php` is only `include`d, as Archon
  itself does on every request, inside a function scope with a throw-away
  `$_ARCHON` object. The report goes to stdout and progress goes to stderr.
* Redacted by default:
  * database login and password (`<set>`/`<empty>`);
  * any setting whose name looks like a password, key or token;
  * host names and IP addresses (`<fqdn:1a2b3c4d>`);
  * e-mail local parts (`<email>@illinois.edu`);
  * encrypted configuration values.

  Password hashes, session hashes, `ModificationLog.RequestData` and other
  row contents are never selected.

## What infra needs to run (exact parameters)

There are four Archon installations (archives — themes `library_web` and
`sousa2` —, ihlc, rbml, ala). Each one has its own `config.inc.php`, and
possibly its own database.

1. Copy `archon-probe.php` anywhere on the web server, e.g. `/tmp/archon-probe.php`.
   It does **not** have to be inside the web root.
2. Run it once per installation with the same PHP binary Apache uses, as a user
   that can read `config.inc.php` (normally the Apache user):

   ```sh
   cd /tmp
   for inst in archives ihlc rbml ala; do
     sudo -u apache /usr/bin/php /tmp/archon-probe.php \
         --archon-dir=/var/www/html/$inst \
         > probe-$inst.json 2> probe-$inst.log
   done
   ```

   Replace `/var/www/html/$inst` with the real installation directories. These
   are the directories that contain `config.inc.php` and `packages/`. The
   default run includes the `profile` section, which scans each table once
   at READ UNCOMMITTED. On a typical Archon database this takes seconds to a few
   minutes.
   * To skip the scan, add `--sections=php,freetds,archon,files,apache,database`.
   * To bound it, add `--max-profile-rows=500000`.
3. (Optional) Use a dedicated read-only database login instead of the
   application credentials:

   ```sql
   -- run by a DBA, once per Archon database
   CREATE LOGIN archon_probe WITH PASSWORD = '<strong password>';
   USE [<ArchonDatabase>];
   CREATE USER archon_probe FOR LOGIN archon_probe;
   EXEC sp_addrolemember 'db_datareader', 'archon_probe';
   GRANT VIEW DEFINITION TO archon_probe;
   ```

   ```sh
   ARCHON_PROBE_DB_PASSWORD='<password>' sudo -E -u apache /usr/bin/php /tmp/archon-probe.php \
       --archon-dir=/var/www/html/archives --db-login=archon_probe > probe-archives.json
   ```

   The default (application login) is preferred, because it also reports the
   permissions and session options the application really runs with. Those
   matter for the PHP 8 driver.
4. Review the JSON files. They are plain, pretty-printed text. Send back the
   `probe-*.json` and `probe-*.log` files and delete `/tmp/archon-probe.php`.

If running PHP on the command line is not possible, there is a web mode:
* Set `SetEnv ARCHON_PROBE_WEB_TOKEN <random string of at least 24 characters>`
  for the directory that holds the probe.
* Request `archon-probe.php?token=<value>`.

Without a matching token, every request gets `403`. Remove the file and the
`SetEnv` afterwards. Web mode reports the exact `mod_php` ini values, but
otherwise collects the same data as the CLI.

### Options

| Option | Purpose |
| --- | --- |
| `--archon-dir=PATH` | Installation directory (auto-detected from cwd / the probe location). |
| `--sections=LIST` | Subset of `php,freetds,archon,files,apache,database,profile`. |
| `--show-hosts` | Do not redact host names, IP addresses or logins. |
| `--db-driver=auto\|mssql\|sqlsrv` | Force a driver (production: `mssql`). |
| `--db-login=LOGIN` | Use another login; the password is read from `ARCHON_PROBE_DB_PASSWORD`. |
| `--max-profile-rows=N` | Skip profiling tables with more than N rows (default 2,000,000). |
| `--query-timeout=SECONDS` | Per-query timeout (default 300). |
| `--no-lookup-dumps` | Omit lookup/configuration table contents. |
| `--max-files=N` | Cap for the file manifest (default 50,000). |

## What the report answers

| Open question | Where in the report |
| --- | --- |
| PHP/ini/extension baseline, `short_open_tag`, `magic_quotes`, `default_charset` | `php.ini`, `php.extensions` |
| Exact MDB2/PEAR versions in production | `php.pear_files`, `php.pear_registry` |
| `crypt()` compatibility of stored password hashes | `php.crypt.md5crypt_known_answer` (PHP 8.2 gives `$1$probe123$g.GA71JPiPiUMYq4qeBYe0`), `database.application.password_hash_formats` |
| TDS version, client charset, text size | `freetds.*`, `archon.db_config.VersionTDS`, `database.session_as_application_sees_it` |
| ServerAddress format (`host:port`, `host\instance`, DSN) | `archon.db_config.ServerAddress` (shape kept, name hashed) |
| Database collation, compatibility level, ANSI options, RCSI | `database.database`, `database.session*` |
| Encrypted connections / TLS | `database.connection_dmv[].encrypt_option` |
| Local schema changes (e.g. `tblAccessions_Accessions.RepositoryID`, missing `tblCollections_ResearchAppointmentFields`) | `database.columns` (compare with `compare-probe.php schema`) |
| NULL vs. empty string prevalence, whitespace | `profile.<table>.columns.<col>.null/empty/spaces_only/trailing_ws` |
| Encoding: UTF-8 bytes stored in varchar, lossy `?`, mojibake | `profile...non_ascii / utf8_bytes_or_mojibake / letter_qmark_letter / cp1252_mojibake` |
| HTML / BBCode in descriptive fields | `profile...html_tag / html_entity / bbcode` |
| Digital file sizes (memory limits for streaming BLOBs) | `database.application.digital_files*`, `profile.tblDigitalLibrary_Files` |
| Which admin features are actually used | `database.application.modification_log_usage` |
| Configuration values (`Check For Updates`, search options, ...) | `database.application.lookup_tables.tblCore_Configuration` |
| Local code modifications on the server | `files.manifest` (compare with `compare-probe.php manifest`) |
| Apache `php_value` overrides, aliases, rewrites | `apache.config_lines` |

## Reading the reports

```sh
php tests/tools/compare-probe.php summary probe-archives.json
php tests/tools/compare-probe.php schema tests/docker/artifacts/php82/probe-test.json probe-archives.json
php tests/tools/compare-probe.php manifest probe-archives.json --rev=35bbafc
```

`35bbafc` is the last commit before the PHP 8 work. The manifest comparison
lists files that were edited, added or removed directly on the server. Any such
changes have to be carried over to the PHP 8.2 branch.
