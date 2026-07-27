# Production containment runbook

This runbook preserves the intentionally vulnerable lessons while containing the
host impact of a web compromise. Follow the order exactly: it creates stopped
containers, installs a fail-closed firewall generation, and only then starts web.

## Preconditions — deployment is blocked without these

| Gate | Check | Why it matters |
|---|---|---|
| Docker Engine | `docker version --format '{{.Server.Version}}'` is **28+** | Older engines may expose loopback-published ports to the local L2 network. |
| Cgroups | `docker info --format '{{.CgroupDriver}}'` is `systemd` | Live `memory.swap.max=0`, not Compose metadata, is the swap control. |
| DB storage | A dedicated mounted filesystem, not `/`, no larger than the approved cap | Its capacity is the database data bound. |
| I/O device | The configured block device has the same `MAJ:MIN` as that mount | `io.max` must constrain the device actually storing MariaDB. |
| TLS | A valid certificate exists for `nexolab.guajilodev.com` | Host nginx is the only public endpoint. |

Do not deploy if any gate fails. The disposable local test cannot prove these
host facts; they are verified after installation by `nexolab-runtime-verify`.

## Install host artifacts

From the reviewed checkout at `/opt/owasp2025`:

```bash
install -m 0750 deploy/production/nexolab-firewall /usr/local/sbin/nexolab-firewall
install -m 0750 deploy/production/nexolab-reset /usr/local/sbin/nexolab-reset
install -m 0750 deploy/production/nexolab-storage /usr/local/sbin/nexolab-storage
install -m 0750 deploy/production/nexolab-runtime-verify /usr/local/sbin/nexolab-runtime-verify
install -m 0750 deploy/production/nexolab-start /usr/local/sbin/nexolab-start
install -m 0750 deploy/production/nexolab-backup /usr/local/sbin/nexolab-backup
install -m 0750 deploy/production/nexolab-rollback /usr/local/sbin/nexolab-rollback
install -m 0750 deploy/production/nexolab-report-failure /usr/local/sbin/nexolab-report-failure
install -m 0644 deploy/production/nexolab-firewall.service /etc/systemd/system/nexolab-firewall.service
install -m 0644 deploy/production/nexolab-start.service /etc/systemd/system/nexolab-start.service
install -m 0644 deploy/production/nexolab-containment-failure@.service /etc/systemd/system/nexolab-containment-failure@.service
install -m 0644 deploy/production/nexolab.slice /etc/systemd/system/nexolab.slice
install -m 0644 deploy/production/nexolab-web.slice /etc/systemd/system/nexolab-web.slice
install -m 0644 deploy/production/nexolab-db.slice /etc/systemd/system/nexolab-db.slice
install -m 0644 deploy/production/nexolab-reset.cron /etc/cron.d/nexolab-reset
install -m 0644 deploy/production/nexolab-reset.logrotate /etc/logrotate.d/nexolab-reset
install -m 0644 deploy/production/nexolab-containment.logrotate /etc/logrotate.d/nexolab-containment
install -m 0644 deploy/production/nexolab.nginx /etc/nginx/sites-available/nexolab.guajilodev.com
ln -sfn /etc/nginx/sites-available/nexolab.guajilodev.com /etc/nginx/sites-enabled/nexolab.guajilodev.com
systemctl daemon-reload
nginx -t
systemctl reload nginx
```

The nginx artifact proxies **only** to `127.0.0.1:8082`; it never addresses a
Docker service name or bridge address. Do not install it until the referenced
Let's Encrypt certificate exists.

## Secrets: root reads a separate, non-executable file

Create `/opt/owasp2025/.env` with mode `0600`, owned by root, for Compose. It
must contain `NEXOLAB_DB_STORAGE_PATH` and the environment values required by
the containers. Root automation **does not source it**.

Create `/etc/nexolab/secrets.env` as root-owned mode `0600`. It has exactly
these four unquoted `KEY=value` records and no shell syntax, comments are
allowed:

```text
NEXO_DB_NAME=...
NEXO_DB_USER=...
NEXO_DB_PASS=...
NEXO_DB_ROOT_PASS=...
```

`nexolab-reset`, backup, and rollback parse only those names, reject symlinks,
reject non-root ownership, and reject group/other access. Never copy secret
values into this repository or a rollback snapshot.

## Provision storage and start safely

First create and mount a small dedicated filesystem (for example
`/srv/nexolab-db`). Select its **actual backing block device**, not the disk
that merely contains an unrelated mount:

