#!/usr/bin/env bash

docker stop monetdb-php 2>/dev/null 2>&1
docker image rm monetdb-php --force
docker system prune --force
