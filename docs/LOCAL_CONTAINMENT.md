# Local Docker containment

The lab is intentionally exploitable. This local policy reduces the blast radius
on a development machine; it does not make the application safe to expose.

## Boundary

The policy dynamically discovers the Compose-labelled `frontend` and `backend`
bridges on every relevant container/network event. It never uses a container IP,
subnet, or a fixed bridge name. It installs only tagged rules:

- `INPUT` drops traffic from either lab bridge to the host.
- `DOCKER-USER` permits traffic that stays on either lab bridge, then drops new
  flows from either bridge to other Docker projects, LAN, or Internet.

The persistent unit runs a Docker event watcher, so a Compose bridge recreation
is rediscovered and rules are rebuilt rather than left as stale one-shot rules.

## Start and apply

Recreate only this project if it existed before containment:

```bash
docker compose down
docker compose up -d --build
sudo -n true
bash scripts/local-containment-firewall.sh apply
bash scripts/local-containment-firewall.sh verify
```

State-changing operations reject interactive sudo. Do not run broad Docker prune
commands or alter rules belonging to another project.

## Persistence and removal

```bash
bash scripts/local-containment-firewall.sh install
```

For a temporary rule rollback, use `remove-rules`. To remove the unit and only
its tagged policy, use `remove`:

```bash
bash scripts/local-containment-firewall.sh remove-rules
bash scripts/local-containment-firewall.sh remove
```

## Safe validation

Use only project-local, non-destructive checks. Do not exploit the app or probe
LAN, Internet, or other Docker workloads.

```bash
docker exec owasp-nginx-2025 wget -qO- http://web/ >/dev/null
docker exec owasp-web-2025 php -r 'new PDO("mysql:host=db;dbname=" . getenv("NEXO_DB_NAME"), getenv("NEXO_DB_USER"), getenv("NEXO_DB_PASS")); echo "db-ok\n";'
curl --fail --silent --show-error http://127.0.0.1:8082/ >/dev/null
```
