#!/usr/bin/env bash
# Disposable behavioral checks for production containment artifacts. No Docker
# containers, host firewall rules, production paths, or application data change.
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly PROJECT_DIR
readonly FIREWALL="${PROJECT_DIR}/deploy/production/nexolab-firewall"
temp="$(mktemp -d)"
trap 'rm -rf "$temp"' EXIT

fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
pass() { printf 'PASS: %s\n' "$*"; }

NEXO_DB_NAME=validation_db \
NEXO_DB_USER=validation_user \
NEXO_DB_PASS=validation_password \
NEXO_DB_ROOT_PASS=validation_root_password \
NEXOLAB_DB_STORAGE_PATH=/srv/nexolab-db \
docker compose -f "${PROJECT_DIR}/docker-compose.prod.yml" --project-directory "$PROJECT_DIR" config --format json >"$temp/compose.json"

python3 - "$temp/compose.json" <<'PY'
import json
import sys

config = json.load(open(sys.argv[1], encoding="utf-8"))
web = config["services"]["web"]
db = config["services"]["db"]
assert web["ports"] == [{"mode": "ingress", "host_ip": "127.0.0.1", "published": "8082", "target": 80, "protocol": "tcp"}]
assert web["restart"] == "no"
assert db["restart"] == "no"
assert db.get("ports", []) == []
assert set(web["networks"]) == {"frontend", "backend"}
assert set(db["networks"]) == {"backend"}
assert config["networks"]["frontend"]["internal"] is True
assert config["networks"]["backend"]["internal"] is True
assert db["volumes"][2]["type"] == "volume"
assert db["volumes"][2]["target"] == "/var/lib/mysql"
assert config["volumes"]["nexo_db"]["driver_opts"] == {"type": "none", "o": "bind", "device": "/srv/nexolab-db"}
assert web["image"] == "owasp2025-web:latest"
assert "/var/www/html/a02_admin/uploads:size=50m,mode=1777,noexec,nosuid" in web["tmpfs"]
assert "/var/www/html/a09_actividad/logs:size=20m,mode=1777,noexec,nosuid" in web["tmpfs"]
assert not any("a09_actividad/logs/app.log" in mount for mount in web["tmpfs"])
PY
pass 'rendered topology exposes only loopback web and keeps DB on internal networks'

# Reproduce snapshot manifest creation with a real checksum check. In particular,
# the transient output file must not checksum itself.
mkdir -p "$temp/snapshot/project"
printf 'release\n' >"$temp/snapshot/project/release"
(
  cd "$temp/snapshot"
  manifest="$(mktemp .manifest.XXXXXX)"
  find . -type f ! -name SHA256SUMS ! -name "${manifest##*/}" -print0 | sort -z | xargs -0 sha256sum >"$manifest"
  mv "$manifest" SHA256SUMS
  sha256sum --check --status SHA256SUMS
)
pass 'snapshot manifest verifies without a self-reference'

mkdir -p "$temp/bin" "$temp/state"
cat >"$temp/bin/docker" <<'SH'
#!/usr/bin/env bash
case "$1" in
  version) printf '28.0.1\n' ;;
  network)
    if [ "$2" = ls ]; then printf 'front\nback\n'; exit 0; fi
    case "$*" in
      *front*com.docker.compose.network*) printf 'frontend\n' ;;
      *back*com.docker.compose.network*) printf 'backend\n' ;;
      *front*com.docker.network.bridge.name*) printf 'br-front\n' ;;
      *back*com.docker.network.bridge.name*) printf 'br-back\n' ;;
    esac
    ;;
esac
SH
cat >"$temp/bin/ip" <<'SH'
#!/usr/bin/env bash
exit 0
SH
cat >"$temp/bin/iptables" <<'SH'
#!/usr/bin/env bash
printf '%s\n' "$*" >>"$NEXOLAB_TEST_LOG"
if [[ "${NEXOLAB_TEST_FAIL:-}" = 'docker-user-jump' && "$*" == *'-I DOCKER-USER 1'* ]]; then exit 1; fi
exit 0
SH
chmod 0755 "$temp/bin/docker" "$temp/bin/ip" "$temp/bin/iptables"
export PATH="$temp/bin:$PATH"
export NEXOLAB_TEST_LOG="$temp/iptables.log"
export NEXOLAB_FIREWALL_STATE_DIR="$temp/state"
"$FIREWALL" apply
python3 - "$temp/iptables.log" <<'PY'
import sys
log = open(sys.argv[1], encoding="utf-8").read()
assert '-i lo -o br-front -p tcp --dport 80' in log
assert '-i br-front -o br-back -p tcp --dport 3306' in log
assert '-o br-front -m conntrack --ctstate NEW -j DROP' in log
assert '-o br-back -m conntrack --ctstate NEW -j DROP' in log
PY
pass 'firewall generator permits only loopback-to-web and web-to-DB while blocking direct bridge publishes'

