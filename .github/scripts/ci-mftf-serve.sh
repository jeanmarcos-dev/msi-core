#!/bin/sh
set -eu

usage='usage: ci-mftf-serve.sh <magento-dir> <port> <log-file>'
magento=${1:?$usage}
port=${2:?$usage}
log=${3:?$usage}
router=$(cd "$(dirname "$0")" && pwd)/ci-mftf-router.php

cd "$magento/pub"
MAGENTO_ROOT=$magento PHP_CLI_SERVER_WORKERS=${PHP_CLI_SERVER_WORKERS:-4} nohup php -d memory_limit=-1 -d opcache.enable_cli=1 -d opcache.revalidate_freq=0 -S "127.0.0.1:$port" -t "$magento/pub" "$router" > "$log" 2>&1 &

attempts=0
until curl --silent --output /dev/null "http://127.0.0.1:$port/"; do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge "${MFTF_SERVE_ATTEMPTS:-60}" ]; then
        echo "PHP server did not answer on port $port" >&2
        cat "$log" >&2
        exit 1
    fi
    sleep 1
done
