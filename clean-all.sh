# dangling images
read -p "Do you want to remove all dangling images? (yes/no): " answer
if [[ $answer == "yes" || $answer == "y" ]]; then
    docker image prune -f
else
    echo "Command execution canceled."
fi

# dangling containers
read -p "Do you want to remove all dangling containers? (yes/no): " answer1
if [[ $answer1 == "yes" || $answer1 == "y" ]]; then
    docker container prune -f
else
    echo "Command execution canceled."
fi

# web app removal
read -p "Do you want to remove web app container? (yes/no): " answer3
if [[ $answer3 == "yes" || $answer3 == "y" ]]; then
    docker container stop freepbx-docker-freepbx-1
    docker container rm freepbx-docker-freepbx-1
else
    echo "Command execution canceled."
fi

# db removal
read -p "Do you want to remove database container? (yes/no): " answer4
if [[ $answer4 == "yes" || $answer4 == "y" ]]; then
    docker container stop freepbx-docker-db-1
    docker container rm freepbx-docker-db-1
else
    echo "Command execution canceled."
fi

# volume removal
read -p "Do you want to remove app volume? (yes/no): " answer5
if [[ $answer5 == "yes" || $answer5 == "y" ]]; then
    docker volume rm freepbx-docker_mysql_data
else
    echo "Command execution canceled."
fi