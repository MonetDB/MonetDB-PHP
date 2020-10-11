#!/usr/bin/env bash

PROJECT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
cd ${PROJECT_DIR}

docker build --tag monetdb-php -f docker/Dockerfile ./
