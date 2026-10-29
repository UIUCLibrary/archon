# Archon in containers (Docker, Traefik)

Two Compose files at the repository root run the PHP 8.2 port of Archon as two containers:

* **php**: PHP 8.2 with Apache httpd, built from `tests/docker/php/Dockerfile`. It holds one Archon site per production install: `archives`, `ihlc`, `rbml` and `ala`.
* **mssql**: SQL Server 2022 (Developer edition), with one database per site (`archon_<site>`).

On first start, each site restores `tests/testenv/backups/<site>.bak` if that file exists. Otherwise the site is installed through Archon's own installer and filled with synthetic sample data shaped after the live UIUC instances.

| File | Use |
|---|---|
| `docker-compose-traefik.yml` | Behind the shared Traefik reverse proxy: HTTPS, Let's Encrypt, campus addresses only. No port is published. |
| `docker-compose.yml` | Stand-alone. Publishes `http://localhost:8080/`. |
| `tests/testenv/compose.yaml` | Rocky Linux with Podman, where the host's own Apache httpd serves the sites (PHP-FPM). See [tests/testenv/README.md](tests/testenv/README.md). |

All three share the image, the start-up scripts and the sample data. This is a test environment. The open problems are listed in [tests/KNOWN-ISSUES.md](tests/KNOWN-ISSUES.md).

## Behind Traefik

Requirements:
* Docker with Compose v2.
* The Traefik stack running on the same host, with its external Docker network (`proxy`).
* A DNS name for the sites that points at the Traefik host.
* About 3 GB of free RAM (SQL Server needs about 2 GB) and 5 GB of disk.

1. Clone this repository on the Docker host, next to the other Traefik applications, and check out the PHP 8.2 branch.
2. Create `.env` next to the Compose files. `docker.env.example` lists every setting; only the host name is required.
   ```
   cp docker.env.example .env
   echo "ARCHON_FQDN=archon-test.library.illinois.edu" >> .env
   echo "COMPOSE_FILE=docker-compose-traefik.yml" >> .env     # optional: lets you drop "-f docker-compose-traefik.yml"
   ```
3. Build and start.
   ```
   docker compose -f docker-compose-traefik.yml up -d --build
   docker compose -f docker-compose-traefik.yml logs -f php    # until the "Archon test environment (PHP 8.2...) is ready" banner
   ```
   The first start installs four sites: a few minutes with `ARCHON_SAMPLE_SIZE=small`, longer with `standard`, `large` or big backups. Traefik routes to the container only once it is healthy, so until the banner appears Traefik answers `404`.
4. Open `https://<ARCHON_FQDN>/` for the list of sites, then `/archives/`, `/ihlc/`, `/rbml/` and `/ala/`. The staff interface of each site is at `/<site>/?p=admin`.
5. Get the staff login:
   ```
   docker compose -f docker-compose-traefik.yml exec php archon-testenv credentials
   ```

### Traefik settings

The labels follow the other UIUC Library applications (for example `UIUCLibrary/getmarcapi`). If the Traefik stack uses other names, set the matching variable in `.env`.

| Label | Variable | Default |
|---|---|---|
| `traefik.docker.network`, external network | `ARCHON_TRAEFIK_NETWORK` | `proxy` |
| `routers.<name>-secure.entrypoints` | `ARCHON_TRAEFIK_ENTRYPOINT` | `websecure` |
| `routers.<name>-secure.tls.certresolver` | `ARCHON_TRAEFIK_CERTRESOLVER` | `letsencrypt` |
| `routers.<name>-secure.middlewares` (comma separated) | `ARCHON_TRAEFIK_MIDDLEWARES` | `campus-ips-whitelist@file` |
| `routers.<name>-secure.rule`, `tls.domains[0].main` | `ARCHON_FQDN` | (required) |
| Router and service name | `ARCHON_TRAEFIK_ROUTER` | `archon` |

To run a second copy on the same host (another branch, for example), clone it into another directory and set `COMPOSE_PROJECT_NAME`, `ARCHON_TRAEFIK_ROUTER` and `ARCHON_FQDN` to new values in its `.env`.

### How requests reach Archon

Traefik terminates TLS and forwards plain HTTP to Apache on port 80 of the php container. It adds `X-Forwarded-For` and `X-Forwarded-Proto` headers. At start-up the container writes Apache settings from `ARCHON_TRUSTED_PROXIES` (default: the private address ranges Docker networks use):

* **Client address:** the address in `X-Forwarded-For` becomes the client address. This is what Archon's session address check, its admin IP restriction (IPv4 only) and the access log see.
* **HTTPS:** a request that Traefik received over HTTPS gets `HTTPS=on`. Archon then sets the `secure` flag on its session cookie, and its "SecureConnection" and "ForceHTTPS" settings work without redirect loops.
* **Redirects:** Apache's own redirects (`/archives` to `/archives/`) keep `https://`.

Requests from other addresses cannot set these headers. With `docker-compose.yml`, `ARCHON_TRUSTED_PROXIES` is empty, so both headers are always ignored.

## Without Traefik

```
docker compose up -d --build
docker compose logs -f php        # until the "is ready" banner
```

The sites are at `http://localhost:8080/`. The port is bound to 127.0.0.1 because the sample sites have a known staff login. To reach them from other machines, set `ARCHON_HTTP_BIND=0.0.0.0` and `ARCHON_PUBLIC_URL=http://<host>:8080` in `.env`. This setup has no TLS, so do not turn on Archon's "Force HTTPS" setting.

