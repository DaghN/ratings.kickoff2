"""verify-tournament-formats on ko2amiga_work (fork entry; logic from tournament_format)."""

from __future__ import annotations

import sys

from scripts.amiga.modern.work_db import connect_work
from scripts.amiga.tournament_format import (
    audit_tournament_format_flags,
    format_bucket_counts,
    list_seeded_templates,
    seed_format_templates,
    verify_template_registry,
)


def main() -> int:
    conn = connect_work()
    try:
        with conn.cursor() as cur:
            cur.execute("SET time_zone = '+00:00'")
        seed_format_templates(conn)
        conn.commit()
        registry_errors = verify_template_registry(conn)
        templates = list_seeded_templates(conn)
        failures = audit_tournament_format_flags(conn)
        buckets = format_bucket_counts(conn)
    finally:
        conn.close()

    planned = [t for t in templates if str(t.get("status")) == "planned"]
    implemented = [t for t in templates if str(t.get("status")) != "planned"]
    print(
        f"Format templates: {len(templates)} total "
        f"({len(implemented)} implemented, {len(planned)} planned)"
    )
    if planned:
        print("  planned: " + ", ".join(str(t["slug"]) for t in planned))
    print("Tournament format buckets: " + ", ".join(f"{k}={v}" for k, v in buckets.items()))

    errors: list[str] = list(registry_errors)
    if failures:
        for row in failures:
            errors.append(
                f"tournament id={row['id']} name={row['name']!r} "
                f"has {row['game_count']} games but neither flag"
            )

    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        return 1
    print("OK: template registry + every tournament with games has has_league or has_cup")
    return 0