```bash
/usr/local/sbin/nexolab-storage configure /srv/nexolab-db /dev/REPLACE_ME 2048 20
/usr/local/sbin/nexolab-storage verify
systemctl enable nexolab-firewall.service nexolab-start.service
systemctl start nexolab-firewall.service
systemctl start nexolab-start.service
/usr/local/sbin/nexolab-firewall verify
/usr/local/sbin/nexolab-runtime-verify
/usr/local/sbin/nexolab-reset verify
```

Both vulnerable services use `restart: "no"`: Docker therefore cannot start them
from its restart-policy queue after a reboot. `nexolab-start.service` runs only
after Docker and the firewall watcher; it creates stopped containers with
`--no-start`, applies and verifies a complete firewall generation, then starts
containers. Never replace the gate with a direct `docker compose up`. The
watcher retains the prior generation if a replacement fails; its systemd unit
has no stop-time rule removal.

The firewall allows only host loopback → web TCP/80 and web → DB TCP/3306.
It drops bridge egress and direct traffic headed toward either lab bridge. This
is an independent defense in addition to Engine 28's loopback-publish fix.

## Validate and observe failures

Safe local artifact validation:

```bash
scripts/validate-production-containment.sh
```

This is disposable: it renders Compose, validates topology as structured JSON,
verifies a real temporary checksum manifest, and exercises firewall generation
with mocked Docker/iptables commands including a failed installation contract.
It intentionally does **not** activate systemd, alter real rules, start
containers, access a production DB, inspect a production mount, or test TLS.

On the host, failures persist in `/var/log/nexolab-containment-failures.log`
and the journal. Check them with:

```bash
systemctl status nexolab-firewall.service
journalctl -u nexolab-firewall.service -u 'nexolab-containment-failure@*' --since -1h
tail -n 100 /var/log/nexolab-containment-failures.log
tail -n 100 /var/log/nexolab-reset.log
```

Cron reports reset and certificate failures to the same persistent containment
log. Storage and runtime verification failures do likewise when their reporter
artifact is installed.

## Reset, snapshot, and rollback

`nexolab-reset` imports into a random staging schema and verifies it **before**
it stops web. It then rebuilds the live schema and moves in the verified seed
tables. A bad import, timeout, or attacker-held application lock therefore
leaves the old database intact. Before replacement,
the reset writes a gzip-verified, root-only SQL archive under
`/var/lib/nexolab-reset-recovery` (directory mode `0700`, archive mode `0600`).
This directory is not mounted into either container and the application database
account has no host filesystem access to it.

If any operation after archive creation fails, the EXIT handler removes and
verifies every temporary MariaDB staging schema, preserves that external archive,
returns nonzero, and keeps web stopped. It never restores the prior, potentially
attacker-controlled schema into live MariaDB or restarts web automatically.
Before a normal web restart, reset recreates and validates the stopped web
container's writable tmpfs paths; it then validates the web/database runtime and
only after those checks deletes the archive. Docker control commands receive TERM
and then KILL escalation, and the bounded reset worker runs in a dedicated process
group that receives the same escalation; a timeout fails closed and requires
verified web containment rather than allowing a Docker command to outlive reset.
The error report records the exact archive path. Only a root operator may inspect or restore it after incident
review; keep web stopped and re-run the normal start and runtime-containment gates
before deliberately exposing any recovered state.

Before an approved change, save a snapshot on non-root backup storage:

```bash
/usr/local/sbin/nexolab-backup create /mnt/approved-backups/nexolab-YYYYMMDD
/usr/local/sbin/nexolab-rollback /mnt/approved-backups/nexolab-YYYYMMDD check
```

The snapshot checksums its DB archive, host artifacts, a full application
release archive, Git revision marker, and exact running web image. Rollback
loads and tags that saved image, restores the release paths rather than
overlaying them, restores DB/configuration, and verifies containment before
reloading nginx:

```bash
/usr/local/sbin/nexolab-rollback /mnt/approved-backups/nexolab-YYYYMMDD rollback
```

Rollback refuses to start without an already verified firewall generation and
never disables or removes containment. It never runs `compose down`: it creates
stopped DB/web containers as needed, synchronously rebinds and verifies the
current bridge policy, and only then starts each service. On a failure, web is
restarted only after that same synchronous rebind. Investigate the persistent
failure log before retrying.
