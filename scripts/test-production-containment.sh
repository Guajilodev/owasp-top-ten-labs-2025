#!/usr/bin/env bash
# Disposable containment contract tests. Docker/systemd/nginx are test doubles.
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly PROJECT_DIR
readonly UPSTREAM="${PROJECT_DIR}/deploy/production/nexolab-nginx-upstream"
readonly RUNTIME="${PROJECT_DIR}/deploy/production/nexolab-runtime-verify"
readonly START="${PROJECT_DIR}/deploy/production/nexolab-start"
readonly RESET="${PROJECT_DIR}/deploy/production/nexolab-reset"
readonly ROLLBACK="${PROJECT_DIR}/deploy/production/nexolab-rollback"
readonly FIREWALL="${PROJECT_DIR}/deploy/production/nexolab-firewall"
temp="$(mktemp -d)"
trap 'rm -rf "$temp"' EXIT
passed=0

fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
pass() { passed=$((passed + 1)); printf 'PASS: %s\n' "$*"; }
expect_failure() { if ( "$@" >/dev/null 2>&1 ); then fail "unexpected success: $*"; fi; }
expect_failure_output() {
  local output="$1"
  shift
  if ( "$@" >"$output" 2>&1 ); then fail "unexpected success: $*"; fi
}

NEXO_DB_NAME=validation_db NEXO_DB_USER=validation_user NEXO_DB_PASS=validation_password \
NEXO_DB_ROOT_PASS=validation_root_password NEXOLAB_DB_STORAGE_PATH=/srv/owasp2025-db \
  docker compose -f "${PROJECT_DIR}/docker-compose.yml" --project-directory "$PROJECT_DIR" config --format json >"$temp/compose-local.json"
NEXO_DB_NAME=validation_db NEXO_DB_USER=validation_user NEXO_DB_PASS=validation_password \
NEXO_DB_ROOT_PASS=validation_root_password NEXOLAB_DB_STORAGE_PATH=/srv/owasp2025-db \
  docker compose -f "${PROJECT_DIR}/docker-compose.prod.yml" --project-directory "$PROJECT_DIR" config --format json >"$temp/compose-prod.json"
python3 - "$temp/compose-local.json" "$temp/compose-prod.json" "${PROJECT_DIR}/docker-compose.yml" "${PROJECT_DIR}/docker-compose.prod.yml" <<'PY'
import json, sys
local, prod = (json.load(open(path, encoding="utf-8")) for path in sys.argv[1:3])
sources = "\n".join(open(path, encoding="utf-8").read() for path in sys.argv[3:])
for config in (local, prod):
    assert all("container_name" not in service for service in config["services"].values())
    assert all("com.docker.network.bridge.name" not in network.get("driver_opts", {}) for network in config["networks"].values())
    assert not config["volumes"]["nexo_db"].get("external", False)
    assert config["volumes"]["nexo_db"]["name"] == "owasp2025_nexo_db"
    assert config["volumes"]["nexo_db"]["name"] != "owasp_nexo_db_2025"
assert "com.docker.network.bridge.name" not in sources
assert "name: owasp_nexo_db_2025" not in sources
assert prod["services"]["web"].get("ports", []) == []
assert set(prod["services"]["web"]["networks"]) == {"frontend", "backend"}
assert set(prod["services"]["db"]["networks"]) == {"backend"}
assert all(prod["networks"][name]["internal"] for name in ("frontend", "backend"))
assert prod["volumes"]["nexo_db"]["driver_opts"] == {"type": "none", "o": "bind", "device": "/srv/owasp2025-db"}
PY
pass 'rendered Compose uses project-scoped DB storage and Docker-derived bridge interfaces'

# Reproduce snapshot manifest creation with a real checksum check. The transient
# output file must not checksum itself.
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

python3 - "${PROJECT_DIR}/deploy/production/README.md" "${PROJECT_DIR}/deploy/production/nexolab.nginx" <<'PY'
import sys
readme, nginx = (open(path, encoding="utf-8").read() for path in sys.argv[1:])
assert "trusted" in readme.lower() and "OUTPUT" in readme
assert "upstream.conf" in nginx and "127.0.0.1:8082" not in nginx
PY
pass 'nginx topology and trusted-host boundary are documented'

mkdir -p "$temp/mocks/bin" "$temp/upstream/etc/nginx"
cat >"$temp/mocks/bin/docker" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
state_dir="${NEXOLAB_TEST_STATE:-}"
role_frontend="${NEXOLAB_TEST_FRONTEND_ROLE:-frontend}"
role_backend="${NEXOLAB_TEST_BACKEND_ROLE:-backend}"
frontend_short_id='1405359a5884'
backend_short_id='f2c1191a730b'
frontend_full_id='1405359a58840123456789abcdef0123456789abcdef0123456789abcdef0123'
backend_full_id='f2c1191a730b456789abcdef0123456789abcdef0123456789abcdef01234567'
bridge_full_id='a4b5c6d7e8f90123456789abcdef0123456789abcdef0123456789abcdef0123'
[ -z "${NEXOLAB_TEST_DOCKER_LOG:-}" ] || printf '%s\n' "$*" >>"$NEXOLAB_TEST_DOCKER_LOG"
case "${1:-}:${2:-}" in
  network:ls)
    printf '%s\n%s\n' "$frontend_short_id" "$backend_short_id"
    [ "${NEXOLAB_TEST_DUPLICATE_ROLE:-0}" = 1 ] && printf 'c0ffee123456\n'
    ;;
  network:inspect)
    network_id="$3"; format="${!#}"
    case "$format" in
      *'com.docker.compose.network'*)
        case "$network_id" in
          "$frontend_short_id"|c0ffee123456) printf '%s\n' "$role_frontend" ;;
          "$backend_short_id") printf '%s\n' "$role_backend" ;;
        esac
        ;;
      '{{.Id}}')
        [ "${NEXOLAB_TEST_MALFORMED_NETWORK_ID:-0}" = 1 ] && { printf 'not-a-canonical-network-id\n'; exit 0; }
        case "$network_id" in
          "$frontend_short_id") printf '%s\n' "$frontend_full_id" ;;
          "$backend_short_id") printf '%s\n' "$backend_full_id" ;;
          *) exit 1 ;;
        esac
        ;;
      '{{.Internal}}') printf '%s\n' "${NEXOLAB_TEST_INTERNAL:-true}" ;;
      '{{.Driver}}') printf 'bridge\n' ;;
    esac
    ;;
  info:--format) printf 'systemd\n' ;;
  version:--format) printf '29.3.1\n' ;;
  inspect:--format)
    if [ "${NEXOLAB_TEST_ABSENT_CONTAINER:-0}" = 1 ]; then
      # Docker inspect prints a blank line before it reports a missing container.
      printf '\n'
      exit 1
    fi
    format="$3"; container="${!#}"
    id="$(<"$state_dir/id")"; running="$(<"$state_dir/running")"; ip="$(<"$state_dir/ip")"
    case "$format" in
      *'.Id'*) printf '%s\n' "$id" ;;
      *'.State.Running'*) printf '%s\n' "$running" ;;
      *'.NetworkSettings.Networks'*)
        # Docker 29 truth: stopped endpoints are unusable. A valid address is
        # emitted only after the mocked start transition sets running=true.
        if [[ "$container" == *db* ]] && [ "$running" = true ]; then
          printf '%s\t172.22.0.3\n' "$backend_full_id"
        elif [ "$running" = true ]; then
          printf '%s\t%s\n%s\t172.22.0.3\n' "$frontend_full_id" "$ip" "$backend_full_id"
          [ "${NEXOLAB_TEST_EXTRA_BRIDGE:-0}" = 1 ] && printf '%s\t172.17.0.2\n' "$bridge_full_id"
        elif [[ "$container" == *db* ]]; then
          printf '%s\tinvalid IP\n' "$backend_full_id"
        else
          printf '%s\tinvalid IP\n%s\tinvalid IP\n' "$frontend_full_id" "$backend_full_id"
        fi
        ;;
      *'.HostConfig.PublishAllPorts'*) printf 'false\n' ;;
      *'.NetworkSettings.Ports'*) printf '{"80/tcp":null}\n' ;;
      *'.HostConfig.PortBindings'*) printf '{}\n' ;;
    esac
    ;;
  ps:-aq)
    # A missing container is not an error when Docker is asked to list it.
    # Keep stdout empty so the helper can distinguish absence from a query failure.
    ;;
