# Amiga tournament structure — manual materialize runbook

**Status:** Forward path for the **long tail** (Jul 2026). Bulk `apply-structure --from-disposition` closed most obvious catalog events (~515/605 with fixture linkage on work). **Remaining ids = one tournament at a time.**

**Authority:** [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) T3, T8–T11 · **handlers reference:** [`amiga-tournament-structure-handlers.md`](amiga-tournament-structure-handlers.md) · **decision log:** [`amiga-tournament-structure-review-queue.md`](amiga-tournament-structure-review-queue.md)

**DB:** **`ko2amiga_work`** (living repair shop). `materialize_legacy` accepts `ko2amiga_work` and `ko2amiga_db` ([`scripts/amiga/config.py`](../scripts/amiga/config.py) `AMIGA_GROUND_DATABASES`).

---

## When to use this

| Use manual materialize | Do **not** use bulk apply |
|------------------------|---------------------------|
| Mixed league + cup (Frankfurt-style) | Expecting `structure_spec` in disposition to auto-run (no active registry spec → bulk **skips**) |
| Cup audit / slice-6 review ids | `materialize-tier-a` / `materialize-tier-b-non-wc` as default ritual |
| `pending_review` or exotic placement | WC track (`wc_deferred`) — separate WC structure slice |
| After human sign-off on games + phases | Blind `generate-disposition-register` (overwrites hand `notes`) |

**Ground truth after success:** MySQL — `tournament_stages`, `tournament_fixtures`, `amiga_games.fixture_id`. Git registers are **triage memory**, not runtime.

---

## Checklist (one tournament)

### 1. Triage (human)

- Open **Games** + **Standings** locally: `http://ratingskickoff.test/amiga/tournament/games.php?id={id}` · `…/standings.php?id={id}`
- Confirm phase histogram and player count match your mental model.
- Write **one line** of format truth (for register `notes` and review log). Example (id **173** Frankfurt): *4p double RR (12g) + 2-leg semis / 3rd / final*.

Disposition `handler` is a **hint** (`pure_rr`, `structure_spec`, …). It does **not** alone mean “may materialize.”

### 2. Unblock materialize (code, if needed)

`materialize_legacy` refuses ids in review frozensets — check **`scripts/amiga/tournament_structure/tier_b_non_wc_register.py`**:

- `NON_WC_SLICE6_CUP_REVIEW_IDS` — cup-audit false positives (Frankfurt was here)
- `NON_WC_ORIGINAL_STRUCTURE_REVIEW_IDS` — older manual review set
- `NON_WC_PARSER_FIX_FIRST_IDS` — fix `tournament_phases.py` **first** (slice 6a), not this runbook
- `STRUCTURE_REVIEW_TOURNAMENT_IDS` in `materialize_legacy.py` — tier-C NULL-phase quirks

**Action:** remove `{id}` from the blocking set **only after** triage. Commit with a review-queue log line.

Optional: update `disposition_register.json` `notes` (do **not** require handler change for legacy materialize).

### 3. Preflight SQL (work DB)

```sql
SELECT id, name,
  (SELECT COUNT(*) FROM tournament_stages s WHERE s.tournament_id = t.id) AS stages,
  (SELECT COUNT(*) FROM tournament_fixtures f JOIN tournament_stages s ON s.id = f.stage_id WHERE s.tournament_id = t.id) AS fixtures,
  (SELECT COUNT(*) FROM amiga_games g WHERE g.tournament_id = t.id) AS games,
  (SELECT COUNT(*) FROM amiga_games g WHERE g.tournament_id = t.id AND g.fixture_id IS NOT NULL) AS linked
FROM tournaments t WHERE id = {id};
```

Expect **stages/fixtures = 0** for first materialize (or use `--replace` to rebuild).

### 4. Materialize

```powershell
cd <repo>
python -m scripts.amiga tournament-structure materialize --tournament-id {id} --dry-run
python -m scripts.amiga tournament-structure materialize --tournament-id {id} --replace
```

| Handler in disposition | Typical CLI |
|------------------------|-------------|
| `pure_rr` / mixed labeled phases | `materialize` (legacy) — **most manual tail** |
| `pure_knockout` | `materialize-pure-knockout --tournament-id {id} [--replace]` after preview |
| `structure_spec` + **active** registry spec (rare) | `apply-structure` / spec path — not this runbook |
| `wc_deferred` | WC track — defer |

**Labeled phases** (Frankfurt): materialize bootstraps stage `name` from game `phase` text; KO ties → one stage per pair (policy T3). **Then triage display names manually** — `tournament_stages.name` is UI authority; leave `g.phase` as witness.

```sql
-- Example (Frankfurt 173): disambiguate RR "Round 1" from KO Round 1 elsewhere
UPDATE tournament_stages SET name = 'Round 1 - League'
WHERE tournament_id = 173 AND stage_key = 'round-1';
```

**Bulk null-phase marathons (Jul 2026):** 503 single-stage tier-A events had materialize default `Overall` — renamed to **`League`** on work (`stage_key` stays `overall`; only when `stage_count = 1` and `stage_type = round_robin`).

### 5. Standings + structure verify

```powershell
python -m scripts.amiga backfill-standings-stage-id --tournament-id {id}
python -m scripts.amiga verify-standings-stage-id --tournament-id {id}
python -m scripts.amiga standings-parity --tournament-id {id} --sweep --only-failures
```

`standings-parity` may **SKIP** events Access does not mirror (mixed league+cup) — not always a failure.

Browser spot-check: standings scopes + games tab.

### 6. Record (same session)

| What | Where |
|------|--------|
| One-line decision + date | [`amiga-tournament-structure-review-queue.md`](amiga-tournament-structure-review-queue.md) § Review log |
| Session handoff | `PROJECT_MEMORY.md` Recent log |
| Optional `notes` tweak | `disposition_register.json` |

**Do not** mark “materialized” only in JSON — confirm DB counts.

### 7. Staging (when ready)

`powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_work.ps1` → WinSCP → import preview/apply. See [`amiga-staging-handoff.md`](amiga-staging-handoff.md).

---

## Gates cheat sheet

| Question | Answer |
|----------|--------|
| May I run `materialize`? | Id **not** in tier-B / structure review frozensets (§2) |
| What does disposition `handler` mean? | Bulk **routing hint** — not materialize permission |
| `structure_spec` without registry spec? | Use **legacy `materialize`** after triage, not bulk apply |
| Promotion graph (D18)? | **Not** required for catalog materialize |
| Display module names on games tab? | **`tournament_stages.name` first** via `amiga_rated_games_from_sql()` (`COALESCE(s.name, g.phase)`); standings links use `stage_id` when set |

---

## Reference: Frankfurt (173), Jul 2026

- Cleared from `NON_WC_SLICE6_CUP_REVIEW_IDS`
- `materialize --tournament-id 173 --replace` → 5 stages, 20 fixtures, 20 links
- `verify-standings-stage-id` OK after `backfill-standings-stage-id`
- RR stage display name **`Round 1 - League`** (`stage_key` `round-1`; witness `g.phase` unchanged)

---

## Anti-patterns

- Assuming bulk `apply-structure` will finish the catalog
- Trusting disposition `handler` alone (Frankfurt was `structure_spec` but blocked in tier-B)
- `generate-disposition-register` without merging hand-edited `notes` / review state
- Skipping `--dry-run` on first materialize for an id
- Writing a `StructureSpec` for every mixed legacy cup (legacy materialize is enough when phases are labeled)