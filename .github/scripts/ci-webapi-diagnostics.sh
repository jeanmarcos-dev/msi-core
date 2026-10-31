#!/bin/sh
set -u

out=${1:?usage: ci-webapi-diagnostics.sh <output-dir> [cores-dir]}
cores=${2:-}

mkdir -p "$out"

{ dmesg || sudo -n dmesg; } 2>&1 | tail -n 300 > "$out/dmesg.txt"
{ journalctl -k --no-pager -n 500 || sudo -n journalctl -k --no-pager -n 500; } > "$out/journal-kernel.txt" 2>&1
ps -eo pid,ppid,stat,rss,etime,args > "$out/ps.txt" 2>&1
free -m > "$out/free.txt" 2>&1
cat /proc/meminfo > "$out/meminfo.txt" 2>&1

if [ -n "$cores" ] && [ -d "$cores" ]; then
    mkdir -p "$out/cores"
    cp -a "$cores"/. "$out/cores/" 2>/dev/null
fi

exit 0