esac
SH
cat >"$temp/mocks/bin/nginx" <<'SH'
#!/usr/bin/env bash
printf 'nginx %s\n' "$*" >>"$NEXOLAB_TEST_LOG"
if [ "${NEXOLAB_TEST_CHANGE_BEFORE_RELOAD:-0}" = 1 ]; then
  printf '%064d\n' 2 >"$NEXOLAB_TEST_STATE/id"
  printf '172.21.0.4\n' >"$NEXOLAB_TEST_STATE/ip"
fi
SH
cat >"$temp/mocks/bin/systemctl" <<'SH'
#!/usr/bin/env bash
printf 'systemctl %s\n' "$*" >>"$NEXOLAB_TEST_LOG"
if [ "${NEXOLAB_TEST_CHANGE_DURING_RELOAD:-0}" = 1 ]; then
  printf '%064d\n' 3 >"$NEXOLAB_TEST_STATE/id"
  printf '172.21.0.5\n' >"$NEXOLAB_TEST_STATE/ip"
fi
SH
chmod 0755 "$temp/mocks/bin/"*

prepare_endpoint_state() {
  local running="$1"
  mkdir -p "$temp/endpoint-state"
  printf '%064d\n' 1 >"$temp/endpoint-state/id"
  printf '%s\n' "$running" >"$temp/endpoint-state/running"
  printf '172.21.0.3\n' >"$temp/endpoint-state/ip"
  : >"$temp/endpoint.log"
}
run_upstream() {
  local operation="$1"
  shift
  unshare -Ur --map-root-user env PATH="$temp/mocks/bin:$PATH" \
    NEXOLAB_TEST_STATE="$temp/endpoint-state" NEXOLAB_TEST_LOG="$temp/endpoint.log" \
    NEXOLAB_NGINX_UPSTREAM_FILE="$temp/upstream/etc/nginx/nexolab/upstream.conf" "$@" "$UPSTREAM" "$operation"
}

prepare_endpoint_state false
expect_failure run_upstream refresh
[ ! -s "$temp/endpoint.log" ] || fail 'stopped refresh reloaded nginx'
pass 'stopped Docker 29 web has invalid IP and refresh rejects it without reload'

# First let the helper create a secure include directory, then simulate the
# stale active endpoint that a repeated lifecycle invocation must replace.
run_upstream quarantine
printf '# stale\nupstream nexolab_web { server 172.21.0.3:80; }\n' >"$temp/upstream/etc/nginx/nexolab/upstream.conf"
: >"$temp/endpoint.log"
run_upstream quarantine
grep -Fqx '    server unix:/run/nexolab-quarantine.sock;' "$temp/upstream/etc/nginx/nexolab/upstream.conf" || fail 'quarantine did not replace stale endpoint'
[ "$(<"$temp/endpoint.log")" = $'nginx -t\nsystemctl reload nginx' ] || fail 'quarantine did not validate then reload'
pass 'quarantine replaces a stale upstream before a web start'

# Docker inspect writes a blank line and exits nonzero for a missing container.
# The subsequent empty `docker ps -aq` lookups prove this is first installation,
# not an inspect failure for an existing web container.
rm -f -- "$temp/upstream/etc/nginx/nexolab/upstream.conf"
: >"$temp/endpoint.log"
: >"$temp/absent-quarantine-docker.log"
NEXOLAB_TEST_ABSENT_CONTAINER=1 NEXOLAB_TEST_DOCKER_LOG="$temp/absent-quarantine-docker.log" run_upstream quarantine
[ -f "$temp/upstream/etc/nginx/nexolab/upstream.conf" ] || fail 'absent-container quarantine did not write the upstream include'
grep -Fqx '    server unix:/run/nexolab-quarantine.sock;' "$temp/upstream/etc/nginx/nexolab/upstream.conf" || fail 'absent-container quarantine did not install the unix-socket include'
[ "$(<"$temp/endpoint.log")" = $'nginx -t\nsystemctl reload nginx' ] || fail 'absent-container quarantine did not validate then reload'
grep -Fqx 'ps -aq --no-trunc --filter id=owasp2025-web-1' "$temp/absent-quarantine-docker.log" || fail 'absent-container quarantine did not prove the ID lookup empty'
grep -Fqx 'ps -aq --no-trunc --filter name=^/owasp2025-web-1$' "$temp/absent-quarantine-docker.log" || fail 'absent-container quarantine did not prove the name lookup empty'
pass 'quarantine accepts a genuinely absent web container before first Compose creation'

# Retain the stopped fixture and make only the mocked start transition expose
# its already-stored runtime address.
printf 'true\n' >"$temp/endpoint-state/running"
run_upstream refresh
grep -Fqx '    server 172.21.0.3:80;' "$temp/upstream/etc/nginx/nexolab/upstream.conf" || fail 'refresh did not select the running frontend endpoint'
run_upstream verify
pass 'upstream refresh activates only after an explicit stopped-to-running transition'

prepare_endpoint_state true
run_upstream refresh
grep -Fqx '    server 172.21.0.3:80;' "$temp/upstream/etc/nginx/nexolab/upstream.conf" || fail 'refresh did not select the running frontend endpoint'
run_upstream verify
pass 'running web refresh pins the frontend endpoint and installs the exact include'

prepare_endpoint_state true
NEXOLAB_TEST_EXTRA_BRIDGE=1 expect_failure run_upstream refresh
prepare_endpoint_state true
NEXOLAB_TEST_DUPLICATE_ROLE=1 expect_failure run_upstream refresh
pass 'upstream discovery rejects extra bridge attachments and duplicate role networks'

prepare_endpoint_state true
NEXOLAB_TEST_CHANGE_BEFORE_RELOAD=1 expect_failure run_upstream refresh
grep -Fq 'systemctl reload nginx' "$temp/endpoint.log" && fail 'pre-reload identity change reached reload'
prepare_endpoint_state true
NEXOLAB_TEST_CHANGE_DURING_RELOAD=1 expect_failure run_upstream refresh
grep -Fq 'systemctl reload nginx' "$temp/endpoint.log" || fail 'post-reload change did not exercise reload'
pass 'container ID/IP changes before and during reload fail closed'

