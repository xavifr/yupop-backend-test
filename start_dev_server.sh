#!/bin/bash
dir=$(dirname $(realpath $0))

cd "$dir"

symfony server:start -d
#symfony run -d --watch=config,src,templates,vendor symfony console messenger:consume async -vv
docker-compose up -d
