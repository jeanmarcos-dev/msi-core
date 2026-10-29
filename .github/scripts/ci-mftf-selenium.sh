#!/bin/sh
set -eu

image=${1:?usage: ci-mftf-selenium.sh <selenium-image>}

docker run --detach --name selenium --network host --shm-size=2g \
    --env SE_NODE_MAX_SESSIONS=4 \
    --env SE_NODE_OVERRIDE_MAX_SESSIONS=true \
    "$image"

attempts=0
until curl --silent http://127.0.0.1:4444/status | grep -q '"ready": *true'; do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge "${MFTF_SELENIUM_ATTEMPTS:-60}" ]; then
        echo "Selenium did not become ready" >&2
        docker logs selenium >&2
        exit 1
    fi
    sleep 1
done
