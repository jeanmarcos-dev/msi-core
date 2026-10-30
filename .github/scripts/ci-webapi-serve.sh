#!/bin/sh
set -eu

magento=${1:?usage: ci-webapi-serve.sh <magento-dir> <port> <log-file>}
port=${2:?usage: ci-webapi-serve.sh <magento-dir> <port> <log-file>}
log=${3:?usage: ci-webapi-serve.sh <magento-dir> <port> <log-file>}

# shellcheck disable=SC3045
ulimit -c unlimited 2>/dev/null || true

cd "$magento/pub"
PHP_CLI_SERVER_WORKERS=${PHP_CLI_SERVER_WORKERS:-4} nohup php -d "memory_limit=${WEBAPI_SERVE_MEMORY_LIMIT:-2G}" -S "127.0.0.1:$port" -t "$magento/pub" "$magento/phpserver/router.php" > "$log" 2>&1 &

attempts=0
until curl --silent --output /dev/null "http://127.0.0.1:$port/rest/V1/store/storeViews"; do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge "${WEBAPI_SERVE_ATTEMPTS:-30}" ]; then
        echo "PHP server did not answer on port $port" >&2
        cat "$log" >&2
        exit 1
    fi
    sleep 1
done
