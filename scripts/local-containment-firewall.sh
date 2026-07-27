#!/usr/bin/env bash
# Local-only, event-driven containment for this Compose project.

set -euo pipefail

readonly PROJECT="owasp2025"
readonly INPUT_CHAIN="OWASP2025-LOCAL-INPUT"
readonly FORWARD_CHAIN="OWASP2025-LOCAL-FORWARD"
readonly TAG="owasp2025-local-containment"
readonly UNIT_NAME="owasp2025-local-containment.service"
readonly INSTALL_PATH="/usr/local/lib/owasp2025/local-containment-firewall.sh"

usage() {
  printf 'Usage: %s {apply|remove-rules|verify|watch|install|remove}\n' "$0" >&2
}

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

have_passwordless_privilege() { [ "${EUID}" -eq 0 ] || sudo -n true 2>/dev/null; }
require_passwordless_privilege() {
  have_passwordless_privilege || fail 'Passwordless sudo is unavailable; no host firewall rules were changed.'
}
privileged() { if [ "${EUID}" -eq 0 ]; then "$@"; else sudo -n "$@"; fi; }
ipt() { local bin="$1"; shift; privileged "$bin" -w "$@"; }

network_id() {
  local role="$1" id
  while IFS= read -r id; do
    [ -n "$id" ] || continue
    if [ "$(docker network inspect "$id" --format '{{ index .Labels "com.docker.compose.network" }}')" = "$role" ]; then
      printf '%s\n' "$id"
      return 0
    fi
  done < <(docker network ls -q --filter "label=com.docker.compose.project=${PROJECT}")
  return 1
}

bridge_for_network() {
  local id="$1" bridge
  bridge="$(docker network inspect "$id" --format '{{ index .Options "com.docker.network.bridge.name" }}')"
  [ -n "$bridge" ] || bridge="br-${id:0:12}"
  ip link show dev "$bridge" >/dev/null 2>&1 || return 1
  printf '%s\n' "$bridge"
}

discover_bridges() {
  FRONTEND_BRIDGE="$(bridge_for_network "$(network_id frontend)")" || return 1
  BACKEND_BRIDGE="$(bridge_for_network "$(network_id backend)")" || return 1
}

firewall_binaries() {
  command -v iptables >/dev/null || fail 'iptables is required but was not found.'
  printf '%s\n' iptables
  if command -v ip6tables >/dev/null && ipt ip6tables -S DOCKER-USER >/dev/null 2>&1; then
    printf '%s\n' ip6tables
  fi
}

ensure_chain() {
  local bin="$1" chain="$2"
  ipt "$bin" -nL "$chain" >/dev/null 2>&1 || ipt "$bin" -N "$chain"
}
ensure_jump() {
  local bin="$1" parent="$2" chain="$3"
  ipt "$bin" -C "$parent" -m comment --comment "$TAG" -j "$chain" >/dev/null 2>&1 || \
    ipt "$bin" -I "$parent" 1 -m comment --comment "$TAG" -j "$chain"
}

apply_discovered() {
  local bin
  while IFS= read -r bin; do
    ipt "$bin" -S DOCKER-USER >/dev/null 2>&1 || fail "The ${bin} DOCKER-USER chain is unavailable."
    ensure_chain "$bin" "$INPUT_CHAIN"
    ensure_chain "$bin" "$FORWARD_CHAIN"
    ensure_jump "$bin" INPUT "$INPUT_CHAIN"
    ensure_jump "$bin" DOCKER-USER "$FORWARD_CHAIN"
    ipt "$bin" -F "$INPUT_CHAIN"
    ipt "$bin" -A "$INPUT_CHAIN" -i "$FRONTEND_BRIDGE" -j DROP
    ipt "$bin" -A "$INPUT_CHAIN" -i "$BACKEND_BRIDGE" -j DROP
    ipt "$bin" -A "$INPUT_CHAIN" -j RETURN
    ipt "$bin" -F "$FORWARD_CHAIN"
    ipt "$bin" -A "$FORWARD_CHAIN" -m conntrack --ctstate ESTABLISHED,RELATED -j RETURN
    ipt "$bin" -A "$FORWARD_CHAIN" -i "$FRONTEND_BRIDGE" -o "$FRONTEND_BRIDGE" -j RETURN
    ipt "$bin" -A "$FORWARD_CHAIN" -i "$BACKEND_BRIDGE" -o "$BACKEND_BRIDGE" -j RETURN
    ipt "$bin" -A "$FORWARD_CHAIN" -i "$FRONTEND_BRIDGE" -m conntrack --ctstate NEW -j DROP
    ipt "$bin" -A "$FORWARD_CHAIN" -i "$BACKEND_BRIDGE" -m conntrack --ctstate NEW -j DROP
    ipt "$bin" -A "$FORWARD_CHAIN" -j RETURN
  done < <(firewall_binaries)
  printf 'Applied local containment: frontend=%s backend=%s\n' "$FRONTEND_BRIDGE" "$BACKEND_BRIDGE"
}

