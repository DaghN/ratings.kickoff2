"""Audit pending_review tournaments for import merge / split candidates."""
from __future__ import annotations

import json
import re
from collections import defaultdict
from pathlib import Path

import pyodbc

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.import_access import connect_mysql
from scripts.amiga.import_corrections import IMPORT_CATALOG_SPLITS, world_cup_catalog_name
from scripts.amiga.tournament_names import (
    MILAN_X_FRAGMENTS,
    TOURNAMENT_ALIASES,
    resolve_phase,
    resolve_tournament_name,
)

REPO = Path(__file__).resolve().parents[2]
REGISTER = REPO / "scripts/amiga/tournament_structure/disposition_register.json"
MDB = REPO / "data/amiga/source/koatd.mdb"

# Alias categories for triage
MILAN_FRAGMENT_LABELS = frozenset(MILAN_X_FRAGMENTS)
WC_RENAME_BASES = frozenset()  # filled from overrides
SPLIT_CHILD_NAMES = frozenset(s.name for s in IMPORT_CATALOG_SPLITS)


def load_pending_review() -> dict[int, str]:
    reg = json.loads(REGISTER.read_text(encoding="utf-8"))
    pending: dict[int, str] = {}
    cfg = load_amiga_db_config()
    conn = connect_mysql(cfg)
    with conn.cursor() as cur:
        for tid_str, row in reg["tournaments"].items():
            if row.get("handler") != "pending_review":
                continue
            tid = int(tid_str)
            cur.execute("SELECT name FROM tournaments WHERE id = %s", (tid,))
            r = cur.fetchone()
            if r:
                pending[tid] = str(r["name"])
    conn.close()
    return pending


def access_catalog_and_scores() -> tuple[set[str], dict[str, int], dict[str, list[tuple[str | None, int]]]]:
    conn = pyodbc.connect(f"DRIVER={{Microsoft Access Driver (*.mdb, *.accdb)}};DBQ={MDB.resolve()};")
    cur = conn.cursor()
    cur.execute("SELECT Tournament FROM [Tournament players]")
    catalog = {str(r[0]).strip() for r in cur.fetchall() if r[0]}

    cur.execute("SELECT Tournament, Phase, COUNT(*) FROM Scores GROUP BY Tournament, Phase")
    scores_by_label: dict[str, int] = defaultdict(int)
    phases_by_label: dict[str, list[tuple[str | None, int]]] = defaultdict(list)
    for tourn, phase, n in cur.fetchall():
        label = str(tourn).strip() if tourn else ""
        if not label:
            continue
        scores_by_label[label] += int(n)
        phases_by_label[label].append((str(phase).strip() if phase else None, int(n)))
    conn.close()
    return catalog, dict(scores_by_label), dict(phases_by_label)


def classify_alias(scores_label: str, parent: str) -> str:
    if scores_label in MILAN_FRAGMENT_LABELS:
        return "milan_ko_fragment"
    if scores_label == "World Cup V KOA Cup":
        return "wc_v_koa_cup_merge"
    if scores_label in ("World Cup 2015",) or parent.startswith("World Cup "):
        if scores_label != parent and "World Cup" in scores_label:
            return "world_cup_rename"
    if scores_label in SPLIT_CHILD_NAMES:
        return "already_split"
    if re.search(r"\b(Cup|Team)\b", scores_label, re.I) and scores_label != parent:
        return "split_candidate_label"
    if scores_label.startswith(parent + " "):
        return "prefixed_scores_label"
    if scores_label != parent:
        return "other_alias"
    return "identity"


