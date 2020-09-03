#!/usr/bin/env bash

PROJECT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"

docker run -d -h monetdb-php --restart unless-stopped \
    -p 9292:80 -v ${PROJECT_DIR}:/var/MonetDB-PHP \
    --name monetdb-php monetdb-php

# TODO: create database
