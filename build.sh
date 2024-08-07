freepbxip="172.18.0.20"
network_interface="freepbxint"

# INSTALL FREEPBX
if [[  "$*" == *"--install-freepbx"*  ]]; then
    docker exec -it freepbx bash /usr/local/bin/credentials.sh --install

# CLEAN
elif [[  "$*" == *"--clean-all"*  ]]; then
  docker container stop freepbx-docker-db-1 && docker container rm freepbx-docker-db-1
  docker container stop freepbx-docker-vault-transit-1 && docker container rm freepbx-docker-vault-transit-1 
  docker container stop vault && docker container rm vault
  docker container stop sidecar-freepbx && docker container rm sidecar-freepbx
  docker container stop freepbx && docker container rm freepbx
  docker volume rm vault
  docker volume rm var_run
  docker volume rm var_data
  docker volume rm usr_data
  docker volume rm etc_data
  docker volume rm asterisk_home
  docker volume rm freepbx-docker_mysql_data
  docker volume rm freepbx-docker_vault-transit
  docker network rm freepbx-docker_defaultnet

# MACOS HOST CASE
elif [[  "$OSTYPE" == "darwin"*   ]]; then
    echo "mac os detected, currently not supported"

# BUILD AND RUN (no arguments passed)
else
    # Vault transit image
    docker build -t vault-transit:custom vault-transit/

    # Vault
    docker build -t vault:custom vault/

    # Sidecar
    docker build -t sidecar sidecar/

    # Database and Vault transit + run
    docker compose up -d --build

    echo "Configuring firewall rules for RTP ports..."
    # OPEN RTP PORTS ON IPTABLES FOR FREEPBX FOR LINUX HOSTS
    if [[  "$OSTYPE" == "linux-gnu"*  ]]; then
        # if DOCKER chain rule does not exist, add it
        if ! iptables -L DOCKER -n -v | grep "udp dpts:16384:32767"; then
            iptables -A DOCKER -d "$freepbxip" ! -i "$network_interface" -o "$network_interface" -p udp -m udp --dport 16384:32767 -j ACCEPT
            echo "rule added!"
        else
            echo "rule for DOCKER chain already exists, skipping..."
            echo ""
        fi

        # if POSTROUTING chain rule does not exist, add it
        if ! iptables -L POSTROUTING -t nat -n -v | grep "udp dpts:16384:32767"; then
            iptables -t nat -A POSTROUTING -s "$freepbxip" -d "$freepbxip" -p udp -m udp --dport 16384:32767 -j MASQUERADE
            echo "rule added!"
        else
            echo "rule for POSTROUTING chain already exists, skipping..."
            echo ""
        fi

        # if NAT chain rule does not exist, add it
        if ! iptables -L -t nat -n -v | grep "udp dpts:16384:32767 to:$freepbxip:16384-32767"; then
            iptables -t nat -A DOCKER ! -i "$network_interface" -p udp -m udp --dport 16384:32767 -j DNAT --to-destination "$freepbxip":16384-32767
            echo "rule added!"
        else
            echo "rule for NAT chain already exists, skipping..."
            echo ""
        fi

    # OPEN RTP PORTS ON IPTABLES FOR FREEPBX FOR WINDOWS HOSTS
    elif [[ "$OSTYPE" == "cygwin" || "$OSTYPE" == "msys" || "$OSTYPE" == "MINGW" ]]; then
        netsh advfirewall firewall add rule name="Allow UDP Port Range" dir=in action=allow protocol=UDP localport=16384-32767 remoteport=16384-32767 localip=127.0.0.1 remoteip="$freepbxip"
        netsh_exit_code=$?
        if [[  $netsh_exit_code -eq 0  ]]; then
            echo "RTP ports allowed on Windows"
        fi
    fi

fi
