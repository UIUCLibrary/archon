# Archon test environment (Rocky Linux, Podman, host Apache httpd)

This directory holds a single-command test environment for the PHP 8.2 port of Archon:

* **php**: a PHP 8.2 PHP-FPM container with the Archon code. One site per production install: `archives`, `ihlc`, `rbml`, `ala`. FastCGI is published on `127.0.0.1:9000`.
* **mssql**: a SQL Server 2022 (Developer) container with one database per site (`archon_<site>`).
* **Apache httpd**: runs on the Rocky host, not in a container. `httpd/archon-testenv.conf` forwards `/<site>/` to PHP-FPM.

On first start, each site restores `backups/<site>.bak` if that file exists. Otherwise the site is installed through Archon's own web installer and filled with synthetic sample data (`tests/sample-data`), shaped after the live UIUC instances (`tests/research/live-survey/summary.md`).

## Setup on Rocky Linux 9

1. Install Podman and podman-compose. podman-compose comes from EPEL.
   ```
   sudo dnf install -y podman epel-release && sudo dnf install -y podman-compose
   ```
2. Start the environment. The first run builds the PHP image and installs the sites, which takes several minutes.
   ```
   cd tests/testenv && podman-compose up -d
   ```
   You can also use `podman compose up -d`, which calls podman-compose. If docker-compose is installed, `podman compose` uses it instead, and that requires `systemctl --user enable --now podman.socket`. `docker compose up -d` works as well.
3. Configure the host Apache httpd. Rocky loads mod_proxy_fcgi by default.
   ```
   sudo cp httpd/archon-testenv.conf /etc/httpd/conf.d/
   sudo apachectl configtest && sudo systemctl reload httpd
   ```
   SELinux: port 9000 is labelled `http_port_t` in the targeted policy, so httpd may already connect to it. If the connection is denied, check with `sudo semanage port -l | grep 9000`. If 9000 is missing, either label it with `sudo semanage port -a -t http_port_t -p tcp 9000` or run `sudo setsebool -P httpd_can_network_connect 1`.
4. Open `http://<host>/archon-testenv/` for the list of sites, then `http://<host>/<site>/` for each site. The admin interface is at `/<site>/admin/`.

## Daily use

All commands run in `tests/testenv`. With Docker, replace `podman-compose` with `docker compose`.

| Task | Command |
|---|---|
| Follow initialisation | `podman-compose logs -f php` |
| Readiness and record counts | `podman-compose exec php archon-testenv status` |
| Staff login and password (`testadmin`; `teststaff`, `testreader` and `testresearcher` share the password) | `podman-compose exec php archon-testenv credentials` |
| Request every page and report PHP diagnostics | `podman-compose exec php archon-testenv crawl ihlc` |
| Summary of PHP warnings and deprecations | `podman-compose exec php archon-testenv diagnostics` |
| Rebuild one site (backup or sample data again) | `podman-compose exec php archon-testenv reset ihlc` |
| After `git pull` | `podman-compose up -d --build` |
| Start over (deletes all databases) | `podman-compose down -v` |

Settings are read from an optional `.env` file; see `env.example`. Use `ARCHON_SAMPLE_SIZE=small` for a fast start, or `large` for volumes close to the live sites. If `.env` changes the site list or the FastCGI port, `httpd/archon-testenv.conf` has to change with it.

## Restoring production backups

Put full SQL Server backups in `backups/` as `archives.bak`, `ihlc.bak`, `rbml.bak` and `ala.bak`, then run `podman-compose up -d`. A site that is already initialised from sample data picks up a new backup after `archon-testenv reset <site>`.

* SQL Server 2022 restores backups taken on SQL Server 2008 or later.
* The files are copied to a staging volume before the restore, so they do not need to be world-readable.
* They are never committed: `backups/.gitignore` ignores everything except itself and this README.

## Notes and limits

* **Rootless Podman:** run `loginctl enable-linger $USER` so that the containers survive logout. The `restart:` policy does not restart containers after a reboot under Podman. Run `podman-compose up -d` again after a reboot, or wrap it in a systemd unit.
* **FastCGI security:** FastCGI executes any PHP file it is asked to run. Keep `ARCHON_FPM_BIND=127.0.0.1` and never open port 9000 in firewalld.
* **Container health:** `podman ps` health status needs a systemd user session. `archon-testenv status` is the authoritative readiness check.
* **SQL Server resources:** SQL Server needs about 2 GB of RAM (`MSSQL_MEMORY_LIMIT_MB`).