: >"$temp/iptables.log"
if NEXOLAB_TEST_FAIL=docker-user-jump "$FIREWALL" apply; then
  fail 'mocked firewall installation failure unexpectedly succeeded'
fi
python3 - "$temp/iptables.log" <<'PY'
import sys
log = open(sys.argv[1], encoding="utf-8").read()
assert '-I INPUT 1' in log and '-I DOCKER-USER 1' in log
assert ' -D ' not in f' {log} '
PY
pass 'firewall installation failure retains the prior restrictive generation'

readonly START="${PROJECT_DIR}/deploy/production/nexolab-start"
readonly ROLLBACK="${PROJECT_DIR}/deploy/production/nexolab-rollback"
readonly RESET="${PROJECT_DIR}/deploy/production/nexolab-reset"

# Execute the real start script in a user namespace (where it sees EUID 0) with
# Docker/firewall command mocks. The runtime command fails after Compose has
# started both services; the script itself must synchronously stop them.
mkdir -p "$temp/start/bin" "$temp/start/project"
touch "$temp/start/project/docker-compose.prod.yml"
cat >"$temp/start/bin/docker" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
last="${!#}"
case " $* " in
  *' start '*) printf 'running\n' >"$NEXOLAB_TEST_STATE/web"; printf 'running\n' >"$NEXOLAB_TEST_STATE/db" ;;
  *' stop web '*)
    [ "${NEXOLAB_TEST_COMPOSE_STOP_FAIL:-}" = web ] && exit 41
    printf 'stopped\n' >"$NEXOLAB_TEST_STATE/web"
    ;;
  *' stop db '*)
    [ "${NEXOLAB_TEST_COMPOSE_STOP_FAIL:-}" = db ] && exit 42
    printf 'stopped\n' >"$NEXOLAB_TEST_STATE/db"
    ;;
  *' inspect '*web*) [ "$(<"$NEXOLAB_TEST_STATE/web")" = stopped ] && printf 'false\n' || printf 'true\n' ;;
  *' inspect '*db*) [ "$(<"$NEXOLAB_TEST_STATE/db")" = stopped ] && printf 'false\n' || printf 'true\n' ;;
  *' stop '*web*)
    [ "${NEXOLAB_TEST_DIRECT_STOP_FAIL:-}" = web ] && exit 43
    printf 'stopped\n' >"$NEXOLAB_TEST_STATE/web"
    ;;
  *' stop '*db*)
    [ "${NEXOLAB_TEST_DIRECT_STOP_FAIL:-}" = db ] && exit 44
    printf 'stopped\n' >"$NEXOLAB_TEST_STATE/db"
    ;;
esac
if [[ " ${*} " == *' start '* && "${NEXOLAB_TEST_COMPOSE_START_FAIL:-}" = 1 ]]; then exit 37; fi
SH
cat >"$temp/start/bin/firewall" <<'SH'
#!/usr/bin/env bash
exit 0
SH
cat >"$temp/start/bin/runtime-fail" <<'SH'
#!/usr/bin/env bash
exit 38
SH
chmod 0755 "$temp/start/bin/"*
for failure_mode in runtime compose; do
  : >"$temp/start/web"; : >"$temp/start/db"
  output="$temp/start/${failure_mode}.output"
  if NEXOLAB_TEST_STATE="$temp/start" \
    NEXOLAB_TEST_COMPOSE_START_FAIL="$([ "$failure_mode" = compose ] && printf 1 || true)" \
    unshare -Ur --map-root-user env \
      PATH="$temp/start/bin:$PATH" \
      NEXOLAB_PROJECT_DIR="$temp/start/project" \
       NEXOLAB_FIREWALL_BIN="$temp/start/bin/firewall" \
       NEXOLAB_RUNTIME_VERIFY_BIN="$temp/start/bin/runtime-fail" \
       "$START" >"$output" 2>&1; then
    fail "start unexpectedly succeeded during ${failure_mode} failure"
  fi
  [ "$(<"$temp/start/web")" = stopped ] || fail "start left web running after ${failure_mode} failure"
  [ "$(<"$temp/start/db")" = stopped ] || fail "start left DB running after ${failure_mode} failure"
done
pass 'real start script synchronously stops web and DB after runtime or partial-start failure'

