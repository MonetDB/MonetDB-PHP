#!/usr/bin/env bash

PROJECT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
USER_ID=$(stat -c '%u' ${PROJECT_DIR}/src/Connection.php)
USER_NAME=$(stat -c '%U' ${PROJECT_DIR}/src/Connection.php)

if ((USER_ID < 1000)); then
    1>&2 printf "\nThe project files are owned by a privileged user (USER_ID < 1000).\n"
    1>&2 printf "Please move them under a normal user.\n\n"
    exit 1
fi

cd ${PROJECT_DIR}

docker build --tag monetdb-php --build-arg ENV_USER_ID=${USER_ID} \
    --build-arg ENV_USER_NAME=${USER_NAME} -f docker/Dockerfile ./
