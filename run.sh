#!/usr/bin/env bash

# Resolve the project in the same Docker context used for cleanup.
if [[ $# -eq 1 && "$1" == "--clean-all" ]]; then
  set -o pipefail
  project=$(docker compose config --environment | sed -n 's/^COMPOSE_PROJECT_NAME=//p') || exit 1
  if [[ ! "$project" =~ ^[a-z0-9][a-z0-9_-]*$ ]]; then
    echo "ERROR: Could not determine the Compose project." >&2
    exit 1
  fi
  echo "This will delete Compose project '$project' and its data volumes."
  read -r -p "Continue? (yes/no) " confirmation
  if [[ "$confirmation" == "yes" ]]; then
    exec docker compose -p "$project" down --volumes
  fi
  echo "Cleanup aborted."
  exit 0
fi

for arg in "$@"; do
  if [[ "$arg" == *--clean-all* ]]; then
    echo "Usage: sudo bash run.sh --clean-all" >&2
    exit 1
  fi
done

# Detect the primary egress interface (IPv4) by asking the kernel
# how it would reach the Internet
get_default_iface() {
  ip -o -4 route get 1.1.1.1 2>/dev/null \
    | awk '{for (i=1; i<=NF; i++) if ($i=="dev") {print $(i+1); exit}}'
}

freepbxip="172.18.0.20"
rtp_port_range="16384-32767"
DEFAULT_IFACE="$(get_default_iface)"

if [[ -z "$DEFAULT_IFACE" ]]; then
  echo "ERROR: Could not detect default egress interface." >&2
  exit 1
fi

# Read the optional RTP port range
prev=""
for arg in "$@"; do
  if [[ "$prev" == "--rtp" ]]; then
    requested_rtp="$arg"
    prev=""
    continue
  fi
  case "$arg" in
    --rtp)
      prev="--rtp"
      ;;
  esac
done

if [[ -n "$prev" ]]; then
  echo "ERROR: --rtp requires a value like 10000-20000" >&2
  exit 1
fi

if [[ ${requested_rtp+x} ]]; then
  if [[ ! "$requested_rtp" =~ ^([0-9]{1,5})-([0-9]{1,5})$ ]]; then
    echo "ERROR: Expected --rtp START-END with ports between 1 and 65535." >&2
    exit 1
  fi
  start_port=$((10#${BASH_REMATCH[1]}))
  end_port=$((10#${BASH_REMATCH[2]}))
  if (( start_port < 1 || end_port > 65535 || start_port >= end_port )); then
    echo "ERROR: Invalid RTP range '$requested_rtp': expected 1 <= START < END <= 65535." >&2
    exit 1
  fi
  rtp_port_range="$start_port-$end_port"
fi

# Install FreePBX, then restart it to start module services.
if [[  "$*" == *"--install-freepbx"*  ]]; then
  # Use an interactive terminal when available. CI runs without one.
  tty_option=-T
  if [[ -t 0 && -t 1 ]]; then
    tty_option=-it
  fi

  sudo docker compose exec "$tty_option" -w /usr/local/src/freepbx freepbx \
    php install -n --dbuser=freepbxuser \
    --dbpass="$(cat freepbxuser_password.txt)" --dbhost=db \
    && sudo docker compose exec "$tty_option" freepbx fwconsole restart

# UNSUPPORTED HOST CASE
elif [[ "$OSTYPE" == "darwin"* || "$OSTYPE" == "cygwin" || "$OSTYPE" == "msys" || "$OSTYPE" == "MINGW" ]]; then
    echo "$OSTYPE currently not supported, please manually configure your host firewall to allow incoming and outgoing UDP traffic on the RTP port range: $rtp_port_range"

# Configure Iptables for RTP ports on Linux
else
    if [[  "$OSTYPE" == "linux-gnu"*  ]]; then
        echo "Configuring iptables rules for RTP ports"

        # Allow incoming UDP traffic to container on the RTP port range on the DOCKER-USER chain 
        # to ensure media packets are accepted before other rules are applied.
        if ! sudo iptables -C DOCKER-USER -p udp -d "$freepbxip" --dport "${rtp_port_range/-/:}" -j ACCEPT 2>/dev/null; then
            if ! sudo iptables -I DOCKER-USER -p udp -d "$freepbxip" --dport "${rtp_port_range/-/:}" -j ACCEPT; then
              echo "ERROR: Could not add the incoming RTP rule in DOCKER-USER." >&2
              exit 1
            fi
            echo "Rule for incoming RTP traffic added!"
        else
            echo "Rule for incoming RTP traffic already in place."
        fi

        # Destination NAT for RTP: forward inbound UDP traffic arriving on the default egress interface
        # and matching the RTP port range to the FreePBX host.
        if ! sudo iptables -t nat -C PREROUTING -i "$DEFAULT_IFACE" -p udp --dport "${rtp_port_range/-/:}" \
            -j DNAT --to-destination "$freepbxip:${rtp_port_range/:/-}" 2>/dev/null; then
            if ! sudo iptables -t nat -A PREROUTING -i "$DEFAULT_IFACE" -p udp --dport "${rtp_port_range/-/:}" \
            -j DNAT --to-destination "$freepbxip:${rtp_port_range/:/-}"; then
              echo "ERROR: Could not add the RTP destination NAT rule in PREROUTING." >&2
              exit 1
            fi
            echo "Rule for Destination NAT RTP added!"
        else
            echo "Rule for Destination NAT RTP already in place."
        fi

        # Start services and wait for their healthchecks to pass
        sudo docker compose up -d --wait --wait-timeout 600 || exit $?
    fi

fi
