# Amiga tournament structure — manual materialize runbook

**Status:** Forward path for **WC structure** (Jul 2026). **Non-WC materialize tail complete** on `ko2amiga_work` — **583/606** catalog ids have stages; remaining **23** without stages = `wc_deferred` World Cups only. Bulk `apply-structure --from-disposition` closed tier-A/B bootstrap (~515+ with fixture linkage); manual runbook cleared the labeled-phase long tail through **284** Athens LIII.

**Authority:** [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) T3, T8–T11 · **display end state:** [`amiga-tournament-structure-display-policy.md`](amiga-tournament-structure-display-policy.md) · **handlers reference:** [`amiga-tournament-structure-handlers.md`](amiga-tournament-structure-handlers.md) · **decision log:** [`amiga-tournament-structure-review-queue.md`](amiga-tournament-structure-review-queue.md)

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
# Near-complete NULL-phase RR (one missing pairing / ±1 game per player): add --force after human sign-off (e.g. id 174).
# Single early exit (one player, spread=2, all missing pairings involve that player only): --force also OK (e.g. id 281 Athens L).
```

| Handler in disposition | Typical CLI |
|------------------------|-------------|
| `pure_rr` / mixed labeled phases | `materialize` (legacy) — **most manual tail** |
| `pure_knockout` | `materialize-pure-knockout --tournament-id {id} [--replace]` after preview |
| `structure_spec` + **active** registry spec (rare) | `apply-structure` / spec path — not this runbook |
| `wc_deferred` | WC track — defer |

**Labeled phases** (Frankfurt): materialize bootstraps stage `name` from game `phase` text; KO ties → one stage per pair (policy T3). **Then triage display names manually** — `tournament_stages.name` is UI authority; leave `g.phase` as witness.

```sql
-- Example (Frankfurt 173 / Venice 64): single RR block in league+cup → display name League
UPDATE tournament_stages SET name = 'League'
WHERE tournament_id = 173 AND stage_key = 'round-1';
```

**Bulk null-phase marathons (Jul 2026):** 503 single-stage tier-A events had materialize default `Overall` — renamed to **`League`** on work (`stage_key` stays `overall`; only when `stage_count = 1` and `stage_type = round_robin`).

**KO display names (manual):** witness `Round 1` on a pure cup often means QF — prefer stage name **`Quarter Finals`** when bracket size fits (604: 8p → 4 ties). Plural **`Semi Finals`** matches catalog majority over `Semi Final`. Leave `g.phase` as witness; edit `tournament_stages.name` only.

**KO `phase_label` (finish):** standings scope uses `fixture_phase_label` before stage name. Witness labels that do not normalize to honours tiers break finish — e.g. **`Finals`** (plural) is not **`final`**. For manual KO materialize: set `tournament_fixtures.phase_label = NULL` on knockout fixtures (or rely on `materialize_legacy` which now NULLs KO `phase_label`); then `backfill-standings-stage-id` + `refresh-event-finish-snapshots`. Example: Milan XII **166** (g5954–56).

### 5. Standings + structure verify

```powershell
python -m scripts.amiga backfill-standings-stage-id --tournament-id {id}
python -m scripts.amiga verify-standings-stage-id --tournament-id {id}
python -m scripts.amiga standings-parity --tournament-id {id} --sweep --only-failures
```

`standings-parity` may **SKIP** events Access does not mirror (mixed league+cup) — not always a failure.

Browser spot-check: standings scopes + games tab.

### 5b. SC-11 extension review (ET and/or penalties)

Any game with `extra` suggesting extra time or penalties needs **human review** — pens-only witness often still followed ET (parenthetical may be post-ET or ET-period score). **Workflow and what counts as verified:** [`match_extensions_verified_register.json`](../scripts/amiga/match_extensions_verified_register.json) `workflow` section (parser/backfill = guess only; register entry = verified). Bulk `backfill-match-extensions` skips ids already in `games`.

**Agent handoff (each game):** tournament games URL, `game_id`, `source_scores_id`, players, phase, **verbatim `access_witness_extra`**, structured cols (parser guess), and **forum context** (URLs from disposition/review queue, game-specific hints if documented) — see register `workflow.agent_handoff`. CLI: `list-extension-review`.

```powershell
python -m scripts.amiga list-extension-review --tournament-id {id}
python -m scripts.amiga backfill-match-extensions   # starting guess for goals_et_* / pens_* — not verification
```

After correcting `goals_et_*` / `pens_*` on a game: `backfill-standings-stage-id --tournament-id {id}` + `refresh-event-finish-snapshots` when knockout finish may change. Then add the game to the verified register.

**Ground score corrections (Type B):** changing regulation `goals_a` / `goals_b` on `amiga_games` also invalidates L5 derived truth that was built from the old result — `amiga_game_ratings.sum_of_goals`, per-player career `GoalsFor` / `GoalsAgainst`, realm goal totals, participation `goals_for`, standings, snapshots, etc. **`refresh-event-finish-snapshots` alone is not enough.** Run a **full simul replay** on work after ground is correct (`modern/replay` path per [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md)). Spot-check: `SUM(amiga_player_current.GoalsFor)` vs fresh tally from `amiga_games` (should match `SUM(goals_a)+SUM(goals_b)`).

### 5c. Tier E finish overrides (when derivation is wrong)

Use only when tiers A–D cannot express the institutional finish ladder ([`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) Tier E).