def main() -> None:
    pending = load_pending_review()
    catalog, scores_by_label, phases_by_label = access_catalog_and_scores()

    # Parents receiving aliased Scores (active merges)
    aliases_by_parent: dict[str, list[str]] = defaultdict(list)
    for label, parent in TOURNAMENT_ALIASES.items():
        if label != parent:
            aliases_by_parent[parent].append(label)

    # Scores-only labels (no Access catalog row) that resolve to a catalog parent
    scores_only_merges: list[dict] = []
    for label, count in sorted(scores_by_label.items()):
        if label in catalog:
            continue
        parent = resolve_tournament_name(label)
        if parent not in catalog and parent not in {resolve_tournament_name(c) for c in catalog}:
            # parent might be canonical WC name
            pass
        # Check if canonical parent exists in catalog (after WC suffix etc.)
        parent_in_catalog = parent in catalog
        if not parent_in_catalog:
            # try matching any catalog row that resolves same
            for c in catalog:
                if resolve_tournament_name(c) == parent or c == parent:
                    parent_in_catalog = True
                    parent = c
                    break
        if not parent_in_catalog:
            continue
        scores_only_merges.append(
            {
                "scores_label": label,
                "parent": parent,
                "games": count,
                "kind": classify_alias(label, parent),
                "phases": phases_by_label.get(label, []),
            }
        )

    # Map pending_review names to ids (multiple ids unlikely)
    pending_by_name = {name: tid for tid, name in pending.items()}

    print(f"pending_review count: {len(pending)}")
    print(f"active alias parents: {len(aliases_by_parent)}")
    print(f"scores-only labels merging into catalog: {len(scores_only_merges)}")
    print()

    # --- Section A: pending_review that ARE parents of active merges ---
    print("=" * 72)
    print("A. pending_review parents with ACTIVE import merges (aliases still wired)")
    print("=" * 72)
    found_a = []
    for parent, labels in sorted(aliases_by_parent.items()):
        tid = pending_by_name.get(parent)
        if tid is None:
            continue
        kinds = {classify_alias(l, parent) for l in labels}
        found_a.append((tid, parent, labels, kinds))
        print(f"  id={tid} {parent!r}")
        for l in sorted(labels):
            g = scores_by_label.get(l, 0)
            print(f"    <- {l!r} ({g}g) [{classify_alias(l, parent)}]")
        print(f"    kinds: {sorted(kinds)}")
        print()

    if not found_a:
        print("  (none — no pending_review tournament is parent of a remaining alias)\n")

    # --- Section B: pending_review inflated by scores-only labels (koatd scan) ---
    print("=" * 72)
    print("B. pending_review catalog rows with extra Scores-only labels (koatd)")
    print("=" * 72)
    found_b = []
    for tid, name in sorted(pending.items()):
        extras = []
        for row in scores_only_merges:
            if row["parent"] == name or resolve_tournament_name(name) == row["parent"]:
                extras.append(row)
        # also direct label match on name
        for row in scores_only_merges:
            if resolve_tournament_name(row["scores_label"]) == name and row not in extras:
                extras.append(row)
        if extras:
            found_b.append((tid, name, extras))
            print(f"  id={tid} {name!r}")
            for row in extras:
                print(
                    f"    + {row['scores_label']!r} {row['games']}g "
                    f"[{row['kind']}] phases={row['phases'][:4]}{'…' if len(row['phases'])>4 else ''}"
                )
            print()

    if not found_b:
        print("  (none)\n")

    # --- Section C: all scores-only merges NOT yet split, by risk ---
    print("=" * 72)
    print("C. All Scores-only labels still merged at import (full koatd)")
    print("=" * 72)
    by_kind: dict[str, list] = defaultdict(list)
    for row in scores_only_merges:
        by_kind[row["kind"]].append(row)

    for kind in sorted(by_kind.keys()):
        rows = by_kind[kind]
        print(f"\n  [{kind}] ({len(rows)} labels)")
        for row in rows:
            parent = row["parent"]
            tid = pending_by_name.get(parent, "—")
            flag = " ** PENDING_REVIEW **" if parent in pending_by_name else ""
            print(
                f"    {row['scores_label']!r} -> {parent!r} ({row['games']}g) "
                f"id={tid}{flag}"
            )

    # --- Section D: pending_review with NO merge — why tier C? ---
    print()
    print("=" * 72)
    print("D. pending_review with NO scores-only merge detected")
    print(f"   ({len(pending) - len(found_b)} tournaments)")
    print("=" * 72)
    merged_parents = {name for _, name, _ in found_b}
    merged_parents |= {p for _, p, _, _ in found_a}
    for tid, name in sorted(pending.items()):
        if name in merged_parents:
            continue
        reg = json.loads(REGISTER.read_text(encoding="utf-8"))
        notes = reg["tournaments"][str(tid)].get("notes", "")
        g = scores_by_label.get(name, 0)
        print(f"  id={tid:3d} {name[:50]:50s} access_scores={g:3d}g  notes={notes[:60]}")

    # Summary
    print()
    print("=" * 72)
    print("SUMMARY")
    print("=" * 72)
    split_like = [
        r for r in scores_only_merges
        if r["kind"] in ("split_candidate_label", "prefixed_scores_label", "other_alias")
        and r["kind"] != "milan_ko_fragment"
        and r["kind"] != "wc_v_koa_cup_merge"
        and r["kind"] != "already_split"
    ]
    print(f"Split-track style scores-only labels still merged: {len(split_like)}")
    for r in split_like:
        print(f"  {r['scores_label']!r} -> {r['parent']!r} ({r['games']}g)")


