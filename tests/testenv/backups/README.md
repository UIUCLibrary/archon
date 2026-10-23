Put full SQL Server backups of the production databases here: `archives.bak`, `ihlc.bak`, `rbml.bak`, `ala.bak`.

* A site with a backup is restored from it on first start, or after `archon-testenv reset <site>`.
* A site without a backup gets synthetic sample data.
* Nothing in this directory except this README is committed.
