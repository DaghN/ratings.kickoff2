# Amiga stored id/date semantics — decision & plan

> **Product policy (Jul 2026):** Rules below remain authoritative for product behaviour. **Writer/sign-off at ship** = oracle **`prove`** on frozen **`ko2amiga_db`**; **forward** = **`simul`** on **`ko2amiga_work`**. [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §0.

**Status:** Phases A–D complete (Jun 2026) — manifest [`amiga-stored-field-semantics.md`](amiga-stored-field-semantics.md)  
**Trigger:** SCH-029 showed `prove` green while HoF `*Date` was wrong (projection + verify gap, not missing columns).  
**Related:** [`amiga-hof-record-date-policy.md`](amiga-hof-record-date-policy.md) (rise dates, done) · [`amiga-data-contract.md`](amiga-data-contract.md) · holy loop `python -m scripts.amiga prove`

---

## Decision

Combine two activities — do **not** choose one or the other:

| Layer | Role | Artifact |
|-------|------|----------|
| **Manifest** | What each id/date *means*; who writes it; what verifies it | Living doc (Phase A) |
| **Verify modules** | Replay oracle checks in `prove` | `scripts/amiga/verify_*.py` (+ optional PHP smoke) |

**Principle:** New stored id/date fields ship with a manifest row **and** a test or verify oracle. No “document only” fields on hot paths.

**Non-goals:** Grep-every-`INSERT` static audit; oracles for unsettled product rules; blocking full `prove` on slow PHP smoke until path is stable.

---

## Semantic classes (manifest vocabulary)

Use these labels in the manifest — same idea as SCH-029 D1–D10:

| Class | Example | HoF / realm note |
|-------|---------|------------------|
| **participation** | `honours_last_event_date`, `last_event_date` on current | Last event played — not record “when metric rose” |
| **rise** | `event_gold_last_rise_*` | Last strict increase of a cumulative scalar |
| **peak-year** | `peak_year_games_year` | Calendar year; HoF date = year end |
| **game-anchor** | `LastGameGameID`, game-sourced HoF rows | Tied to a specific `amiga_games` row |
| **holder-projection** | `MostTournamentWinsDate` on `generalstats` | Copied from holder player row at finalize — not computed in HoF PHP |

---

## Phases

| Phase | Goal | Deliverables | Proof / STOP |
|-------|------|--------------|--------------|
| **A** | Manifest + backlog | `docs/amiga-stored-field-semantics.md` — table of id/date fields on snapshots, current, realm snapshots, `generalstats`; column: meaning, Python writer, PHP writer, verify coverage (yes/no/planned); ranked **unverified backlog** | **Done** — Jun 2026 |
| **B** | Holder projection verify | Extend or add `verify_*` — every career HoF `*ID` / `*Date` on `generalstats` + latest realm vs holder row canonical source (generalize SCH-029 eight rows + older `server_records` holders) | **Done** — Jun 2026 (`verify_hof_holder_projection.py` in `prove`) |
| **C** | Id/date pairing + manifest gaps | Invariant: non-null `*_tournament_id` ↔ consistent `*_event_date` (and tournament exists); close top manifest **no** rows with unit tests or verify slices | **Done** — Jun 2026 (`verify_stored_id_date_pairs.py` in `prove`) |
| **D** | PHP parity smoke (optional) | ~~`verify-php-finalize-parity`~~ — **retired** with refinalize (Jun 2026) | **Retired** — use `prove` |

**Suggested v1 sign-off:** Phases **A + B + C** in `prove`.

---

## Ritual (ongoing)

When adding or changing stored ladder/amiga truth:

1. Add/update one row in **stored-field semantics** manifest.
2. If meaning is new, one sentence in policy or data-contract cross-link.
3. Ship **either** `test_*` (pure increment logic) **or** `verify_*` (replay oracle) before calling the slice done.
4. Holder-facing `*Date` on `generalstats` / realm → must trace to a manifest **source field** on the holder row, not HoF read-time SQL.

---

## Code touchpoints (holy ops)

| Area | Python | PHP |
|------|--------|-----|
| Snapshots / current | `snapshot_persist.py`, `honours_totals.py`, `player_geo_year.py` | `amiga_event_snapshot_persist.php`, honours/geo libs |
| Realm / HoF projection | `realm_incremental.py`, `server_records.py` | `amiga_realm_incremental_lib.php`, `amiga_realm_snapshot_lib.php` |
| Verify | `verify_hof_geo_year.py`, `verify_realm_snapshots.py`, `verify_event_snapshots.py` | — (PHP smoke in D only) |
| Orchestration | `prove.py`, `finalize_tournament.py`, `replay.py` | `finalize_tournament.php`, `run_process_game.php` |

---

## Starter prompt (new chat)

```text
Today: Amiga stored id/date semantics — Phase N per docs/amiga-stored-field-semantics-plan.md.
Read manifest (Phase A doc) before coding verify slices.
Reuse prove verify-* pattern; no grep-only audit.
```