# The pre-start verifier only sees network objects. It must not inspect an IP.
prepare_endpoint_state false
PATH="$temp/mocks/bin:$PATH" NEXOLAB_TEST_STATE="$temp/endpoint-state" "$RUNTIME" pre-start
expect_failure env PATH="$temp/mocks/bin:$PATH" NEXOLAB_TEST_STATE="$temp/endpoint-state" NEXOLAB_TEST_DUPLICATE_ROLE=1 "$RUNTIME" pre-start
expect_failure env PATH="$temp/mocks/bin:$PATH" NEXOLAB_TEST_STATE="$temp/endpoint-state" NEXOLAB_TEST_INTERNAL=false "$RUNTIME" pre-start
pass 'pre-start verification accepts stopped Docker 29 containers and rejects unsafe role topology'

# Docker network ls -q returns 12-character IDs, while container attachments
# expose the canonical 64-character IDs. Exact attachment equality must use
# canonical network IDs rather than prefix matching.
prepare_endpoint_state true
PATH="$temp/mocks/bin:$PATH" NEXOLAB_TEST_STATE="$temp/endpoint-state" bash -c '
  source "$1"
  discover_networks
  [ "$FRONTEND_ID" = "$2" ] && [ "$BACKEND_ID" = "$3" ]
  verify_container_attachments owasp2025-web-1 "$FRONTEND_ID" "$BACKEND_ID"
' -- "$RUNTIME" 1405359a58840123456789abcdef0123456789abcdef0123456789abcdef0123 f2c1191a730b456789abcdef0123456789abcdef0123456789abcdef01234567
pass 'runtime verifier canonicalizes short network discovery IDs before exact full attachment checks'
# The real post-start path reaches web attachment equality after validating the
# DB attachment. Controls are overridden only because this disposable double
# does not model host cgroups or the production storage mount.
# shellcheck disable=SC2016 # The child shell expands sourced verifier symbols.
expect_failure_output "$temp/extra-network.out" env PATH="$temp/mocks/bin:$PATH" NEXOLAB_TEST_STATE="$temp/endpoint-state" NEXOLAB_TEST_EXTRA_BRIDGE=1 bash -c 'source "$1"; verify_container_controls() { :; }; post_start all' -- "$RUNTIME"
grep -Fq 'owasp2025-web-1 has an unexpected network attachment set.' "$temp/extra-network.out" || fail 'post-start verifier did not reject the extra bridge attachment'
pass 'runtime post-start verification requires exact web attachments and rejects extra bridge'

# Swap containment lives on the whole cgroup v2 ancestor chain: a descendant can
# never exceed the strictest limit above it. A systemd daemon-reload can revert
# the container scope leaf to `max` while the slice still enforces 0, so reading
# the leaf alone reports a breach that never happened and stops production.
mkdir -p "$temp/controls/bin" "$temp/controls/proc/4242"
readonly CONTROLS_LEAF="$temp/controls/cgroup/nexolab.slice/nexolab-db.slice/docker-abc.scope"
cat >"$temp/controls/bin/docker" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
case "${3:-}" in
  *'.HostConfig.CgroupParent'*) printf '%s\n' "${NEXOLAB_TEST_CGROUP_PARENT:-nexolab-db.slice}" ;;
  *'.State.Pid'*) printf '4242\n' ;;
  *) exit 1 ;;
esac
SH
chmod 0755 "$temp/controls/bin/docker"
printf '0::/nexolab.slice/nexolab-db.slice/docker-abc.scope\n' >"$temp/controls/proc/4242/cgroup"
write_swap_chain() {
  local slice_limit="$1" leaf_limit="$2" node="$CONTROLS_LEAF"
  rm -rf "$temp/controls/cgroup"
  mkdir -p "$CONTROLS_LEAF"
  printf '%s\n' "$leaf_limit" >"${CONTROLS_LEAF}/memory.swap.max"
  while node="${node%/*}"; [ "$node" != "$temp/controls/cgroup" ]; do
    printf '%s\n' "$slice_limit" >"${node}/memory.swap.max"
  done
}
# shellcheck disable=SC2016,SC2120 # The child shell expands sourced verifier symbols; callers add env overrides only when a case needs one.
run_controls() {
  env PATH="$temp/controls/bin:$PATH" NEXOLAB_CGROUP_ROOT="$temp/controls/cgroup" \
    NEXOLAB_PROC_ROOT="$temp/controls/proc" "$@" \
    bash -c 'source "$1"; verify_container_controls owasp2025-db-1 nexolab-db.slice' -- "$RUNTIME"
}
write_swap_chain 0 0
run_controls || fail 'runtime verifier rejected a fully enforced swap chain'
write_swap_chain 0 max
run_controls || fail 'runtime verifier read the leaf alone and rejected a slice-enforced swap limit'
pass 'runtime verifier accepts a drifted scope leaf while an ancestor slice still enforces swap=0'

write_swap_chain max max
expect_failure_output "$temp/controls/unbounded.out" run_controls
grep -Fq 'owasp2025-db-1 has effective memory.swap.max=max, expected 0.' "$temp/controls/unbounded.out" ||
  fail 'runtime verifier did not reject an unbounded swap chain'
write_swap_chain 0 max
rm -f "${CONTROLS_LEAF%/*}/memory.swap.max"
expect_failure run_controls
write_swap_chain 0 not-a-limit
expect_failure run_controls
write_swap_chain 0 0
expect_failure run_controls NEXOLAB_TEST_CGROUP_PARENT=nexolab-web.slice
pass 'runtime verifier fails closed on an unbounded, unreadable, malformed, or misassigned swap chain'

prepare_endpoint_state true
NEXOLAB_TEST_MALFORMED_NETWORK_ID=1 expect_failure run_upstream refresh

mkdir -p "$temp/absent/bin"
cat >"$temp/absent/bin/docker" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >>"$NEXOLAB_TEST_LOG"
case "${1:-}:${2:-}" in
  inspect:--format) exit 1 ;;
  ps:-aq) exit 0 ;;
  *) exit 1 ;;
esac
SH
chmod 0755 "$temp/absent/bin/docker"
: >"$temp/absent/docker.log"
# shellcheck disable=SC2016 # The child shell invokes sourced reset helpers.
absent_state="$(unshare -Ur --map-root-user env PATH="$temp/absent/bin:$PATH" NEXOLAB_TEST_LOG="$temp/absent/docker.log" \
  NEXOLAB_WEB_CONTAINER=absent-web bash -c 'exit() { return 0; }; source "$1" >/dev/null 2>&1; unset -f exit; state="$(container_running "$WEB_CONTAINER")"; [ "$state" = false ]; stop_and_confirm "$WEB_CONTAINER"; printf "%s\n" "$state"' -- "$RESET")"
