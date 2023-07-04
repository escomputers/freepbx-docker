freepbxip="172.18.0.20"
network_interface="freepbxint"

# LINUX HOST CASE
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    echo "linux detected"
    # Check if jq is installed
    if ! command -v jq &> /dev/null; then
        echo "jq is not installed. Installing..."
        sudo apt update
        sudo apt install -y jq
    fi

    # Check if Docker is installed
    if ! command -v docker &> /dev/null; then
        echo "Docker is not installed. Installing..."
        sudo apt-get update
        sudo apt-get install \
            ca-certificates \
            curl \
            gnupg
        sudo mkdir -m 0755 -p /etc/apt/keyrings
        curl -fsSL https://download.docker.com/linux/debian/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
        echo \
        "deb [arch="$(dpkg --print-architecture)" signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian \
        "$(. /etc/os-release && echo "$VERSION_CODENAME")" stable" | \
        sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
        sudo apt-get update
        sudo apt-get install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin -y
    fi

    # BUILD
    cd vault-transit && docker build -t vault-transit:custom .
    cd .. && docker compose up -d --build

    # Check the exit code of the build command
    build_exit_code=$?
    if [ $build_exit_code -eq 0 ]; then
        # CONFIGURE VAULT
        docker build -t vault:custom vault/

        # OPEN RTP PORTS ON IPTABLES

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
    else
        echo "Build failed with exit code: $build_exit_code. Exiting the script."
        exit 1
    fi

# MACOS HOST CASE
elif [[ "$OSTYPE" == "darwin"* ]]; then
    echo "mac os detected, currently not supported"

# WINDOWS HOST CASE
elif [[ "$OSTYPE" == "cygwin" || "$OSTYPE" == "msys" || "$OSTYPE" == "MINGW" ]]; then
    # BUILD
    docker compose up -d --build

    # Check the exit code of the build command
    build_exit_code=$?
    if [ $build_exit_code -eq 0 ]; then
        # CONFIGURE VAULT
        docker build -t vault:custom vault/

        # OPEN RTP PORTS ON WINDOWS FIREWALL
        echo "windows os detected"
        netsh advfirewall firewall add rule name="Allow UDP Port Range" dir=in action=allow protocol=UDP localport=16384-32767 remoteport=16384-32767 localip=127.0.0.1 remoteip="$freepbxip"
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