: >"$temp/start/web"; : >"$temp/start/db"
if NEXOLAB_TEST_STATE="$temp/start" \
  NEXOLAB_TEST_COMPOSE_STOP_FAIL=web \
  unshare -Ur --map-root-user env \
    PATH="$temp/start/bin:$PATH" \
    NEXOLAB_PROJECT_DIR="$temp/start/project" \
    NEXOLAB_FIREWALL_BIN="$temp/start/bin/firewall" \
    NEXOLAB_RUNTIME_VERIFY_BIN="$temp/start/bin/runtime-fail" \
    "$START" >"$temp/start/stop-failure.output" 2>&1; then
  fail 'start unexpectedly succeeded after injected Compose stop failure'
fi
[ "$(<"$temp/start/web")" = stopped ] || fail 'start direct fallback left web running after a Compose stop failure'
[ "$(<"$temp/start/db")" = stopped ] || fail 'start direct fallback left DB running after a Compose stop failure'
grep -Fqx 'ERROR: Compose could not stop web; attempting direct Docker stop.' "$temp/start/stop-failure.output" || \
  fail 'start suppressed the injected Compose stop failure'
pass 'real start script surfaces a stop failure, uses direct fallback, and confirms both services stopped'

# Drive the complete rollback path through the real script. Host-mutating
# commands are mocked, while its final runtime gate fails. The EXIT handler must
# leave web stopped rather than attempting an unverified recovery restart.
mkdir -p "$temp/rollback/bin" "$temp/rollback/project" "$temp/rollback/snapshot/project/mysql" "$temp/rollback/snapshot/host" "$temp/rollback/release"
for path in docker-compose.yml docker-compose.prod.yml; do
  touch "$temp/rollback/project/$path" "$temp/rollback/release/$path" "$temp/rollback/snapshot/project/$path"
done
mkdir -p "$temp/rollback/project/php" "$temp/rollback/project/src" "$temp/rollback/project/mysql" \
  "$temp/rollback/release/php" "$temp/rollback/release/src" "$temp/rollback/release/mysql"
tar -czf "$temp/rollback/snapshot/project/release.tar.gz" -C "$temp/rollback/release" .
printf 'test-revision\n' >"$temp/rollback/snapshot/project/release-revision"
printf 'sha256:%064d\n' 0 >"$temp/rollback/snapshot/project/web-image-id"
touch "$temp/rollback/snapshot/project/web-image.tar" "$temp/rollback/snapshot/project/mysql/init.sql" "$temp/rollback/snapshot/project/mysql/my.cnf"
for file in nexolab.guajilodev.com nexolab-reset nexolab-firewall nexolab-storage nexolab-runtime-verify nexolab-firewall.service nexolab-start nexolab-start.service nexolab-report-failure nexolab-containment-failure@.service nexolab-reset.cron nexolab-reset.logrotate nexolab.slice nexolab-web.slice nexolab-db.slice nexolab-storage.env nexolab-db-storage.conf nexolab-containment.logrotate; do
  : >"$temp/rollback/snapshot/host/$file"
done
printf 'SELECT 1;\n' | gzip >"$temp/rollback/snapshot/owasp_nexo_db_2025.sql.gz"
(
  cd "$temp/rollback/snapshot"
  manifest="$(mktemp .manifest.XXXXXX)"
  find . -type f ! -name SHA256SUMS ! -name "${manifest##*/}" -print0 | sort -z | xargs -0 sha256sum >"$manifest"
  mv "$manifest" SHA256SUMS
)
cat >"$temp/rollback/secrets.env" <<'EOF'
NEXO_DB_NAME=validation_db
NEXO_DB_ROOT_PASS=validation_root_password
EOF
cat >"$temp/rollback/bin/docker" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
case "$1" in
  inspect)
    case "$*" in
      *"$NEXOLAB_TEST_WEB_CONTAINER"*) [ "$(<"$NEXOLAB_TEST_STATE/web")" = stopped ] && printf 'false\n' || printf 'true\n' ;;
      *"$NEXOLAB_TEST_DB_CONTAINER"*) [ "$(<"$NEXOLAB_TEST_STATE/db")" = stopped ] && printf 'false\n' || printf 'true\n' ;;
    esac
    ;;
  exec) printf '1\n' ;;
  stop)
    case "${!#}" in "$NEXOLAB_TEST_WEB_CONTAINER") printf 'stopped\n' >"$NEXOLAB_TEST_STATE/web" ;; "$NEXOLAB_TEST_DB_CONTAINER") printf 'stopped\n' >"$NEXOLAB_TEST_STATE/db" ;; esac
    ;;
  load|tag|image) exit 0 ;;
  compose)
    case " $* " in
      *' start web '*) printf 'running\n' >"$NEXOLAB_TEST_STATE/web"; : >"$NEXOLAB_TEST_STATE/web-started" ;;
      *' stop web '*) printf 'stopped\n' >"$NEXOLAB_TEST_STATE/web" ;;
      *' start db '*) printf 'running\n' >"$NEXOLAB_TEST_STATE/db" ;;
      *' stop db '*) printf 'stopped\n' >"$NEXOLAB_TEST_STATE/db" ;;
    esac
    ;;