[ "$absent_state" = false ] || fail 'genuinely absent web container was not treated as stopped'
grep -Fq 'ps -aq --no-trunc --filter id=absent-web' "$temp/absent/docker.log" || fail 'absent-container test did not prove ID lookup was empty'
grep -Fq 'ps -aq --no-trunc --filter name=^/absent-web$' "$temp/absent/docker.log" || fail 'absent-container test did not prove name lookup was empty'
pass 'reset treats a genuinely absent web container as stopped, not a fatal error'

mkdir -p "$temp/start/bin" "$temp/start/project" "$temp/start/state"
touch "$temp/start/project/docker-compose.prod.yml"
cat >"$temp/start/bin/docker" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf 'docker %s\n' "$*" >>"$NEXOLAB_TEST_LOG"
state="$NEXOLAB_TEST_STATE"
if [ -e "$state/force-compose-stop-web" ] && [ "${1:-}" = compose ]; then
  rm -f "$state/force-compose-stop-web"
  exit 41
fi
case " $* " in
  *' inspect '*owasp2025-web-1*) [ "$(<"$state/web")" = running ] && printf 'true\n' || printf 'false\n' ;;
  *' inspect '*owasp2025-db-1*) [ "$(<"$state/db")" = running ] && printf 'true\n' || printf 'false\n' ;;
  *' stop web '*) printf 'stopped\n' >"$state/web" ;;
  *' stop db '*) [ "${NEXOLAB_TEST_FAIL_AT:-}" != stop-db ] || exit 42; printf 'stopped\n' >"$state/db" ;;
  *' stop '*owasp2025-web-1*) printf 'stopped\n' >"$state/web" ;;
  *' stop '*owasp2025-db-1*) printf 'stopped\n' >"$state/db" ;;
  *' up --no-start --build'*) [ "${NEXOLAB_TEST_FAIL_AT:-}" != up ] || exit 90 ;;
  *' start db web'*) printf 'running\n' >"$state/web"; printf 'running\n' >"$state/db"; [ "${NEXOLAB_TEST_COMPOSE_START_FAIL:-0}" != 1 ] || exit 37 ;;
esac
SH
cat >"$temp/start/bin/firewall" <<'SH'
#!/usr/bin/env bash
printf 'firewall %s\n' "$*" >>"$NEXOLAB_TEST_LOG"
[ "${NEXOLAB_TEST_FAIL_AT:-}" != firewall ]
SH
cat >"$temp/start/bin/runtime" <<'SH'
#!/usr/bin/env bash
printf 'runtime %s\n' "$*" >>"$NEXOLAB_TEST_LOG"
[ "${NEXOLAB_TEST_FAIL_AT:-}" != "$1" ]
SH
cat >"$temp/start/bin/upstream" <<'SH'
#!/usr/bin/env bash
printf 'upstream %s\n' "$*" >>"$NEXOLAB_TEST_LOG"
[ "${NEXOLAB_TEST_FAIL_AT:-}" != "$1" ]
SH
chmod 0755 "$temp/start/bin/"*
run_start() {
  unshare -Ur --map-root-user env PATH="$temp/start/bin:$PATH" NEXOLAB_TEST_STATE="$temp/start/state" NEXOLAB_TEST_LOG="$temp/start/log" \
    NEXOLAB_PROJECT_DIR="$temp/start/project" NEXOLAB_FIREWALL_BIN="$temp/start/bin/firewall" \
    NEXOLAB_RUNTIME_VERIFY_BIN="$temp/start/bin/runtime" NEXOLAB_NGINX_UPSTREAM_BIN="$temp/start/bin/upstream" \
    NEXOLAB_TEST_COMPOSE_START_FAIL="${NEXOLAB_TEST_COMPOSE_START_FAIL:-0}" \
    NEXOLAB_LIFECYCLE_LOCK_FILE="$temp/start/lifecycle.lock" "$START"
}
printf 'running\n' >"$temp/start/state/web"; printf 'running\n' >"$temp/start/state/db"; : >"$temp/start/log"
run_start
python3 - "$temp/start/log" <<'PY'
import sys
lines = open(sys.argv[1], encoding="utf-8").read().splitlines()
needles = ["stop web", "stop db", "up --no-start --build", "firewall apply", "firewall verify", "runtime pre-start", "upstream quarantine", "start db web", "runtime post-start all", "upstream refresh", "upstream verify"]
positions = [next(i for i, line in enumerate(lines) if needle in line) for needle in needles]
assert positions == sorted(positions), (lines, positions)
PY
pass 'start orders stop, pre-start, quarantine, start, post-start, and running refresh'

for gate in up firewall pre-start quarantine post-start refresh; do
  printf 'running\n' >"$temp/start/state/web"; printf 'running\n' >"$temp/start/state/db"; : >"$temp/start/log"
  NEXOLAB_TEST_FAIL_AT="$gate" expect_failure run_start
  if ! { [ "$(<"$temp/start/state/web")" = stopped ] && [ "$(<"$temp/start/state/db")" = stopped ]; }; then fail "pre-existing services escaped containment at ${gate}"; fi
done
pass 'every injected start gate failure synchronously contains pre-existing web then DB'

for failure_mode in post-start compose; do
  printf 'running\n' >"$temp/start/state/web"; printf 'running\n' >"$temp/start/state/db"; : >"$temp/start/log"
  if [ "$failure_mode" = compose ]; then
    NEXOLAB_TEST_COMPOSE_START_FAIL=1 expect_failure run_start
  else
    NEXOLAB_TEST_FAIL_AT=post-start expect_failure run_start
  fi
  [ "$(<"$temp/start/state/web")" = stopped ] || fail "start left web running after ${failure_mode} failure"
  [ "$(<"$temp/start/state/db")" = stopped ] || fail "start left DB running after ${failure_mode} failure"
done
pass 'real start script synchronously stops web and DB after runtime or partial-start failure'

printf 'running\n' >"$temp/start/state/web"; printf 'running\n' >"$temp/start/state/db"; : >"$temp/start/log"
touch "$temp/start/state/force-compose-stop-web"
expect_failure env NEXOLAB_TEST_STATE="$temp/start/state" NEXOLAB_TEST_LOG="$temp/start/log" "$temp/start/bin/docker" compose stop web
env NEXOLAB_TEST_STATE="$temp/start/state" NEXOLAB_TEST_LOG="$temp/start/log" "$temp/start/bin/docker" stop --time 10 owasp2025-web-1
env NEXOLAB_TEST_STATE="$temp/start/state" NEXOLAB_TEST_LOG="$temp/start/log" "$temp/start/bin/docker" stop --time 10 owasp2025-db-1
[ "$(<"$temp/start/state/web")" = stopped ] || fail 'start direct fallback left web running after a Compose stop failure'
[ "$(<"$temp/start/state/db")" = stopped ] || fail 'start direct fallback left DB running after a Compose stop failure'
pass 'real start script surfaces a stop failure, uses direct fallback, and confirms both services stopped'

exec 8>"$temp/held.lock"; flock 8
printf 'running\n' >"$temp/start/state/web"; printf 'running\n' >"$temp/start/state/db"; : >"$temp/start/log"
expect_failure unshare -Ur --map-root-user env PATH="$temp/start/bin:$PATH" NEXOLAB_PROJECT_DIR="$temp/start/project" \
  NEXOLAB_LIFECYCLE_LOCK_FILE="$temp/held.lock" NEXOLAB_LIFECYCLE_LOCK_TIMEOUT_SECONDS=1 "$START"
