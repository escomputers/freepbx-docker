#!/bin/bash

network_name="freepbx-docker_default"
container_name="freepbx-docker-freepbx-1"
network_interface="br-b40ead5142fa"

ipv4_address=$(docker network inspect "$network_name" | jq -r '.[].Containers | to_entries[] | select(.value.Name == "'"${container_name}"'") | .value.IPv4Address' | cut -d'/' -f1)

# if DOCKER chain rule does not exist, add it
if ! iptables -L DOCKER -n -v | grep "udp dpts:16384:32767"; then
iptables -A DOCKER -d "$ipv4_address" ! -i "$network_interface" -o "$network_interface" -p udp -m udp --dport 16384:32767 -j ACCEPT
else
echo "rule for DOCKER chain already exists, skipping..."
echo ""
fi

# if POSTROUTING chain rule does not exist, add it
if ! iptables -L POSTROUTING -t nat -n -v | grep "udp dpts:16384:32767"; then
iptables -A POSTROUTING -s "$ipv4_address" -d "$ipv4_address" -p udp -m udp --dport 16384:32767 -j MASQUERADE
else
echo "rule for POSTROUTING chain already exists, skipping..."
echo ""
fi


# if NAT chain rule does not exist, add it
if ! iptables -L -t nat -n -v | grep "udp dpts:16384:32767 to:$ipv4_address:16384-32767"; then
iptables -A DOCKER ! -i "$network_interface" -p udp -m udp --dport 16384:32767 -j DNAT --to-destination "$ipv4_address":16384-32767
else
echo "rule for NAT chain already exists, skipping..."
echo ""
fi

# build
docker compose up -d --build