esac
SH
cat >"$temp/rollback/bin/stat" <<'SH'
#!/usr/bin/env bash
case "$*" in *'%u'*) printf '0\n' ;; *'%a'*) printf '600\n' ;; *) exec /usr/bin/stat "$@" ;; esac
SH
for command in install cp chmod systemctl nginx; do
  cat >"$temp/rollback/bin/$command" <<'SH'
#!/usr/bin/env bash
exit 0
SH
  chmod 0755 "$temp/rollback/bin/$command"
done
cat >"$temp/rollback/bin/tar" <<'SH'
#!/usr/bin/env bash
exec /usr/bin/tar --no-same-owner "$@"
SH
cat >"$temp/rollback/bin/ok" <<'SH'
#!/usr/bin/env bash
exit 0
SH
cat >"$temp/rollback/bin/runtime-fail" <<'SH'
#!/usr/bin/env bash
: >"$NEXOLAB_TEST_STATE/runtime-gate-reached"
exit 39
SH
chmod 0755 "$temp/rollback/bin/docker" "$temp/rollback/bin/stat" "$temp/rollback/bin/tar" "$temp/rollback/bin/ok" "$temp/rollback/bin/runtime-fail"
printf 'unknown\n' >"$temp/rollback/web"
printf 'unknown\n' >"$temp/rollback/db"
if NEXOLAB_TEST_STATE="$temp/rollback" unshare -Ur --map-root-user env \
  PATH="$temp/rollback/bin:$PATH" \
  NEXOLAB_PROJECT_DIR="$temp/rollback/project" \
  NEXOLAB_SECRETS_FILE="$temp/rollback/secrets.env" \
  NEXOLAB_FIREWALL_BIN="$temp/rollback/bin/ok" \
  NEXOLAB_STORAGE_BIN="$temp/rollback/bin/ok" \
  NEXOLAB_RESET_BIN="$temp/rollback/bin/ok" \
  NEXOLAB_RUNTIME_VERIFY_BIN="$temp/rollback/bin/runtime-fail" \
  NEXOLAB_TEST_WEB_CONTAINER=owasp-web-2025 \
  NEXOLAB_TEST_DB_CONTAINER=owasp-db-2025 \
  "$ROLLBACK" "$temp/rollback/snapshot" rollback; then
  fail 'rollback unexpectedly succeeded after runtime verification failure'
fi
[ -f "$temp/rollback/runtime-gate-reached" ] || fail 'rollback did not reach the web runtime gate'
[ "$(<"$temp/rollback/web")" = stopped ] || fail 'rollback restarted or left web running after a failed runtime gate'
[ "$(<"$temp/rollback/db")" = stopped ] || fail 'rollback left DB running after a failed runtime gate'
pass 'real rollback script stops and confirms web and DB after failed runtime verification'

# Use disposable real MariaDB and web containers for reset. A command wrapper
# injects post-drop failures without modifying the real Docker daemon policy.
PATH="${PATH#"$temp/bin:"}"
export PATH
reset_id="nexolab-reset-test-$$-$RANDOM"
reset_db="${reset_id}-db"
reset_web="${reset_id}-web"
cleanup_reset_test() {
  docker rm -f "$reset_db" "$reset_web" >/dev/null 2>&1 || true
}
trap 'cleanup_reset_test; rm -rf "$temp"' EXIT
docker run -d --name "$reset_db" --memory=768m --memory-swap=768m --cpus=1 --pids-limit=128 \
  -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 mariadb:10.11 >/dev/null
for _ in $(seq 1 120); do
  mariadb_init_log="$(docker logs "$reset_db" 2>&1)" || fail 'could not read disposable MariaDB initialization logs'
  [[ "$mariadb_init_log" == *'MariaDB init process done'* ]] && break
  sleep 1
done
if [[ "$mariadb_init_log" != *'MariaDB init process done'* ]]; then
  docker inspect --format 'MariaDB init state={{.State.Status}} oom={{.State.OOMKilled}} exit={{.State.ExitCode}}' "$reset_db" >&2 || true
  docker logs "$reset_db" >&2 || true
  fail 'disposable MariaDB initialization did not complete'