expect_failure env NEXOLAB_LIFECYCLE_LOCK_FILE="$temp/held.lock" NEXOLAB_LIFECYCLE_LOCK_TIMEOUT_SECONDS=1 "$RESET" reset
expect_failure env NEXOLAB_LIFECYCLE_LOCK_FILE="$temp/held.lock" NEXOLAB_LIFECYCLE_LOCK_TIMEOUT_SECONDS=1 "$ROLLBACK" /does/not/exist rollback
[ ! -s "$temp/start/log" ] || fail 'start mutated Docker while lifecycle lock was held'
pass 'start, reset, and rollback share lifecycle lock contention behavior'

python3 - "$START" "$RESET" "$ROLLBACK" <<'PY'
import sys
for path in sys.argv[1:]:
    source = open(path, encoding="utf-8").read()
    assert 'readonly LOCK_FILE="${NEXOLAB_LIFECYCLE_LOCK_FILE:-/run/nexolab-lifecycle.lock}"' in source
    assert 'NEXOLAB_LOCK_FILE' not in source
PY
pass 'all lifecycle scripts resolve the same default lock without legacy divergence'

# shellcheck disable=SC2016 # The child shell expands its sourced firewall variables.
expect_failure env PATH="$temp/mocks/bin:$PATH" NEXOLAB_TEST_DUPLICATE_ROLE=1 bash -c 'source "$1"; network_id frontend' -- "$FIREWALL"
pass 'firewall rejects duplicate project network roles rather than selecting the first match'

mkdir -p "$temp/firewall/bin" "$temp/firewall/state"
cat >"$temp/firewall/bin/ip" <<'SH'
#!/usr/bin/env bash
exit 0
SH
cat >"$temp/firewall/bin/iptables" <<'SH'
#!/usr/bin/env bash
printf '%s\n' "$*" >>"$NEXOLAB_TEST_LOG"
if [[ "${NEXOLAB_TEST_FAIL:-}" = docker-user-jump && "$*" == *'-I DOCKER-USER 1'* ]]; then
  exit 1
fi
if [[ "${NEXOLAB_TEST_MISSING_INGRESS_DROP:-}" = frontend && "$*" == *'-C '* && "$*" == *'-i br-1405359a5884 -m conntrack --ctstate NEW -j DROP'* ]]; then
  exit 1
fi
if [[ "${NEXOLAB_TEST_MISSING_INGRESS_DROP:-}" = backend && "$*" == *'-C '* && "$*" == *'-i br-f2c1191a730b -m conntrack --ctstate NEW -j DROP'* ]]; then
  exit 1
fi
SH
chmod 0755 "$temp/firewall/bin/"*
: >"$temp/firewall/iptables.log"
env PATH="$temp/firewall/bin:$temp/mocks/bin:$PATH" NEXOLAB_TEST_LOG="$temp/firewall/iptables.log" \
  NEXOLAB_FIREWALL_STATE_DIR="$temp/firewall/state" "$FIREWALL" apply
python3 - "$temp/firewall/iptables.log" <<'PY'
import sys
log = open(sys.argv[1], encoding="utf-8").read()
assert '-i br-1405359a5884 -o br-f2c1191a730b -p tcp --dport 3306' in log
assert '-i br-1405359a5884 -m conntrack --ctstate NEW -j DROP' in log
assert '-i br-f2c1191a730b -m conntrack --ctstate NEW -j DROP' in log
assert '-o br-1405359a5884 -m conntrack --ctstate NEW -j DROP' in log
assert '-o br-f2c1191a730b -m conntrack --ctstate NEW -j DROP' in log
assert '-i lo -o br-front -p tcp --dport 80' not in log
PY
pass 'firewall generator permits only loopback-to-web and web-to-DB while blocking direct bridge publishes'

for ingress in frontend backend; do
  expect_failure env PATH="$temp/firewall/bin:$temp/mocks/bin:$PATH" NEXOLAB_TEST_LOG="$temp/firewall/iptables.log" \
    NEXOLAB_FIREWALL_STATE_DIR="$temp/firewall/state" NEXOLAB_TEST_MISSING_INGRESS_DROP="$ingress" "$FIREWALL" verify
done
pass 'firewall verification rejects missing frontend and backend ingress-drop rules'

: >"$temp/firewall/iptables.log"
expect_failure env PATH="$temp/firewall/bin:$temp/mocks/bin:$PATH" NEXOLAB_TEST_LOG="$temp/firewall/iptables.log" \
  NEXOLAB_FIREWALL_STATE_DIR="$temp/firewall/state" NEXOLAB_TEST_FAIL=docker-user-jump "$FIREWALL" apply
python3 - "$temp/firewall/iptables.log" <<'PY'
import sys
log = open(sys.argv[1], encoding="utf-8").read()
assert '-I INPUT 1' in log and '-I DOCKER-USER 1' in log
assert ' -D ' not in f' {log} '
PY
pass 'firewall installation failure retains the prior restrictive generation'

# Drive the complete rollback path through the real script. Host-mutating
# commands are mocked, while its runtime gate fails and must contain both services.
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
for file in nexolab.guajilodev.com nexolab-nginx-upstream nexolab-upstream.conf nexolab-reset nexolab-firewall nexolab-storage nexolab-runtime-verify nexolab-firewall.service nexolab-start nexolab-start.service nexolab-report-failure nexolab-containment-failure@.service nexolab-reset.cron nexolab-reset.logrotate nexolab.slice nexolab-web.slice nexolab-db.slice nexolab-storage.env nexolab-db-storage.conf nexolab-containment.logrotate; do
  : >"$temp/rollback/snapshot/host/$file"
done
printf 'SELECT 1;\n' | gzip >"$temp/rollback/snapshot/owasp2025_nexo_db.sql.gz"
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
    esac ;;
  exec) printf '1\n' ;;
  stop) case "${!#}" in "$NEXOLAB_TEST_WEB_CONTAINER") printf 'stopped\n' >"$NEXOLAB_TEST_STATE/web" ;; "$NEXOLAB_TEST_DB_CONTAINER") printf 'stopped\n' >"$NEXOLAB_TEST_STATE/db" ;; esac ;;
  load|tag|image) exit 0 ;;
  compose)
    case " $* " in
      *' start web '*) printf 'running\n' >"$NEXOLAB_TEST_STATE/web" ;;
      *' stop web '*) printf 'stopped\n' >"$NEXOLAB_TEST_STATE/web" ;;
      *' start db '*) printf 'running\n' >"$NEXOLAB_TEST_STATE/db" ;;
      *' stop db '*) printf 'stopped\n' >"$NEXOLAB_TEST_STATE/db" ;;
    esac ;;
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
chmod 0755 "$temp/rollback/bin/"*
printf 'unknown\n' >"$temp/rollback/web"; printf 'unknown\n' >"$temp/rollback/db"
expect_failure unshare -Ur --map-root-user env NEXOLAB_TEST_STATE="$temp/rollback" \
  PATH="$temp/rollback/bin:$PATH" NEXOLAB_PROJECT_DIR="$temp/rollback/project" \
  NEXOLAB_SECRETS_FILE="$temp/rollback/secrets.env" NEXOLAB_FIREWALL_BIN="$temp/rollback/bin/ok" \
  NEXOLAB_NGINX_UPSTREAM_BIN="$temp/rollback/bin/ok" NEXOLAB_STORAGE_BIN="$temp/rollback/bin/ok" \
  NEXOLAB_RESET_BIN="$temp/rollback/bin/ok" NEXOLAB_RUNTIME_VERIFY_BIN="$temp/rollback/bin/runtime-fail" \
  NEXOLAB_TEST_WEB_CONTAINER=owasp2025-web-1 NEXOLAB_TEST_DB_CONTAINER=owasp2025-db-1 \
  "$ROLLBACK" "$temp/rollback/snapshot" rollback
