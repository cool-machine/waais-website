#!/usr/bin/env bash
# Tiny wrapper: run a script in the background, write log and pid files.
# Args: <script-path> <log-path> <pid-path>
set -euo pipefail
SCRIPT="$1"
LOG="$2"
PIDFILE="$3"
nohup /bin/bash "$SCRIPT" > "$LOG" 2>&1 < /dev/null &
echo $! > "$PIDFILE"
echo "started pid=$(cat "$PIDFILE") log=$LOG"
