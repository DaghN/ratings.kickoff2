# Agent handoff: Amiga tournament format system

**Use this prompt in a new chat** with a model strong in structural logic / schema design.  
**Primary reading:** [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) (full analysis from prior conversation).

---

## Copy-paste prompt (start here)

```
You are implementing the next generation of Amiga offline tournament structure in ko2amiga_db.

## Context

This repo maintains the Amiga 500 realm: ~27k historical games imported from Microsoft Access (koatd.mdb) into MySQL (ko2amiga_db), plus Elo replay and tournament standings UI. Legacy tournaments encode structure mostly via optional per-game `phase` string labels (~39% of games; ~61% NULL). There is no format schema, stage graph, or fixture model in Access.

A prior agent conversation (same repo, Jun 2026) produced a vision document and shipped one import fix (World Cup V KOA Cup merged into World Cup V via tournament_names.py). Read that analysis before coding:

  docs/amiga-tournament-format-vision.md

Also read and obey:

  docs/amiga-data-contract.md        — ground vs derived layers; table register policy
  docs/amiga-import-layer.md         — import transforms; never patch koatd as primary fix
  docs/amiga-schema-discovery.md     — Access inventory
  docs/amiga-realm-vision.md         — product context (tournament-first realm)
  docs/amiga-track-b-tournament-standings-agent-prompt.txt — Track B tiers, phase taxonomy, known gaps

Skim implementation reality:

  scripts/amiga/tournament_phases.py
  scripts/amiga/tournament_standings.py
  scripts/amiga/tournament_names.py
  scripts/amiga/import_access.py
  scripts/amiga/sql/001_core.sql
  site/public_html/includes/amiga_tournament_lib.php

If the prior chat is available in your environment, review it for the World Cup V / league-vs-cup / format-system threads. Do not re-litigate settled import fixes unless audit finds a bug.

## Your mission — two phases in one effort

### Phase A — Audit the vision document (required first)

Carefully verify docs/amiga-tournament-format-vision.md against the actual codebase and data. Be skeptical. Produce a short **Audit Report** section in your final response covering:

1. **Factual accuracy** — Are claims about koatd, phase NULL %, is_cup counts, World Cup KOA fix, standings scopes, parity exceptions still true? Run queries / read Access if koatd.mdb exists at data/amiga/source/koatd.mdb.
2. **Architectural gaps** — What did the vision doc miss? (e.g. PHP post-game ops, staging SQL multi-part export, FK ordering, append-only Elo constraints, match streaks policy.)
3. **Risks** — Migration pain, backwards compatibility, honour rules, bracket UI expectations.
4. **Corrections** — Amend the vision doc only where audit finds concrete errors or omissions worth documenting (minimal edits; do not rewrite the whole doc unless necessary).

Do not start large schema changes until Phase A is written down (even if brief).

### Phase B — Begin implementation (first high-quality slice)

Design and implement the **foundation** of a format/fixture system in ko2amiga_db — built for new tournaments from the ground up, with a clear bridge for legacy import. This is NOT a quick hack; optimise for:

- Clear ground vs derived separation (amiga-data-contract.md)
- Extensibility (new formats via templates, not regex sprawl)
- Legacy fallback (existing phase parser must keep working for imported games)
- Auditability (import manifest / migration notes)
- Minimal scope for slice 1 — but schema and module boundaries should reflect the long-term design

**Gravitating requirements (from product):**

- League play and cup play are **non-exclusive** (has_league / has_cup style flags, not a single enum)
- Eventually: registration, stages, fixtures, validated result entry for live tournaments
- Legacy events transform into or coexist with the new model (default: legacy_inferred template + phase fallback)
- Respect legacy = reproduce intended standings/brackets from game facts; Access Tables are reference only

**Suggested first slice (you may refine after audit):**

1. DDL: format templates table + tournaments columns (format_template_id, has_league, has_cup, keep is_cup as verbatim Access import unless you rename with migration note)
2. Python: tournament_format.py (or similar) — compute has_league/has_cup at import from Scores phase histogram; seed templates including legacy_inferred
3. Import integration + audit command (fail if tournament has games but neither flag)
4. Extend amiga-data-contract.md table register
5. Tests or verify script with real counts
6. Full import + replay + standings-parity smoke (no new FAILs)

**Out of scope for slice 1 unless trivial:**

- Public registration UI
- Full World Cup backfill of stages/fixtures
- Bracket advancement graph
- Website filter pill changes (explicitly deferred)

**Constraints:**

- Do not edit koatd.mdb
- Do not break append-only post-game ops contract without documenting
- Do not display match streaks on Amiga (data contract policy)
- Follow existing code style; minimal comments; no over-engineering beyond solid boundaries
- Do not commit unless user asks

## Deliverables

1. Audit report (markdown in reply)
2. Vision doc amendments if needed
3. Implemented slice 1 (schema + import + docs + verification)
4. Clear "next slice" notes for stages/fixtures/legacy backfill

## Verification commands (repo root)

python -m scripts.amiga import
python -m scripts.amiga replay
python -m scripts.amiga verify-chronology
python -m scripts.amiga verify-import-manifest
python -m scripts.amiga standings-parity --sweep --only-failures

Work in the real environment; run commands yourself.
```

---

## Notes for the human (Dagh)

- Point the new chat at this file and `amiga-tournament-format-vision.md`.
- If the model has access to **agent transcripts**, the Jun 2026 thread covers WC V KOA investigation, dual league/cup analysis, and this format-system report request.
- After slice 1 lands, staging will need `scripts/export_ko2amiga_db.ps1` re-run and WinSCP sync of new SQL parts.
- The new model should **audit before building** — that was intentional to catch blind spots in this analysis.
