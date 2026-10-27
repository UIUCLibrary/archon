# Migration API differential test

[archon-migration](https://github.com/UIUCLibrary/archon-migration) reads Archon only through Archon's JSON API (`?p=core/authenticate`, `core/enums`, `core/repositories`, `core/users`, `core/subjects`, `core/creators`, `core/classifications`, `core/accessions`, `core/digitalcontent`, `core/digitalfiles`, `core/digitalfileblob`, `core/collections` and `core/content`). These two scripts check that two Archon installs give the migration exactly the same data:

* `harvest.php` makes the requests that `ArchonClient.java` makes, in the same way, and saves every response. That covers the login, the `session` header, cookies, `batch_start` paging until a body is not JSON, the content of every collection, and every digital file (only its size and SHA-256 are saved).
* `compare.php` compares two harvests record by record and field by field. It puts every difference into a class and prints a summary and examples. It can also write a full JSON report.

Both run on PHP 5.4 and later and need the curl extension. Neither changes any data. Logging in adds a session row, and every request updates that row's expiry time, exactly as a migration run does.

## Comparing production with PHP 8.2 on the same data

The comparison is only meaningful when both sides hold the same data. Take the `.bak`, then harvest production straight away, ideally when nobody is editing. A record edited between the backup and the harvest shows up as a difference on that record only.

1. **Harvest production** from any machine with PHP and curl, for example the Rocky host. The production server (PHP 5.4) also works. Use the administrator account the migration uses:

   ```sh
   ARCHON_PASSWORD='…' php tests/migration-api/harvest.php https://<production-host>/<path>/index.php <admin-login> /tmp/harvest/archives-prod
   ```

2. **Restore the same `.bak` into the test environment.** Copy it to `tests/testenv/backups/archives.bak`, then:

   ```sh
   sudo podman-compose exec php archon-testenv reset archives
   sudo podman-compose exec php archon-testenv status
   sudo podman-compose exec php archon-testenv credentials
   ```

   Wait until `status` shows the site as ready. `credentials` prints the `testadmin` password; the test environment adds that administrator to every restored database.

3. **Harvest the test environment.** Use the URL that serves the site in a browser:

   ```sh
   ARCHON_PASSWORD='<testadmin password>' php tests/migration-api/harvest.php https://<rocky-host>/archives/index.php testadmin /tmp/harvest/archives-php82
   ```

   Add `--insecure` if that host's certificate is self-signed. If the host has no PHP, run the script in the container from the image's copy of the repository. Point it at the host's Apache, and copy the result out:

   ```sh
   sudo podman-compose exec -e ARCHON_PASSWORD='…' php php /opt/archon/src/tests/migration-api/harvest.php http://host.containers.internal/archives/index.php testadmin /tmp/archives-php82
   sudo podman cp archon-testenv_php_1:/tmp/archives-php82 /tmp/harvest/
   ```

   The image holds the files that were there when it was built. After pulling a new version of these scripts, run `sudo podman-compose build` again.

4. **Compare**, with production first:

   ```sh
   php tests/migration-api/compare.php --limit=10 --json=/tmp/harvest/archives-report.json /tmp/harvest/archives-prod /tmp/harvest/archives-php82
   ```

Repeat for `ihlc`, `rbml` and `ala`. To skip parts of the harvest, `--no-blobs` leaves out the digital files (these are slow on large installs) and `--no-content` leaves out the per-collection content.

## Reading the result

| Class | Meaning |
| --- | --- |
| `status` | A page or file answered with a different HTTP status. The migration tool treats anything other than 200 as "no data", so a 500 loses that whole list or file. |
| `paging` | A list stopped at a different batch, or with a different message. |
| `record-only-in-A` / `-B` | The record is on one side only. `users` always shows `testadmin` on the test side. |
| `field-only-in-A` / `-B` | The field is on one side only. |
| `null-in-A` / `-B` | The field is null on one side and has a value on the other. On PHP 5.4, `json_encode()` writes `null` for any string that is not valid UTF-8, so this is the usual sign of text that the old stack could not deliver. |
| `decimal-zero` | `0.50` on one side and `.50` on the other. |
| `double-encoded` | One side holds the other's UTF-8 bytes read as Windows-1252 (`Café` against `CafÃ©`). |
| `question-marks` | The same text, but with `?` on one side in place of characters outside ASCII. |
| `type`, `value` | Same value with a different JSON type; any other change. |
| `file` | A digital file differs. The summary shows each side's size and last bytes. |

Every file that `core/digitalfileblob` returns ends with an extra `1`, on both PHP versions. The template runs `echo print …`, which echoes the return value of `print` after the file.

If production leaves `DatabaseEncodingUTF8` out of `config.inc.php`, expect `type` differences on every number and `null-in-B` on every NULL column. Production then sends all values as strings and NULL as `""`.

The differences found so far, and what they mean for the migration, are in `tests/KNOWN-ISSUES.md` §7.