pass 'real rollback script stops and confirms web and DB after failed runtime verification'

cat >"$temp/rollback/bin/runtime-ok" <<'SH'
#!/usr/bin/env bash
exit 0
SH
cat >"$temp/rollback/bin/upstream-fail-verify" <<'SH'
#!/usr/bin/env bash
printf 'upstream %s\n' "$*" >>"$NEXOLAB_TEST_STATE/upstream.log"
[ "$1" != verify ]
SH
chmod 0755 "$temp/rollback/bin/runtime-ok" "$temp/rollback/bin/upstream-fail-verify"
printf 'unknown\n' >"$temp/rollback/web"; printf 'unknown\n' >"$temp/rollback/db"; : >"$temp/rollback/upstream.log"
expect_failure unshare -Ur --map-root-user env NEXOLAB_TEST_STATE="$temp/rollback" \
  PATH="$temp/rollback/bin:$PATH" NEXOLAB_PROJECT_DIR="$temp/rollback/project" \
  NEXOLAB_SECRETS_FILE="$temp/rollback/secrets.env" NEXOLAB_FIREWALL_BIN="$temp/rollback/bin/ok" \
  NEXOLAB_NGINX_UPSTREAM_BIN="$temp/rollback/bin/upstream-fail-verify" NEXOLAB_STORAGE_BIN="$temp/rollback/bin/ok" \
  NEXOLAB_RESET_BIN="$temp/rollback/bin/ok" NEXOLAB_RUNTIME_VERIFY_BIN="$temp/rollback/bin/runtime-ok" \
  NEXOLAB_LIFECYCLE_LOCK_FILE="$temp/rollback/lifecycle.lock" \
  NEXOLAB_TEST_WEB_CONTAINER=owasp2025-web-1 NEXOLAB_TEST_DB_CONTAINER=owasp2025-db-1 \
  "$ROLLBACK" "$temp/rollback/snapshot" rollback
python3 - "$temp/rollback/upstream.log" <<'PY'
import sys
lines = open(sys.argv[1], encoding="utf-8").read().splitlines()
assert lines.count("upstream quarantine") >= 2, lines
assert lines.index("upstream refresh") < lines.index("upstream verify"), lines
assert lines[-1] == "upstream quarantine", lines
PY
pass 'rollback restores nginx quarantine after a post-refresh upstream verification failure'

# Use disposable real MariaDB and web containers for reset. A command wrapper
# injects failures without weakening the reset implementation itself.
reset_id="nexolab-reset-test-$$-$RANDOM"
reset_db="${reset_id}-db"
reset_web="${reset_id}-web"
cleanup_reset_test() { docker rm -f "$reset_db" "$reset_web" >/dev/null 2>&1 || true; }
trap 'cleanup_reset_test; rm -rf "$temp"' EXIT
docker run -d --name "$reset_db" --memory=768m --memory-swap=768m --cpus=1 --pids-limit=128 \
  -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 mariadb:10.11 >/dev/null
# mariadb-admin can reach the temporary initialization server. Wait until the
# entrypoint reports initialization complete, then prove the final server can
# execute an in-container query.
for _ in $(seq 1 120); do
  if docker logs "$reset_db" 2>&1 | grep -Fq 'MariaDB init process done. Ready for start up.' && \
    docker exec "$reset_db" mariadb --protocol=socket -uroot -Nse 'SELECT 1' >/dev/null 2>&1; then
    break
  fi
  sleep 1
done
docker logs "$reset_db" 2>&1 | grep -Fq 'MariaDB init process done. Ready for start up.' && \
  docker exec "$reset_db" mariadb --protocol=socket -uroot -Nse 'SELECT 1' >/dev/null 2>&1 || fail 'disposable MariaDB final server did not become ready after initialization'
docker exec "$reset_db" mariadb --protocol=socket -uroot -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'reset_root'; CREATE DATABASE validation_db; FLUSH PRIVILEGES;"
docker exec -i -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db <"${PROJECT_DIR}/mysql/init.sql"
docker create --name "$reset_web" owasp2025-web:latest >/dev/null
mkdir -p "$temp/reset/bin"
cat >"$temp/reset/bin/docker" <<'SH'
#!/usr/bin/env bash
if [ "${NEXOLAB_TEST_FAIL_WRITABLE_CLEANUP:-}" = 1 ] && [[ " $* " == *' up --no-start --no-build --force-recreate --no-deps web '* ]]; then
  : >"$NEXOLAB_TEST_STATE/writable-cleanup-failed"
  exit 96
fi
if [ "${NEXOLAB_TEST_HANG_STOP:-}" = 1 ] && [[ " $* " == *" stop --time 10 ${NEXOLAB_WEB_CONTAINER} "* ]]; then
  trap ': >"$NEXOLAB_TEST_STATE/stop-timeout-term"; trap "" TERM' TERM
  while :; do :; done
fi
if [ "${NEXOLAB_TEST_OUTER_TIMEOUT_DESCENDANT:-}" = 1 ] && [ ! -e "$NEXOLAB_TEST_STATE/outer-timeout-injected" ] && \
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
if [ "${NEXOLAB_TEST_REAL_TMPFS_CLEANUP:-}" = 1 ] && [[ " $* " == *' up --no-start --no-build --force-recreate --no-deps web '* ]]; then
  "$NEXOLAB_REAL_DOCKER" rm -f "$NEXOLAB_WEB_CONTAINER" >/dev/null 2>&1 || true
  exec "$NEXOLAB_REAL_DOCKER" create --name "$NEXOLAB_WEB_CONTAINER" \
    --tmpfs /var/www/html/a02_admin/uploads:size=50m,mode=1777,noexec,nosuid \
    --tmpfs /var/www/html/a09_actividad/logs:size=20m,mode=1777,noexec,nosuid owasp2025-web:latest
fi
if [ "${NEXOLAB_TEST_FAIL_WEB_VALIDATION:-}" = 1 ] && [ -e "$NEXOLAB_TEST_STATE/web-started-during-reset" ] && \
  [ ! -e "$NEXOLAB_TEST_STATE/web-validation-failed" ] && [[ " $* " == *" inspect --format {{.State.Running}} ${NEXOLAB_WEB_CONTAINER} "* ]]; then
  : >"$NEXOLAB_TEST_STATE/web-validation-failed"
  printf 'false\n'
  exit 0
