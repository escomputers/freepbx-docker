#!/bin/bash

# Check if jq is installed
if ! command -v jq &> /dev/null; then
    echo "jq is not installed. Installing..."
    sudo apt update
    sudo apt install -y jq
fi

# build
docker compose up -d --build

# Check the exit code of the build command
build_exit_code=$?
if [ $build_exit_code -eq 0 ]; then

    network_name="defaultnet"
    container_name="freepbx-docker-freepbx-1"
    network_interface="freepbxint"

    ipv4_address=$(docker network inspect "$network_name" | jq -r '.[].Containers | to_entries[] | select(.value.Name == "'"${container_name}"'") | .value.IPv4Address' | cut -d'/' -f1)

    # if DOCKER chain rule does not exist, add it
    if ! iptables -L DOCKER -n -v | grep "udp dpts:16384:32767"; then
        iptables -A DOCKER -d "$ipv4_address" ! -i "$network_interface" -o "$network_interface" -p udp -m udp --dport 16384:32767 -j ACCEPT
        echo "rule added!"
    else
        echo "rule for DOCKER chain already exists, skipping..."
        echo ""
    fi

    # if POSTROUTING chain rule does not exist, add it
    if ! iptables -L POSTROUTING -t nat -n -v | grep "udp dpts:16384:32767"; then
        iptables -t nat -A POSTROUTING -s "$ipv4_address" -d "$ipv4_address" -p udp -m udp --dport 16384:32767 -j MASQUERADE
        echo "rule added!"
    else
        echo "rule for POSTROUTING chain already exists, skipping..."
        echo ""
    fi

    # if NAT chain rule does not exist, add it
    if ! iptables -L -t nat -n -v | grep "udp dpts:16384:32767 to:$ipv4_address:16384-32767"; then
        iptables -t nat -A DOCKER ! -i "$network_interface" -p udp -m udp --dport 16384:32767 -j DNAT --to-destination "$ipv4_address":16384-32767
        echo "rule added!"
    else
        echo "rule for NAT chain already exists, skipping..."
        echo ""
    fi
else
    echo "Build failed with exit code: $build_exit_code. Exiting the script."
    exit 1
fi

