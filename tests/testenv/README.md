# Archon test environment (Rocky Linux, Podman, host Apache httpd)

This directory holds a single-command test environment for the PHP 8.2 port of Archon:

* **php**: a PHP 8.2 PHP-FPM container with the Archon code. One site per production install: `archives`, `ihlc`, `rbml`, `ala`. FastCGI is published on `127.0.0.1:9000`.
* **mssql**: a SQL Server 2022 (Developer) container with one database per site (`archon_<site>`).
* **Apache httpd**: runs on the Rocky host, not in a container. `httpd/archon-testenv.conf` forwards `/<site>/` to PHP-FPM.

For Docker, or behind the Traefik reverse proxy with Apache inside the container, use the Compose files at the repository root instead (see [README.docker.md](../../README.docker.md)).

On first start, each site restores `backups/<site>.bak` if that file exists. Otherwise the site is installed through Archon's own web installer and filled with synthetic sample data (`tests/sample-data`), shaped after the live UIUC instances (`tests/research/live-survey/summary.md`).

## Setup on Rocky Linux 8 or 9

1. Install Podman and podman-compose. podman-compose comes from EPEL.
   ```
   sudo dnf install -y podman epel-release && sudo dnf install -y podman-compose
   ```
2. Decide who runs the containers.
   * **Rootful (recommended for a shared test server):** run every `podman-compose` and `podman` command in this README with `sudo` (for example `sudo podman-compose up -d`). This needs no per-user setup. To bring the environment back after a reboot, run `sudo systemctl enable podman-restart.service` once.
   * **Rootless (your own account):** Podman needs a range of subordinate user and group IDs for your account. Check with `grep "^$USER:" /etc/subuid /etc/subgid`. If nothing is printed, see [Rootless prerequisites](#rootless-prerequisites) first. To keep the containers running after you log out, and to bring them back after a reboot, run `loginctl enable-linger $USER` and `systemctl --user enable podman-restart.service` once.
3. Build and start the environment. The first run builds the PHP image and installs the sites, which takes several minutes.
   ```
   cd tests/testenv
   podman-compose build              # must end with "Successfully tagged localhost/archon-testenv/php:8.2-fpm"
   podman-compose up -d
   podman-compose logs -f php        # until the "Archon test environment (PHP 8.2...) is ready" banner
   ```
   podman-compose 1.0.6 exits with status 0 even when the build fails, and `up` carries on regardless. Check the end of the build output before you run `up`.
   You can also use `podman compose up -d`, which calls podman-compose. If docker-compose is installed, `podman compose` uses it instead, and that requires the Podman socket (`systemctl --user enable --now podman.socket`, or `sudo systemctl enable --now podman.socket` for rootful). On a Docker host, `docker compose up -d` works as well.
4. Configure the host Apache httpd. Rocky loads mod_proxy_fcgi by default.
   ```
   sudo cp httpd/archon-testenv.conf /etc/httpd/conf.d/
   sudo apachectl configtest && sudo systemctl reload httpd
   ```
   This file replaces the usual `Alias /app /var/www/app` and `<Directory>` pair, so do not add one for this checkout. Each site's code and its own `config.inc.php` exist only inside the php container (`/var/www/html/<site>`), so Apache forwards every request under `/archives/`, `/ihlc/`, `/rbml/`, `/ala/` and `/archon-testenv/` to the container's PHP-FPM on `127.0.0.1:9000`. That includes images and style sheets. The file applies to every virtual host, including the HTTPS one, so the sites appear as `https://<host>/ihlc/` and so on. These paths take precedence over an `Alias` with the same path. The `SetHandler` for `.php` files in Rocky's `php.conf` does not apply to forwarded requests.

   SELinux: port 9000 is labelled `http_port_t`. httpd may connect to `http_port_t` while `httpd_graceful_shutdown` (on by default) or `httpd_can_network_relay` is on. Check with `getsebool httpd_graceful_shutdown httpd_can_network_relay`. If both are off, run `sudo setsebool -P httpd_can_network_relay 1`. If you move FastCGI to another port (`ARCHON_FPM_PORT`), either label that port with `sudo semanage port -a -t http_port_t -p tcp <port>` or use `httpd_can_network_connect`. `sudo ausearch -m avc -ts recent` shows any denials.
5. Open `http://<host>/archon-testenv/` for the list of sites, then `http://<host>/<site>/` for each site. The admin interface is at `/<site>/admin/`.

### Rootless prerequisites

Rootless Podman maps container users to a range of subordinate IDs listed in `/etc/subuid` and `/etc/subgid`. `useradd` adds a range for local accounts, but not for network (LDAP or Active Directory) accounts. Without a range, Podman falls back to a single ID mapping, and every image that contains files owned by other users fails to unpack (see [Troubleshooting](#troubleshooting)).

1. An administrator adds a range that does not overlap the ranges already in the files. `usermod --add-subuids` only works for accounts in `/etc/passwd`, so for network accounts append the lines directly:
   ```
   sudo sh -c 'echo "<user>:200000:65536" >> /etc/subuid; echo "<user>:200000:65536" >> /etc/subgid'
   ```
2. Apply the new range, then remove anything left over from the failed attempts. `podman system reset` deletes all of your rootless containers, images and volumes.
   ```
   podman system migrate
   podman system reset
   ```
3. Rootless image storage does not work on NFS. If your home directory is on NFS, set `graphroot` in `~/.config/containers/storage.conf` to a directory on local disk, or use rootful Podman.

## Daily use

All commands run in `tests/testenv`. With Docker, replace `podman-compose` with `docker compose`.

| Task | Command |
|---|---|
| Follow initialisation | `podman-compose logs -f php` |
| Readiness and record counts | `podman-compose exec php archon-testenv status` |
| Staff login and password (`testadmin`; `teststaff`, `testreader` and `testresearcher` share the password) | `podman-compose exec php archon-testenv credentials` |
| Request every page and report PHP diagnostics | `podman-compose exec php archon-testenv crawl ihlc` |
| Create, edit and delete records in every admin module (test copies only; report in `/var/log/archon/crud-SITE.json`) | `podman-compose exec php archon-testenv crud archives` (add `--only=collections` to limit, `--keep` to leave the records) |
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

* **After a reboot:** Podman has no daemon, so the containers come back only through `podman-restart.service` (see step 2 of the setup). Otherwise run `podman-compose up -d` again.
* **Repository location:** the checkout holds `.env` (database and staff passwords) and `backups/`. Keep it outside every directory that Apache serves.
* **FastCGI security:** FastCGI executes any PHP file it is asked to run. Keep `ARCHON_FPM_BIND=127.0.0.1` and never open port 9000 in firewalld.
* **Container health:** `podman ps` health status needs a systemd user session. `archon-testenv status` is the authoritative readiness check.
* **SQL Server resources:** SQL Server needs about 2 GB of RAM (`MSSQL_MEMORY_LIMIT_MB`).

## Troubleshooting

| Message | Cause and fix |
|---|---|
| `cannot find UID/GID for user ...: no subuid ranges found` and `potentially insufficient UIDs or GIDs available in user namespace (requested 0:42 for /etc/gshadow) ... lchown /etc/gshadow: invalid argument` | Rootless Podman without subordinate IDs, so neither image can be built or pulled. Use rootful Podman (`sudo podman-compose ...`) or follow [Rootless prerequisites](#rootless-prerequisites). |
| `localhost/archon-testenv/php:8.2-fpm: image not known`, or `pinging container registry localhost` | The PHP image was not built; the real error is further up in the build output. Run `podman-compose build` on its own. (`pinging container registry localhost` comes from older copies of `compose.yaml` without `pull_policy: never`.) |
| `mssql: waiting for the php container to create /run/archon-secrets/mssql-sa`, repeated | The php container is not running. Check `podman-compose ps` and `podman-compose logs php`. |
| `podman ps` or `podman-compose ps` lists no containers, although `up -d` succeeded | Containers started with `sudo` belong to root, and plain `podman` (rootless) only lists your own containers and images. Use `sudo podman ps`, and use the same mode for every command. |
| `still waiting for SQL Server ... cannot resolve 'mssql' inside the php container` | Container DNS is missing. `sudo podman info --format '{{.Host.NetworkBackend}}'` prints the network backend. For `netavark`, install `aardvark-dns`; for `cni` (Rocky 8 default), install `podman-plugins`. Then recreate the network: `sudo podman-compose down`, `sudo podman network rm archon-testenv_default`, `sudo podman-compose up -d`. After `firewall-cmd --reload`, run `sudo podman network reload --all`, because the reload removes Podman's firewall rules. |
| `still waiting for SQL Server` in `podman-compose logs php` (other errors) | SQL Server is not up. Check `podman-compose logs mssql`; SQL Server needs about 2 GB of free RAM. |
| `503 Service Unavailable` from Apache | PHP-FPM starts only after initialisation has finished, so a 503 is normal until the "is ready" banner appears in `podman-compose logs php`. After that, PHP-FPM is not reachable on 127.0.0.1:9000: check `podman-compose ps`, then the SELinux note in step 4 of the setup. |