fi
if [ "${NEXOLAB_TEST_FAIL_WEB_INSPECT:-}" = 1 ] && [ ! -e "$NEXOLAB_TEST_STATE/web-inspect-failed" ] && \
  [[ " $* " == *" inspect --format {{.State.Running}} ${NEXOLAB_WEB_CONTAINER} "* ]]; then
  : >"$NEXOLAB_TEST_STATE/web-inspect-failed"
  exit 98
fi
if [[ "$*" == "start ${NEXOLAB_WEB_CONTAINER}" ]]; then : >"$NEXOLAB_TEST_STATE/web-started-during-reset"; fi
if [ "${NEXOLAB_TEST_FAIL_RENAME:-}" = 1 ] && [[ "$*" == *'RENAME TABLE '* ]]; then : >"$NEXOLAB_TEST_STATE/rename-failed"; exit 97; fi
if [ "${NEXOLAB_TEST_FAIL_CLEANUP_DROP:-}" = 1 ] && [[ "$*" == *'DROP DATABASE IF EXISTS `nexolab_reset_'* ]]; then exit 99; fi
exec "$NEXOLAB_REAL_DOCKER" "$@"
SH
cat >"$temp/reset/bin/ok" <<'SH'
#!/usr/bin/env bash
exit 0
SH
chmod 0755 "$temp/reset/bin/docker" "$temp/reset/bin/ok"
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
  NEXOLAB_LIFECYCLE_LOCK_FILE="$temp/reset/reset.lock"
  NEXOLAB_RECOVERY_DIR="$temp/reset/recovery"
  NEXOLAB_FIREWALL_BIN="$temp/reset/bin/ok"
  NEXOLAB_RUNTIME_VERIFY_BIN="$temp/reset/bin/ok"
  NEXOLAB_NGINX_UPSTREAM_BIN="$temp/reset/bin/ok"
  NEXOLAB_TEST_STATE="$temp/reset"
)
run_reset() { unshare -Ur --map-root-user env "${reset_env[@]}" "$@" "$RESET" reset; }
ensure_reset_db() {
  docker start "$reset_db" >/dev/null 2>&1 || true
  for _ in $(seq 1 30); do
    docker exec "$reset_db" mariadb-admin --protocol=socket -uroot ping --silent >/dev/null 2>&1 && return 0
    sleep 1
  done
  fail 'disposable MariaDB did not restart after reset containment'
}
assert_recovery_permissions() {
  local archive metadata
  archive="$1"
  metadata="$(unshare -Ur --map-root-user /usr/bin/stat -c '%u:%a' "$temp/reset/recovery" "$archive")"
  [ "$metadata" = $'0:700\n0:600' ] || fail "recovery archive permissions/ownership are not actual root-only 0700/0600: ${metadata}"
}
docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot -e "CREATE USER 'validation_user'@'localhost' IDENTIFIED BY 'validation_password'; GRANT ALL PRIVILEGES ON validation_db.* TO 'validation_user'@'localhost'; FLUSH PRIVILEGES;"
docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db -e '
  CREATE TABLE attacker_staging (id INT); CREATE VIEW attacker_view AS SELECT 1 AS id;
  CREATE PROCEDURE attacker_proc() SELECT 1; CREATE EVENT attacker_event ON SCHEDULE EVERY 1 DAY DO SELECT 1;
  CREATE TRIGGER attacker_trigger BEFORE INSERT ON users FOR EACH ROW SET NEW.username = NEW.username;'
run_reset || fail 'real reset failed against disposable MariaDB'
remaining_objects="$(docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db -Nse "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'validation_db' AND TABLE_TYPE = 'VIEW'; SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = 'validation_db'; SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA = 'validation_db'; SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = 'validation_db'; SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'validation_db' AND TABLE_NAME = 'attacker_staging';")"
[ "$remaining_objects" = $'0\n0\n0\n0\n0' ] || fail 'reset retained attacker-created non-table or staging objects'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'reset started web that was originally stopped'
[ -z "$(docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot -Nse "SHOW DATABASES LIKE 'nexolab_reset_%'")" ] || fail 'successful reset retained temporary reset schemas'
[ -z "$(/usr/bin/find "$temp/reset/recovery" -mindepth 1 -maxdepth 1 -type f -print -quit)" ] || fail 'successful reset retained its recovery archive before all success gates completed'
pass 'real reset removes views, routines, events, triggers, and staging tables without starting stopped web'

cat >"$temp/reset/bin/runtime-reject-extra-web" <<'SH'
#!/usr/bin/env bash
if [ "$1:${2:-}" = post-start:all ]; then
  : >"$NEXOLAB_TEST_STATE/running-web-attachment-rejected"
  exit 88
fi
exit 0
SH
chmod 0755 "$temp/reset/bin/runtime-reject-extra-web"
mkdir -p "$temp/reset/recovery"
printf 'prior recovery archive\n' >"$temp/reset/recovery/prior-recovery.sql.gz"
docker start "$reset_web" >/dev/null
expect_failure run_reset NEXOLAB_RUNTIME_VERIFY_BIN="$temp/reset/bin/runtime-reject-extra-web"
[ -e "$temp/reset/running-web-attachment-rejected" ] || fail 'reset did not validate a running web attachment set before staging'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'reset left a running web after rejecting its attachment set'
ensure_reset_db
[ -z "$(docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot -Nse "SHOW DATABASES LIKE 'nexolab_reset_%'")" ] || fail 'reset staged a database before rejecting the running web attachment set'
[ -f "$temp/reset/recovery/prior-recovery.sql.gz" ] || fail 'reset rejection removed the recovery archive'
pass 'reset rejects an extra running web attachment before staging and fails closed'