fi
docker exec "$reset_db" mariadb-admin --protocol=socket -uroot ping --silent >/dev/null 2>&1 || fail 'disposable MariaDB did not become ready'
docker exec "$reset_db" mariadb --protocol=socket -uroot -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'reset_root'; CREATE DATABASE validation_db; FLUSH PRIVILEGES;"
docker exec -i -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db <"${PROJECT_DIR}/mysql/init.sql"
docker create --name "$reset_web" owasp2025-web:latest >/dev/null
mkdir -p "$temp/reset/bin"
cat >"$temp/reset/bin/docker" <<'SH'
#!/usr/bin/env bash
if [ "${NEXOLAB_TEST_FAIL_WRITABLE_CLEANUP:-}" = 1 ] && \
  [[ " $* " == *' up --no-start --no-build --force-recreate --no-deps web '* ]]; then
  : >"$NEXOLAB_TEST_STATE/writable-cleanup-failed"
  exit 96
fi
if [ "${NEXOLAB_TEST_HANG_STOP:-}" = 1 ] && \
  [[ " $* " == *" stop --time 10 ${NEXOLAB_WEB_CONTAINER} "* ]]; then
  trap ': >"$NEXOLAB_TEST_STATE/stop-timeout-term"; trap "" TERM' TERM
  while :; do :; done
fi
if [ "${NEXOLAB_TEST_OUTER_TIMEOUT_DESCENDANT:-}" = 1 ] && \
  [ ! -e "$NEXOLAB_TEST_STATE/outer-timeout-injected" ] && \
  [[ " $* " == *' mariadb --protocol=socket -uroot -Nse SELECT 1 '* ]]; then
  : >"$NEXOLAB_TEST_STATE/outer-timeout-injected"
  (
    trap ': >"$NEXOLAB_TEST_STATE/outer-timeout-descendant-term"; trap "" TERM' TERM
    deadline=$((SECONDS + 4))
    while (( SECONDS < deadline )); do :; done
    : >"$NEXOLAB_TEST_STATE/outer-timeout-descendant-survived"
  ) &
  trap ': >"$NEXOLAB_TEST_STATE/outer-timeout-worker-term"; trap "" TERM' TERM
  while :; do :; done
fi
if [ "${NEXOLAB_TEST_REAL_TMPFS_CLEANUP:-}" = 1 ] && \
  [[ " $* " == *' up --no-start --no-build --force-recreate --no-deps web '* ]]; then
  "$NEXOLAB_REAL_DOCKER" rm -f "$NEXOLAB_WEB_CONTAINER" >/dev/null 2>&1 || true
  exec "$NEXOLAB_REAL_DOCKER" create --name "$NEXOLAB_WEB_CONTAINER" \
    --tmpfs /var/www/html/a02_admin/uploads:size=50m,mode=1777,noexec,nosuid \
    --tmpfs /var/www/html/a09_actividad/logs:size=20m,mode=1777,noexec,nosuid \
    owasp2025-web:latest
fi
if [ "${NEXOLAB_TEST_FAIL_WEB_VALIDATION:-}" = 1 ] && \
  [ -e "$NEXOLAB_TEST_STATE/web-started-during-reset" ] && \
  [ ! -e "$NEXOLAB_TEST_STATE/web-validation-failed" ] && \
  [[ " $* " == *" inspect --format {{.State.Running}} ${NEXOLAB_WEB_CONTAINER} "* ]]; then
  : >"$NEXOLAB_TEST_STATE/web-validation-failed"
  printf 'false\n'
  exit 0
fi
if [[ "$*" == "start ${NEXOLAB_WEB_CONTAINER}" ]]; then
  : >"$NEXOLAB_TEST_STATE/web-started-during-reset"
fi
if [ "${NEXOLAB_TEST_FAIL_RENAME:-}" = 1 ] && [[ "$*" == *'RENAME TABLE '* ]]; then
  : >"$NEXOLAB_TEST_STATE/rename-failed"
  exit 97
fi
if [ "${NEXOLAB_TEST_FAIL_RECOVERY_IMPORT:-}" = 1 ] && [ -f "$NEXOLAB_TEST_STATE/rename-failed" ] && \
  [[ "$*" == *'mariadb --protocol=socket -uroot validation_db'* ]]; then
  exit 98
fi
if [ "${NEXOLAB_TEST_FAIL_CLEANUP_DROP:-}" = 1 ] && [[ "$*" == *'DROP DATABASE IF EXISTS `nexolab_reset_'* ]]; then
  exit 99