def extended_scan() -> None:
    """Fragment prefixes, Cup/Team labels, pending Cup catalog siblings."""
    import re

    reg = json.loads(REGISTER.read_text(encoding="utf-8"))
    pending_ids = {
        int(k)
        for k, v in reg["tournaments"].items()
        if v.get("handler") == "pending_review"
    }
    pending = load_pending_review()

    catalog, scores_by_label, _ = access_catalog_and_scores()

    conn = pyodbc.connect(f"DRIVER={{Microsoft Access Driver (*.mdb, *.accdb)}};DBQ={MDB.resolve()};")
    cur = conn.cursor()
    cur.execute("SELECT DISTINCT Tournament FROM Scores")
    all_labels = [str(r[0]).strip() for r in cur.fetchall() if r[0]]
    conn.close()

    by_parent: dict[str, list[str]] = defaultdict(list)
    for label in all_labels:
        parent = resolve_tournament_name(label)
        if label != parent:
            by_parent[parent].append(label)

    pending_by_name = {name: tid for tid, name in pending.items()}

    print("=" * 72)
    print("E. Extended: pending_review receiving ANY aliased Scores labels")
    print("=" * 72)
    any_found = False
    for tid, name in sorted(pending.items()):
        labels = sorted(by_parent.get(name, []))
        if not labels:
            continue
        any_found = True
        print(f"  id={tid} {name!r}: {labels}")
    if not any_found:
        print("  (none beyond Milan X)")

    print()
    print("=" * 72)
    print("F. Scores-only prefix fragments (label starts with catalog name + space)")
    print("=" * 72)
    for label in sorted(all_labels):
        if label in catalog:
            continue
        cat_parent = None
        for c in sorted(catalog, key=len, reverse=True):
            if label.startswith(c + " "):
                cat_parent = c
                break
        if not cat_parent:
            continue
        resolved = resolve_tournament_name(label)
        tid = pending_by_name.get(resolved) or pending_by_name.get(cat_parent)
        flag = " ** pending_review **" if tid else ""
        g = scores_by_label.get(label, 0)
        print(f"  {label!r} ({g}g) -> {resolved!r}  id={tid or '—'}{flag}")

    print()
    print("=" * 72)
    print("G. Cup/Team Scores labels with NO Access catalog row")
    print("=" * 72)
    cup_team = []
    for label in sorted(all_labels):
        if label in catalog:
            continue
        if not re.search(r"(Cup|Team)\b", label):
            continue
        parent = resolve_tournament_name(label)
        tid = pending_by_name.get(parent)
        cup_team.append((label, parent, scores_by_label.get(label, 0), tid))
    if not cup_team:
        print("  (none — Groningen/Gloucester already split to synthetic catalog)")
    for label, parent, g, tid in cup_team:
        print(f"  {label!r} ({g}g) -> {parent!r}  parent_pending_id={tid or '—'}")

    print()
    print("=" * 72)
    print("H. pending_review *Cup* catalog rows — main event also in Access?")
    print("=" * 72)
    for tid, name in sorted(pending.items()):
        if not re.search(r"\bCup\b", name, re.I):
            continue
        base = re.sub(r"\s+(Gold|Silver)?\s*Cup\s*$", "", name, flags=re.I).strip()
        mains = sorted(c for c in catalog if c == base)
        print(f"  id={tid:3d} {name!r}  Access main={mains or '—'}")


if __name__ == "__main__":
    main()
    print()
    extended_scan()