apply_rules() {
  require_passwordless_privilege
  discover_bridges || fail 'Compose networks are not ready.'
  apply_discovered
}

remove_rules() {
  require_passwordless_privilege
  local bin parent
  while IFS= read -r bin; do
    for parent in INPUT DOCKER-USER; do
      for chain in "$INPUT_CHAIN" "$FORWARD_CHAIN"; do
        while ipt "$bin" -C "$parent" -m comment --comment "$TAG" -j "$chain" >/dev/null 2>&1; do
          ipt "$bin" -D "$parent" -m comment --comment "$TAG" -j "$chain"
        done
      done
    done
    ipt "$bin" -F "$INPUT_CHAIN" 2>/dev/null || true
    ipt "$bin" -X "$INPUT_CHAIN" 2>/dev/null || true
    ipt "$bin" -F "$FORWARD_CHAIN" 2>/dev/null || true
    ipt "$bin" -X "$FORWARD_CHAIN" 2>/dev/null || true
  done < <(firewall_binaries)
}

verify() {
  discover_bridges || fail 'Compose networks are not ready.'
  local bin
  while IFS= read -r bin; do
    ipt "$bin" -C INPUT -m comment --comment "$TAG" -j "$INPUT_CHAIN"
    ipt "$bin" -C DOCKER-USER -m comment --comment "$TAG" -j "$FORWARD_CHAIN"
    ipt "$bin" -C "$FORWARD_CHAIN" -i "$FRONTEND_BRIDGE" -o "$FRONTEND_BRIDGE" -j RETURN
    ipt "$bin" -C "$FORWARD_CHAIN" -i "$BACKEND_BRIDGE" -o "$BACKEND_BRIDGE" -j RETURN
    ipt "$bin" -C "$FORWARD_CHAIN" -i "$FRONTEND_BRIDGE" -m conntrack --ctstate NEW -j DROP
    ipt "$bin" -C "$FORWARD_CHAIN" -i "$BACKEND_BRIDGE" -m conntrack --ctstate NEW -j DROP
  done < <(firewall_binaries)
  printf 'Local containment verification passed: frontend=%s backend=%s\n' "$FRONTEND_BRIDGE" "$BACKEND_BRIDGE"
}

apply_when_ready() {
  if discover_bridges; then apply_discovered; else return 1; fi
}

watch() {
  require_passwordless_privilege
  trap 'exit 0' TERM INT
  while :; do
    apply_when_ready || true
    docker events --filter "label=com.docker.compose.project=${PROJECT}" \
      --filter 'type=container' --filter 'type=network' --format '{{.Type}} {{.Action}}' | \
      while IFS=' ' read -r event_type event_action; do
        case "${event_type}:${event_action}" in
          container:start|container:die|container:destroy|container:create|network:create|network:destroy|network:connect|network:disconnect)
            apply_when_ready || true ;;
        esac
      done || true
    sleep 2
  done
}

install_policy() {
  require_passwordless_privilege
  local source_path
  source_path="$(readlink -f "$0")"
  privileged install -Dm0750 "$source_path" "$INSTALL_PATH"
  privileged tee "/etc/systemd/system/${UNIT_NAME}" >/dev/null <<EOF
[Unit]
Description=OWASP 2025 local Docker containment firewall
After=docker.service
Requires=docker.service

[Service]
Type=simple
ExecStart=${INSTALL_PATH} watch
ExecReload=${INSTALL_PATH} apply
ExecStop=${INSTALL_PATH} remove-rules
Restart=always
RestartSec=2

[Install]
WantedBy=multi-user.target
EOF
  privileged systemctl daemon-reload
  privileged systemctl enable --now "$UNIT_NAME"
}

remove_policy() {
  require_passwordless_privilege
  if privileged test -f "/etc/systemd/system/${UNIT_NAME}"; then
    privileged systemctl disable --now "$UNIT_NAME" || true
    privileged rm -f "/etc/systemd/system/${UNIT_NAME}"
    privileged systemctl daemon-reload
  fi
  remove_rules
  privileged rm -f "$INSTALL_PATH"
}

case "${1:-}" in
  apply) apply_rules ;;
  remove-rules) remove_rules ;;
  verify) verify ;;
  watch) watch ;;
  install) install_policy ;;
  remove) remove_policy ;;
  *) usage; exit 2 ;;
esac