fi
exec "$NEXOLAB_REAL_DOCKER" "$@"
SH
chmod 0755 "$temp/reset/bin/docker"
cat >"$temp/reset/secrets.env" <<'EOF'
NEXO_DB_NAME=validation_db
NEXO_DB_USER=validation_user
NEXO_DB_PASS=validation_password
NEXO_DB_ROOT_PASS=reset_root
EOF
chmod 0600 "$temp/reset/secrets.env"
reset_env=(
  PATH="$temp/reset/bin:$PATH"
  NEXOLAB_REAL_DOCKER="$(command -v docker)"
  NEXOLAB_PROJECT_DIR="$PROJECT_DIR"
  NEXOLAB_DB_CONTAINER="$reset_db"
  NEXOLAB_WEB_CONTAINER="$reset_web"
  NEXOLAB_SECRETS_FILE="$temp/reset/secrets.env"
  NEXOLAB_LOCK_FILE="$temp/reset/reset.lock"
  NEXOLAB_RECOVERY_DIR="$temp/reset/recovery"
  NEXOLAB_TEST_STATE="$temp/reset"
)
run_reset() {
  # The user namespace supplies a real UID 0 for archive ownership checks while
  # retaining access to this user's local Docker socket. No stat command is mocked.
  unshare -Ur --map-root-user env "${reset_env[@]}" "$@" "$RESET" reset
}
assert_recovery_permissions() {
  local archive metadata
  archive="$1"
  metadata="$(unshare -Ur --map-root-user /usr/bin/stat -c '%u:%a' "$temp/reset/recovery" "$archive")"
  [ "$metadata" = $'0:700\n0:600' ] || fail "recovery archive permissions/ownership are not actual root-only 0700/0600: ${metadata}"
}
docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot -e "CREATE USER 'validation_user'@'localhost' IDENTIFIED BY 'validation_password'; GRANT ALL PRIVILEGES ON validation_db.* TO 'validation_user'@'localhost'; FLUSH PRIVILEGES;"
docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db -e '
  CREATE TABLE attacker_staging (id INT);
  CREATE VIEW attacker_view AS SELECT 1 AS id;
  CREATE PROCEDURE attacker_proc() SELECT 1;
  CREATE EVENT attacker_event ON SCHEDULE EVERY 1 DAY DO SELECT 1;
  CREATE TRIGGER attacker_trigger BEFORE INSERT ON users FOR EACH ROW SET NEW.username = NEW.username;'
if ! run_reset; then
  docker inspect --format 'reset DB state={{.State.Status}} oom={{.State.OOMKilled}} exit={{.State.ExitCode}}' "$reset_db" >&2 || true
  docker logs "$reset_db" >&2 || true
  fail 'real reset failed against disposable MariaDB'
fi
remaining_objects="$(docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db -Nse "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'validation_db' AND TABLE_TYPE = 'VIEW'; SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = 'validation_db'; SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA = 'validation_db'; SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = 'validation_db'; SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'validation_db' AND TABLE_NAME = 'attacker_staging';")"
[ "$remaining_objects" = $'0\n0\n0\n0\n0' ] || fail 'reset retained attacker-created non-table or staging objects'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'reset started web that was originally stopped'
[ -z "$(docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot -Nse "SHOW DATABASES LIKE 'nexolab_reset_%'")" ] || fail 'successful reset retained temporary reset schemas'
[ -z "$(/usr/bin/find "$temp/reset/recovery" -mindepth 1 -maxdepth 1 -type f -print -quit)" ] || fail 'successful reset retained its recovery archive before all success gates completed'
pass 'real reset removes views, routines, events, triggers, and staging tables without starting stopped web'

docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db -e '
  CREATE TABLE attacker_recovery_table (id INT);
  CREATE VIEW attacker_recovery_view AS SELECT 1 AS id;
  CREATE PROCEDURE attacker_recovery_proc() SELECT 1;
  CREATE EVENT attacker_recovery_event ON SCHEDULE EVERY 1 DAY DO SELECT 1;
  CREATE TRIGGER attacker_recovery_trigger BEFORE INSERT ON users FOR EACH ROW SET NEW.username = NEW.username;'
docker start "$reset_web" >/dev/null
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = true ] || fail 'post-replacement recovery test web did not start before reset'
rm -f "$temp/reset/web-started-during-reset"
if run_reset NEXOLAB_TEST_FAIL_RENAME=1 >"$temp/reset/post-replacement-recovery.output" 2>&1; then
  fail 'reset unexpectedly succeeded after an injected post-replacement failure'
