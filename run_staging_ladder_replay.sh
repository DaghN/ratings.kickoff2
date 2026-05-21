#!/usr/bin/env bash
# One-shot staging ladder replay (kooldb). Run on the server from project root
# (the folder that contains public_html/, config/, and scripts/).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

if [[ ! -d scripts/ladder ]]; then
  echo "Missing scripts/ladder under $ROOT" >&2
  echo "Upload scripts/ladder/ to the server (sibling of public_html/), not inside public_html/." >&2
  exit 1
fi

python3 -m pip install -q -r scripts/ladder/requirements.txt
exec python3 -m scripts.ladder run
