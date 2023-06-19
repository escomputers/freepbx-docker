function buildApp {
    # unzip source files
    zip_files=("freepbx.zip" "asterisk-16.30.0.zip")
    target_dirs=("freepbx" "asterisk-16.30.0")

    for ((i=0; i<${#zip_files[@]}; i++)); do
        zip_file="${zip_files[i]}"
        target_dir="${target_dirs[i]}"

        if [ -d "$target_dir" ]; then
            echo "Directory $target_dir already exists. Skipping unzip for $zip_file."
        else
            unzip "$zip_file" -d "$target_dir"
        fi
    done

    # build
    docker compose up -d --build
}

# LINUX HOST CASE
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    echo "linux detected"
    # Check if jq is installed
    if ! command -v jq &> /dev/null; then
        echo "jq is not installed. Installing..."
        sudo apt update
        sudo apt install -y jq
    fi

    # BUILD
    buildApp

    # Check the exit code of the build command
    build_exit_code=$?
    if [ $build_exit_code -eq 0 ]; then
        # CONFIGURE VAULT
        docker build -t vault:custom vault/

        # OPEN RTP PORTS ON IPTABLES
        network_name="freepbx-docker_defaultnet"
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

# MACOS HOST CASE
elif [[ "$OSTYPE" == "darwin"* ]]; then
    echo "mac os detected"

# WINDOWS HOST CASE
elif [[ "$OSTYPE" == "cygwin" || "$OSTYPE" == "msys" || "$OSTYPE" == "MINGW" ]]; then
    # BUILD
    buildApp

    # Check the exit code of the build command
    build_exit_code=$?
    if [ $build_exit_code -eq 0 ]; then
        # CONFIGURE VAULT
        docker build -t vault:custom vault/

        # OPEN RTP PORTS ON WINDOWS FIREWALL
        echo "windows os detected"
        netsh advfirewall firewall add rule name="Allow UDP Port Range" dir=in action=allow protocol=UDP localport=16384-32767 remoteport=16384-32767
        netsh_exit_code=$?
        if [ $netsh_exit_code -eq 0 ]; then
            echo "RTP ports allowed on windows"
        fi
    else
        echo "Build failed with exit code: $build_exit_code. Exiting the script."
        exit 1
    fi
else
    # Unknown error
    echo "cannot detect os type"
fi