fi
[ ! -e "$temp/reset/web-started-during-reset" ] || fail 'post-replacement recovery restarted web before temporary schemas were proven removed'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'post-replacement recovery left web running'
application_attacker_objects="$(docker exec -e MYSQL_PWD=validation_password "$reset_db" mariadb --protocol=socket -uvalidation_user -Nse "
  SELECT CONCAT(TABLE_SCHEMA, '.', TABLE_NAME) FROM information_schema.TABLES WHERE TABLE_NAME IN ('attacker_recovery_table', 'attacker_recovery_view')
  UNION ALL SELECT CONCAT(ROUTINE_SCHEMA, '.', ROUTINE_NAME) FROM information_schema.ROUTINES WHERE ROUTINE_NAME = 'attacker_recovery_proc'
  UNION ALL SELECT CONCAT(EVENT_SCHEMA, '.', EVENT_NAME) FROM information_schema.EVENTS WHERE EVENT_NAME = 'attacker_recovery_event'
  UNION ALL SELECT CONCAT(TRIGGER_SCHEMA, '.', TRIGGER_NAME) FROM information_schema.TRIGGERS WHERE TRIGGER_NAME = 'attacker_recovery_trigger';")"
[ -z "$application_attacker_objects" ] || fail 'post-replacement recovery exposed attacker objects through an application-reachable schema'
[ -z "$(docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot -Nse "SHOW DATABASES LIKE 'nexolab_reset_%'")" ] || \
  fail 'post-replacement recovery retained a recovery or staging schema'
