freepbxip="172.18.0.20"
network_interface="freepbxint"


# INSTALL FREEPBX
if [[  "$*" == *"--install-freepbx"*  ]]; then
    docker compose exec -it freepbx php /usr/src/freepbx/install -n --dbuser=freepbxuser --dbpass="$(cat freepbxuser_password.txt)" --dbhost=db

# CLEAN
elif [[  "$*" == *"--clean-all"*  ]]; then
  docker container stop freepbx-docker-db-1 && docker container rm freepbx-docker-db-1
  docker container stop freepbx-docker-freepbx-1 && docker container rm freepbx-docker-freepbx-1
  docker volume rm freepbx-docker_var_data
  docker volume rm freepbx-docker_usr_data
  docker volume rm freepbx-docker_etc_data
  docker volume rm freepbx-docker_asterisk_home
  docker volume rm freepbx-docker_mysql_data
  docker network rm freepbx-docker_defaultnet

# MACOS HOST CASE
elif [[  "$OSTYPE" == "darwin"*   ]]; then
    echo "mac os detected, currently not supported"

# BUILD AND RUN + ADD IPTABLES RULES (no arguments passed)
else
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