| Rule | Detail |
|------|--------|
| **Full ladder** | If *any* override row exists for `{id}`, insert **all** entrants at positions `1..N` — not a single patched slot |
| **Source** | Derive once (or witness/forum), human-verify the full order, then write every row |
| **Table** | `amiga_tournament_finish_override` (L3; survives simul) |
| **After insert** | `refresh-event-finish-snapshots --tournament-id {id}` (honours on snapshots); full simul if other derived tables may be stale |

Example (145 Milan V): eight rows — Gianni 1 … Marco 7, Sandro 8 (withdrew after groups).

### 6. Record (same session)

| What | Where |
|------|--------|
| One-line decision + date | [`amiga-tournament-structure-review-queue.md`](amiga-tournament-structure-review-queue.md) § Review log |
| Session handoff | `PROJECT_MEMORY.md` Recent log |
| Optional `notes` tweak | `disposition_register.json` |

**Do not** mark “materialized” only in JSON — confirm DB counts.

### 6b. Stale register hygiene (after materialize)

Git registers are **triage memory** — they must not block ids that already have stages on work.

| Symptom | Fix |
|---------|-----|
| `materialize` refuses id that **already has** `tournament_stages` | Remove id from `NON_WC_SLICE6_CUP_REVIEW_IDS` / `NON_WC_ORIGINAL_STRUCTURE_REVIEW_IDS` / `STRUCTURE_REVIEW_TOURNAMENT_IDS` in `tier_b_non_wc_register.py` / `materialize_legacy.py` |
| `disposition_register.json` still `pending_review` but DB has stages | Promote `handler` to shipped value (`pure_knockout`, `structure_spec`, …) + one line in review log |
| Unclear what is stale | `python -m scripts.amiga tournament-structure audit-review-register` — exit **0** = clean |

**Never** run blind `generate-disposition-register` to “fix” hand-edited `notes` / promoted handlers.

```powershell
python -m scripts.amiga tournament-structure audit-review-register
python -m scripts.amiga tournament-structure verify-disposition-register
```

### 7. Staging (when ready)

`powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_work.ps1` → WinSCP → import preview/apply. See [`amiga-staging-handoff.md`](amiga-staging-handoff.md).

---

## Gates cheat sheet

| Question | Answer |
|----------|--------|
| May I run `materialize`? | Id **not** in tier-B / structure review frozensets (§2) |
| Stale blockers after ship? | `audit-review-register` — remove id from frozenset + promote disposition (§6b) |
| What does disposition `handler` mean? | Bulk **routing hint** — not materialize permission |
| `structure_spec` without registry spec? | Use **legacy `materialize`** after triage, not bulk apply |
| Promotion graph (D18)? | **Not** required for catalog materialize |
| Display module names on games tab? | **`tournament_stages.name` first** via `amiga_rated_games_from_sql()` (`COALESCE(s.name, g.phase)`); standings links use `stage_id` when set |

---

## Reference: Frankfurt (173), Jul 2026

- Cleared from `NON_WC_SLICE6_CUP_REVIEW_IDS`
- `materialize --tournament-id 173 --replace` → 5 stages, 20 fixtures, 20 links
- `verify-standings-stage-id` OK after `backfill-standings-stage-id`
- RR stage display name **`League`** when one RR block in league+cup (`stage_key` often `round-1` or `overall`; witness `g.phase` unchanged). Use **`Round N - League`** only when multiple RR stages need ordinals.

---

## Anti-patterns

- Assuming bulk `apply-structure` will finish the catalog
- Trusting disposition `handler` alone (Frankfurt was `structure_spec` but blocked in tier-B)
- `generate-disposition-register` without merging hand-edited `notes` / review state
- Skipping `--dry-run` on first materialize for an id
- Writing a `StructureSpec` for every mixed legacy cup (legacy materialize is enough when phases are labeled)