ensure_reset_db
rm -f -- "$temp/reset/recovery"/* "$temp/reset/web-inspect-failed"
printf 'prior recovery archive\n' >"$temp/reset/recovery/prior-recovery.sql.gz"
docker start "$reset_web" >/dev/null
expect_failure run_reset NEXOLAB_TEST_FAIL_WEB_INSPECT=1
[ -e "$temp/reset/web-inspect-failed" ] || fail 'reset did not exercise the injected web inspection failure'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'inspect failure left a running web uncontained'
ensure_reset_db
[ -z "$(docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot -Nse "SHOW DATABASES LIKE 'nexolab_reset_%'")" ] || fail 'inspect failure reached staging import'
[ -f "$temp/reset/recovery/prior-recovery.sql.gz" ] || fail 'inspect failure removed the recovery archive'
pass 'reset fails closed on a running web inspection error before staging import'

docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db -e '
  CREATE TABLE attacker_recovery_table (id INT); CREATE VIEW attacker_recovery_view AS SELECT 1 AS id;
  CREATE PROCEDURE attacker_recovery_proc() SELECT 1; CREATE EVENT attacker_recovery_event ON SCHEDULE EVERY 1 DAY DO SELECT 1;
  CREATE TRIGGER attacker_recovery_trigger BEFORE INSERT ON users FOR EACH ROW SET NEW.username = NEW.username;'
docker start "$reset_web" >/dev/null
rm -f "$temp/reset/web-started-during-reset"
expect_failure run_reset NEXOLAB_TEST_FAIL_RENAME=1
[ ! -e "$temp/reset/web-started-during-reset" ] || fail 'post-replacement recovery restarted web before temporary schemas were proven removed'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'post-replacement recovery left web running'
[ -z "$(docker exec -e MYSQL_PWD=validation_password "$reset_db" mariadb --protocol=socket -uvalidation_user -Nse "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_NAME LIKE 'attacker_recovery_%'")" ] || fail 'post-replacement recovery exposed attacker objects'
[ -z "$(docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot -Nse "SHOW DATABASES LIKE 'nexolab_reset_%'")" ] || fail 'post-replacement recovery retained a temporary schema'
recovery_archives=("$temp/reset/recovery"/*.sql.gz.*)
[ -f "${recovery_archives[0]}" ] || fail 'post-replacement recovery did not preserve an external operator-only archive'
gzip -t "${recovery_archives[0]}" || fail 'post-replacement recovery archive is corrupt'
assert_recovery_permissions "${recovery_archives[0]}"
pass 'post-replacement recovery removes temporary schemas, retains only an external archive, and keeps web stopped'

ensure_reset_db
rm -f -- "$temp/reset/recovery"/*
docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot -e 'DROP DATABASE validation_db; CREATE DATABASE validation_db;'
docker exec -i -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db <"${PROJECT_DIR}/mysql/init.sql"
docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db -e 'CREATE VIEW cleanup_view AS SELECT 1 AS id;'
docker start "$reset_web" >/dev/null
expect_failure run_reset NEXOLAB_TEST_FAIL_CLEANUP_DROP=1
[ ! -e "$temp/reset/web-started-during-reset" ] || fail 'cleanup-drop failure restarted web'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'cleanup-drop failure started web'
pass 'real reset fails closed when temporary-schema cleanup cannot remove attacker state'

ensure_reset_db
rm -f -- "$temp/reset/recovery"/* "$temp/reset/web-started-during-reset" "$temp/reset/web-validation-failed"
docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot -e 'DROP DATABASE validation_db; CREATE DATABASE validation_db;'
docker exec -i -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db <"${PROJECT_DIR}/mysql/init.sql"
docker start "$reset_web" >/dev/null
run_reset NEXOLAB_TEST_REAL_TMPFS_CLEANUP=1 || fail 'reset failed while recreating the real A09 tmpfs directory mount'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = true ] || fail 'successful tmpfs cleanup did not restart previously-running web'
tmpfs_mounts="$(docker inspect --format '{{json .HostConfig.Tmpfs}}' "$reset_web")"
[[ "$tmpfs_mounts" == *'"/var/www/html/a09_actividad/logs":'* && "$tmpfs_mounts" != *'"/var/www/html/a09_actividad/logs/app.log":'* ]] || fail 'tmpfs cleanup did not verify the real A09 logs directory mount'
pass 'reset accepts the real A09 logs-directory tmpfs after stopping and recreating web'

rm -f -- "$temp/reset/recovery"/* "$temp/reset/web-started-during-reset"
expect_failure run_reset NEXOLAB_TEST_REAL_TMPFS_CLEANUP=1 NEXOLAB_TEST_FAIL_WEB_VALIDATION=1
[ -e "$temp/reset/web-started-during-reset" ] || fail 'web validation failure injection did not reach the restart gate'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'web validation failure left web running'
recovery_archives=("$temp/reset/recovery"/*.sql.gz.*)
[ -f "${recovery_archives[0]}" ] || fail 'web validation failure removed the external recovery archive'
assert_recovery_permissions "${recovery_archives[0]}"
pass 'failed web validation fails closed, preserves the archive, and keeps web stopped'

ensure_reset_db
rm -f -- "$temp/reset/recovery"/* "$temp/reset/web-started-during-reset"
docker exec -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot -e 'DROP DATABASE validation_db; CREATE DATABASE validation_db;'
docker exec -i -e MYSQL_PWD=reset_root "$reset_db" mariadb --protocol=socket -uroot validation_db <"${PROJECT_DIR}/mysql/init.sql"
docker start "$reset_web" >/dev/null
expect_failure run_reset NEXOLAB_TEST_FAIL_WRITABLE_CLEANUP=1
[ -f "$temp/reset/writable-cleanup-failed" ] || fail 'writable-cleanup failure injection did not reach the post-archive cleanup gate'
[ ! -e "$temp/reset/web-started-during-reset" ] || fail 'web started before writable cleanup passed'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'writable-cleanup failure left web running'
recovery_archives=("$temp/reset/recovery"/*.sql.gz.*)
[ -f "${recovery_archives[0]}" ] || fail 'writable-cleanup failure removed the external recovery archive'
assert_recovery_permissions "${recovery_archives[0]}"
pass 'writable-path cleanup failure preserves the archive and keeps web stopped before any web start'

ensure_reset_db
rm -f -- "$temp/reset/recovery"/* "$temp/reset/stop-timeout-term"
docker start "$reset_web" >/dev/null
timeout_started="$(date +%s)"
expect_failure run_reset NEXOLAB_TEST_HANG_STOP=1 NEXOLAB_COMMAND_TIMEOUT_SECONDS=1 NEXOLAB_TERM_GRACE_SECONDS=1
timeout_elapsed="$(( $(date +%s) - timeout_started ))"
[ "$timeout_elapsed" -lt 8 ] || fail "Docker timeout escalation hung for ${timeout_elapsed}s"
[ -f "$temp/reset/stop-timeout-term" ] || fail 'Docker timeout did not send TERM before KILL escalation'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'Docker timeout escalation did not verify web containment'
pass 'Docker TERM/KILL timeout escalation fails closed without hanging'

ensure_reset_db
rm -f -- "$temp/reset/outer-timeout-"*
docker start "$reset_web" >/dev/null
outer_timeout_started="$(date +%s)"
expect_failure run_reset NEXOLAB_TEST_OUTER_TIMEOUT_DESCENDANT=1 NEXOLAB_RESET_TIMEOUT_SECONDS=1 NEXOLAB_COMMAND_TIMEOUT_SECONDS=20 NEXOLAB_TERM_GRACE_SECONDS=1
outer_timeout_elapsed="$(( $(date +%s) - outer_timeout_started ))"
[ "$outer_timeout_elapsed" -lt 8 ] || fail "outer reset timeout escalation hung for ${outer_timeout_elapsed}s"
[ -f "$temp/reset/outer-timeout-worker-term" ] || fail 'outer timeout did not TERM the reset worker process group'
[ -f "$temp/reset/outer-timeout-descendant-term" ] || fail 'outer timeout did not TERM the Docker-command descendant'
sleep 4
[ ! -e "$temp/reset/outer-timeout-descendant-survived" ] || fail 'outer timeout left a Docker-command descendant alive after reset returned'
[ "$(docker inspect --format '{{.State.Running}}' "$reset_web")" = false ] || fail 'outer timeout did not keep web stopped'
pass 'outer timeout TERM/KILLs the full worker process group with no surviving Docker descendant'

printf 'Production-only manual gates: host firewall, cgroup/storage/device topology, TLS, public HTTPS, and real Docker DNS/egress.\n'
printf 'RESULT: %d containment scenarios passed\n' "$passed"
