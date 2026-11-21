#!/usr/bin/env bash
# Fails when a secret could leak into git (task 1287). Runs in CI and locally: bash docker/check-secrets.sh
# Never prints a secret value, only file names and keys.
set -u
cd "$(dirname "$0")/.."
failed=0
fail() { echo "::error::$1"; failed=1; }

# 1. Nothing under secrets/ is tracked (it is git-ignored, but a forced add would still commit it).
for file in $(git ls-files secrets); do
  fail "secrets/$(basename "$file") is committed: remove it with git rm --cached and rotate that secret."
done

# 2. secrets.example only lists names: every file stays empty.
for file in secrets.example/*; do
  [ -s "$file" ] && fail "$file is not empty: it must only list the name, the value goes in secrets/."
done

# 3. Every secret('name') the code reads is listed in secrets.example/, so nobody misses one.
for name in $(grep -rhoE "secret\('[a-z0-9_]+'" --include=*.php gateway/config gateway/app services/*/config services/*/app packages/common/src packages/common/config | sed -E "s/secret\('([a-z0-9_]+)'/\1/" | sort -u); do
  [ -e "secrets.example/$name" ] || fail "secret('$name') is read by the code but secrets.example/$name is missing."
done

# 4. No password, token or private key value in a committed .env.example (empty or null only).
for file in $(git ls-files '*.env.example' '.env.example'); do
  # Keys only (the value is cut off), for lines whose value is not empty or null. tr: Windows line endings.
  for found in $(tr -d '\r' < "$file" | grep -nE '^(APP_KEY|[A-Z_]*(PASSWORD|SECRET|TOKEN|API_KEY|PRIVATE_KEY))=' \
      | grep -vE '=(null)?$' | cut -d= -f1); do
    fail "$file line ${found%%:*}: ${found#*:} must be empty, the value goes in secrets/."
  done
done

# 5. No private key outside the test fixtures (those are throwaway keys made for the tests).
for file in $(git grep -lE "BEGIN ([A-Z]+ )?PRIVATE KEY" -- ':!*/tests/Fixtures/*'); do
  fail "$file contains a private key."
done

[ "$failed" -eq 0 ] && echo "Secrets check passed."
exit "$failed"
