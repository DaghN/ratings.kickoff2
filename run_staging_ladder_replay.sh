#!/usr/bin/env bash
# DEPRECATED (Jun 2026) — May 2026 one-shot on frozen staging kooldb only.
# Forward work: ops simul on kooldb1 — docs/coordination/cutover-readiness.md
# Historical record: docs/archive/STAGING_REPLAY-2026-05.md
#
# One-shot staging ladder replay (staging kooldb only).
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
echo "Running staging replay only. Production needs a separately named, explicitly reviewed wrapper."
exec python3 -m scripts.ladder run --target staging
