#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="$(pwd)"

# Store credentials ONLY inside this project (not global).
# Delete this dir (or let this script wipe it) to reset the auth session.
SESSION_HOME="${PROJECT_DIR}/.gemini-session-home"

echo "────────────────────────────────────────────"
echo "Gemini CLI (project-local auth session)"
echo "Project: ${PROJECT_DIR}"
echo "Session: ${SESSION_HOME}"
echo "────────────────────────────────────────────"
echo

echo "Resetting any existing Gemini session in this project…"
# This is the key change: wipe cached credentials/settings every run.
rm -rf "${SESSION_HOME}"
mkdir -p "${SESSION_HOME}"

# Recreate expected dirs (helpful across Linux/macOS setups)
mkdir -p "${SESSION_HOME}/.gemini" \
         "${SESSION_HOME}/.config" \
         "${SESSION_HOME}/.cache" \
         "${SESSION_HOME}/Library/Application Support"

echo
echo "Starting Gemini…"
echo "When prompted, choose: Login with Google"
echo "This script forces a URL/code flow (copy/paste) instead of auto-opening a browser."
echo

# NO_BROWSER=true makes Gemini print the auth URL so you can paste it manually.
# Use isolated HOME so it won't reuse your global cached credentials.
export HOME="${SESSION_HOME}"
export XDG_CONFIG_HOME="${SESSION_HOME}/.config"
export XDG_CACHE_HOME="${SESSION_HOME}/.cache"
export NO_BROWSER=true

# IMPORTANT: exec replaces the shell process with gemini
exec gemini
