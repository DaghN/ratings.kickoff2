#!/usr/bin/env bash
# One-shot staging ladder replay (kooldb).
# Run on the server from either:
#   - project root (scripts/ beside public_html/), or
#   - public_html/ if SFTP only allows uploading under the web root.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -d "$HERE/scripts/ladder" ]]; then
  cd "$HERE"
elif [[ -d "$HERE/public_html/scripts/ladder" ]]; then
  cd "$HERE/public_html"
else
  echo "Missing scripts/ladder under $HERE or $HERE/public_html" >&2
  exit 1
fi

python3 -m pip install -q -r scripts/ladder/requirements.txt
exec python3 -m scripts.ladder run