If you put another reverse proxy in front of the published port, set `ARCHON_TRUSTED_PROXIES` to that proxy's address.

## Daily use

The commands are shown for `docker-compose-traefik.yml`. For `docker-compose.yml`, or with `COMPOSE_FILE` in `.env`, leave out `-f docker-compose-traefik.yml`.

| Task | Command |
|---|---|
| Follow initialisation; Apache access and error log | `docker compose -f docker-compose-traefik.yml logs -f php` |
| Readiness and record counts | `docker compose -f docker-compose-traefik.yml exec php archon-testenv status` |
| Staff login and password (`testadmin`; `teststaff`, `testreader` and `testresearcher` share the password) | `docker compose -f docker-compose-traefik.yml exec php archon-testenv credentials` |
| PHP error log | `docker compose -f docker-compose-traefik.yml exec php tail -f /var/log/archon/php_errors.log` |
| Request every page and report PHP diagnostics | `docker compose -f docker-compose-traefik.yml exec php archon-testenv crawl ihlc` |
| Create, edit and delete records in every admin module (**writes to the database**: only on a test copy nobody is editing; report in `/var/log/archon/crud-SITE.json`) | `docker compose -f docker-compose-traefik.yml exec php archon-testenv crud archives` (add `--only=collections` to limit, `--keep` to leave the records) |
| Summary of PHP warnings and deprecations | `docker compose -f docker-compose-traefik.yml exec php archon-testenv diagnostics` |
| Rebuild one site (backup or sample data again) | `docker compose -f docker-compose-traefik.yml exec php archon-testenv reset ihlc` |
| After `git pull` (the code is part of the image) | `docker compose -f docker-compose-traefik.yml up -d --build` |
| Stop, keeping the databases | `docker compose -f docker-compose-traefik.yml down` |
| Start over (deletes all databases and generated passwords) | `docker compose -f docker-compose-traefik.yml down -v` |

The containers restart with Docker (`restart: unless-stopped`). On restart, the code from the image is copied into the sites again, while the databases are kept.

## Restoring production backups

Put full SQL Server backups in `tests/testenv/backups/` (or the directory in `ARCHON_BACKUPS_DIR`) as `archives.bak`, `ihlc.bak`, `rbml.bak` and `ala.bak`, then start the containers. A site that was already initialised from sample data picks up a new backup after `archon-testenv reset <site>`.

* SQL Server 2022 restores backups taken on SQL Server 2008 or later.
* A backup whose Archon tables are not in `dbo` (the ala backup) works: after each restore and on every start, Archon's database user gets the schema that holds the tables as its default schema, as production's user has (`tests/KNOWN-ISSUES.md`, 2.6).
* Backups contain real staff and researcher accounts. Keep the campus-only middleware, and keep the backups directory readable only by the administrators of the Docker host.
* `.bak` files are never committed: `tests/testenv/backups/.gitignore` ignores them. `.env` is ignored by git, and neither is copied into the image.

## Security notes

* **Test administrator:** `testadmin` is created or reset in every site on each start, and anyone who can reach the site can try its password. Set `ARCHON_ADMIN_PASSWORD` in `.env`, keep `ARCHON_TRAEFIK_MIDDLEWARES` restricted to campus, and do not use this as a public production server.
* **Trusted proxies:** any container on the `proxy` network can claim another client address or HTTPS. If containers other than Traefik share that network, narrow `ARCHON_TRUSTED_PROXIES` to Traefik's address, or to the network's subnet (`docker network inspect proxy -f '{{range .IPAM.Config}}{{.Subnet}} {{end}}'`).
* **Files that are not served:** include files (`*.inc.php`, which covers `config.inc.php`), dotfiles, `*.bak`/`*.sql` and the `_dev/` developer scripts are not served.
* **E-mail:** no mail relay is configured, so `mail()` fails. This affects account activation, the e-mail form and appointment notifications (see `tests/KNOWN-ISSUES.md`).

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| `network proxy declared as external, but could not be found` | The Traefik network has another name. `docker network ls` lists the networks; set `ARCHON_TRAEFIK_NETWORK`. |
| Traefik answers `404 page not found` | The php container is not healthy yet: `docker compose -f docker-compose-traefik.yml ps`, and wait for the "is ready" banner. Otherwise check `ARCHON_TRAEFIK_ENTRYPOINT`, and Traefik's own log for this router. |
| Traefik answers `403 Forbidden` | The campus-only middleware rejected your address. Use the campus network or VPN. |
| Browser certificate warning (`TRAEFIK DEFAULT CERT`) | The certificate resolver could not issue a certificate for `ARCHON_FQDN`. Check the DNS name, `ARCHON_TRAEFIK_CERTRESOLVER` and Traefik's log. |
| Redirect loop or "not a secure connection" after login, or the client address is Traefik's | Traefik's address is not in `ARCHON_TRUSTED_PROXIES`. Find it with `docker network inspect proxy`, then add it and run `up -d` again. |
| `ARCHON_TRUSTED_PROXIES: '...' is not an IP address or CIDR block`, and the php container keeps restarting | Use space-separated addresses or CIDR blocks, not host names. |
| `still waiting for SQL Server` in the php log | SQL Server is not up. Check `docker compose -f docker-compose-traefik.yml logs mssql`; SQL Server needs about 2 GB of free RAM. |
| `port is already allocated` (`docker-compose.yml`) | Choose another port with `ARCHON_HTTP_PORT`. |
