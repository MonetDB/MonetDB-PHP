#!/usr/bin/env bash

PROJECT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
USER_ID=$(stat -c '%u' ${PROJECT_DIR}/src/Connection.php)

if ((USER_ID < 1000)); then
    1>&2 printf "\nThe project files are owned by a privileged user (USER_ID < 1000).\n"
    1>&2 printf "Please move them under a normal user.\n\n"
    exit 1
fi

docker run -d -h monetdb-php --restart unless-stopped \
    -p 9292:80 -v ${PROJECT_DIR}:/var/MonetDB-PHP \
    --name monetdb-php monetdb-php

docker exec --user root -it monetdb-php /bin/bash -c "usermod -u ${USER_ID} mdb"

docker exec --user mdb -it monetdb-php /bin/bash -c "cd /var/MonetDB-PHP && composer install"