recovery_archives=("$temp/reset/recovery"/*.sql.gz.*)
[ -f "${recovery_archives[0]}" ] || fail 'post-replacement recovery did not preserve an external operator-only archive'
gzip -t "${recovery_archives[0]}" || fail 'post-replacement recovery archive is corrupt'
assert_recovery_permissions "${recovery_archives[0]}"
grep -Fqx 'ERROR: reset failed after live schema replacement; web remains stopped pending operator recovery.' "$temp/reset/post-replacement-recovery.output" || \
  fail 'reset did not surface the fail-closed post-replacement recovery state'
pass 'post-replacement recovery removes temporary schemas, retains only an external archive, and keeps web stopped'

rm -f -- "$temp/reset/recovery"/*
docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db -e 'DROP DATABASE validation_db; CREATE DATABASE validation_db;'
docker exec -i -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db <"${PROJECT_DIR}/mysql/init.sql"
docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db -e 'CREATE VIEW cleanup_view AS SELECT 1 AS id;'
docker start "$reset_web" >/dev/null
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = true ] || fail 'cleanup-drop test web did not start before reset'
if run_reset NEXOLAB_TEST_FAIL_CLEANUP_DROP=1 >"$temp/reset/cleanup-drop-failure.output" 2>&1; then
  fail 'reset unexpectedly succeeded when prior-schema cleanup drop failed'
fi
[ ! -e "$temp/reset/web-started-during-reset" ] || fail 'cleanup-drop failure restarted or exposed web before cleanup succeeded'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'cleanup-drop failure started web'
[ "$(docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db -Nse "SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = 'validation_db' AND TABLE_NAME = 'cleanup_view';")" = 0 ] || \
  fail 'cleanup-drop failure exposed an attacker view in the live schema'
grep -Fqx 'ERROR: Reset cleanup could not remove temporary recovery schemas; web remains stopped.' "$temp/reset/cleanup-drop-failure.output" || \
  fail 'reset did not surface the prior-schema cleanup drop failure'
pass 'real reset fails closed when temporary-schema cleanup cannot remove attacker state'

rm -f -- "$temp/reset/recovery"/* "$temp/reset/web-started-during-reset" "$temp/reset/web-validation-failed"
docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db -e 'DROP DATABASE validation_db; CREATE DATABASE validation_db;'
docker exec -i -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db <"${PROJECT_DIR}/mysql/init.sql"
docker start "$reset_web" >/dev/null
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = true ] || fail 'tmpfs cleanup test web did not start before reset'
if ! run_reset NEXOLAB_TEST_REAL_TMPFS_CLEANUP=1 >"$temp/reset/tmpfs-cleanup.output" 2>&1; then
  fail 'reset failed while recreating the real A09 tmpfs directory mount'
fi
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = true ] || fail 'successful tmpfs cleanup did not restart previously-running web'
tmpfs_mounts="$(docker inspect --format '{{json .HostConfig.Tmpfs}}' "$reset_web")"
[[ "$tmpfs_mounts" == *'"/var/www/html/a09_actividad/logs":'* ]] || \
  fail 'tmpfs cleanup did not verify the real A09 logs directory mount'
[[ "$tmpfs_mounts" != *'"/var/www/html/a09_actividad/logs/app.log":'* ]] || \
  fail 'tmpfs cleanup accepted an app.log mount that Compose does not define'
pass 'reset accepts the real A09 logs-directory tmpfs after stopping and recreating web'

rm -f -- "$temp/reset/recovery"/* "$temp/reset/web-started-during-reset"
if run_reset NEXOLAB_TEST_REAL_TMPFS_CLEANUP=1 NEXOLAB_TEST_FAIL_WEB_VALIDATION=1 >"$temp/reset/web-validation-failure.output" 2>&1; then
  fail 'reset falsely succeeded after web validation reported stopped'
fi
[ -e "$temp/reset/web-started-during-reset" ] || fail 'web validation failure injection did not reach the restart gate'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'web validation failure left web running'
recovery_archives=("$temp/reset/recovery"/*.sql.gz.*)
[ -f "${recovery_archives[0]}" ] || fail 'web validation failure removed the external recovery archive'
assert_recovery_permissions "${recovery_archives[0]}"
grep -Fqx 'ERROR: Web start validation failed after reset; web remains stopped.' "$temp/reset/web-validation-failure.output" || \
  fail 'reset did not surface the failed web validation gate'
pass 'failed web validation fails closed, preserves the archive, and keeps web stopped'

rm -f -- "$temp/reset/recovery"/* "$temp/reset/web-started-during-reset"
docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db -e 'DROP DATABASE validation_db; CREATE DATABASE validation_db;'
docker exec -i -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db <"${PROJECT_DIR}/mysql/init.sql"
docker start "$reset_web" >/dev/null
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = true ] || fail 'writable-cleanup test web did not start before reset'
if run_reset NEXOLAB_TEST_FAIL_WRITABLE_CLEANUP=1 >"$temp/reset/writable-cleanup-failure.output" 2>&1; then
  fail 'reset unexpectedly succeeded when stopped-container writable cleanup failed'
fi
[ -f "$temp/reset/writable-cleanup-failed" ] || fail 'writable-cleanup failure injection did not reach the post-archive cleanup gate'
[ ! -e "$temp/reset/web-started-during-reset" ] || fail 'web started before writable cleanup passed'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'writable-cleanup failure left web running'
recovery_archives=("$temp/reset/recovery"/*.sql.gz.*)
[ -f "${recovery_archives[0]}" ] || fail 'writable-cleanup failure removed the external recovery archive'
assert_recovery_permissions "${recovery_archives[0]}"
pass 'writable-path cleanup failure preserves the archive and keeps web stopped before any web start'

rm -f -- "$temp/reset/recovery"/* "$temp/reset/stop-timeout-term"
docker start "$reset_web" >/dev/null
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = true ] || fail 'timeout test web did not start before reset'
timeout_started="$(date +%s)"
if run_reset NEXOLAB_TEST_HANG_STOP=1 NEXOLAB_COMMAND_TIMEOUT_SECONDS=1 NEXOLAB_TERM_GRACE_SECONDS=1 >"$temp/reset/stop-timeout.output" 2>&1; then
  fail 'reset unexpectedly succeeded when Docker stop ignored TERM'
fi
timeout_elapsed="$(( $(date +%s) - timeout_started ))"
[ "$timeout_elapsed" -lt 8 ] || fail "Docker timeout escalation hung for ${timeout_elapsed}s"
[ -f "$temp/reset/stop-timeout-term" ] || fail 'Docker timeout did not send TERM before KILL escalation'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'Docker timeout escalation did not verify web containment'
pass 'Docker TERM/KILL timeout escalation fails closed without hanging'

rm -f -- "$temp/reset/outer-timeout-"*
docker start "$reset_web" >/dev/null
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = true ] || fail 'outer timeout test web did not start before reset'
outer_timeout_started="$(date +%s)"
if run_reset NEXOLAB_TEST_OUTER_TIMEOUT_DESCENDANT=1 NEXOLAB_RESET_TIMEOUT_SECONDS=1 NEXOLAB_COMMAND_TIMEOUT_SECONDS=20 NEXOLAB_TERM_GRACE_SECONDS=1 >"$temp/reset/outer-timeout.output" 2>&1; then
  fail 'reset unexpectedly succeeded after its worker exceeded the outer timeout'
fi
outer_timeout_elapsed="$(( $(date +%s) - outer_timeout_started ))"
[ "$outer_timeout_elapsed" -lt 8 ] || fail "outer reset timeout escalation hung for ${outer_timeout_elapsed}s"
[ -f "$temp/reset/outer-timeout-worker-term" ] || fail 'outer timeout did not TERM the reset worker process group'
[ -f "$temp/reset/outer-timeout-descendant-term" ] || fail 'outer timeout did not TERM the Docker-command descendant'
sleep 4
[ ! -e "$temp/reset/outer-timeout-descendant-survived" ] || fail 'outer timeout left a Docker-command descendant alive after reset returned'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'outer timeout did not keep web stopped'
pass 'outer timeout TERM/KILLs the full worker process group with no surviving Docker descendant'

printf '%s\n' 'Production-only gates not exercised locally: systemd boot ordering, real iptables backend, host cgroup/device topology, TLS certificate files, and cron delivery.'
