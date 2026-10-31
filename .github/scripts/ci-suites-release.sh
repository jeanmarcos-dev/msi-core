#!/bin/sh
set -eu

repository=${1:?usage: ci-suites-release.sh <repository>}

git -C "$repository" describe --tags --abbrev=0 --match '[0-9]*.[0-9]*.[0-9]*'
