# Post-game cutover checklist (agent / Dagh)

> **Jun 2026 — forward cutover:** Live post-game target is **PHP `ops/dispatch.php`** (`ProcessCompletedGame`, `FinalizeUtcDay`), not new C++ merges. Steve runbook: [`post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md). Prep proof: [`cutover-readiness.md`](cutover-readiness.md). Legacy C++ = read-only reference ([`ratings_cpp.txt`](../ratings_cpp.txt)).

**Purpose:** One-page index of **contract vs legacy C++** deltas before Steve **enables PHP ops** on live. **Policy detail stays in** [`website-data-contract.md`](../website-data-contract.md) — do not duplicate rules here.

**When to use:** Planning live cutover, ops simul parity checks, or “what did we decide about peak vs club milestones?”

---

## Authority map

| Topic | Document |
|--------|----------|
| All aggregate tables + per-game order | [`website-data-contract.md`](../website-data-contract.md) — **Derived data index**, § **Post-game derived-data behavior** |
| Career `PeakRating` / `LowestRating` | Contract § **Career peak and nadir** (`playertable`) |
| `club_1700` … `club_2300` milestones | Contract § `player_milestones` — **Rating club** + implementation notes below |
| Personal BL/BW/MGC/MGS + inverse counts | Contract § **Personal record pointers**; site copy: `ranked5.php` at cutover |
| HoF `generalstatstable` | [`records-post-game-exception.md`](records-post-game-exception.md) |
| PHP post-game + cutover | [`ladder-ops-platform.md`](../ladder-ops-platform.md) §2, [`post-game-register.md`](post-game-register.md), `ops/run_process_game.php` |
| Legacy C++ (retiring) | [`ratings_cpp.txt`](../ratings_cpp.txt) — field order reference only |
| Core ladder Elo replay (dev/sandbox) | `scripts/run_local_replay.ps1` · `python -m scripts.ladder run` — **not** website aggregate cutover |
| Website aggregates (work/staging/prod copy) | [`cutover-readiness.md`](cutover-readiness.md) — `ops/run_ops_sim.php` + `run_verify_ops_sim.php` |
| Historical May staging one-shot | [`STAGING_REPLAY.md`](../STAGING_REPLAY.md) → archive stub only |

---

## Deliberate breaks from legacy C++ (must not miss)

| Area | Legacy (today) | Target (contract) |
|------|----------------|-------------------|
| Career peak / nadir | From game 1; peak only on rating **gain** in that game | **Unset until 20 games**; at game 20 set **both** from post-game **`Rating`**; game 21+ max/min of **`Rating`** every game |
| `club_*` milestones | *(no live writer)*; rebuild uses `PeakRating` join | Post-game: first **`Rating` ≥ threshold** (any game #, including &lt; 20); rebuild: drop `PeakRating` join when peak-at-20 replay ships |
| Personal record pointers | `>=` on margin | **`>`** — first holder keeps on tie |
| HoF records | `>=` on many fields | **`>`**; stop writing ratio leader cols to `generalstatstable` |
| `player_milestones` (most keys) | Not in prod C++ | Full writer per contract M1–M7 |

---

## Do not conflate

| Name | Meaning |
|------|---------|
| `playertable.PeakRating` / `LowestRating` | Career peak/nadir (exist after **20** games) — `ranked1.php`, profile |
| `playertable.Rating` | Current Elo — **`club_*` milestones** |
| `generalstatstable.BiggestPeakRating` | Server HoF record (separate) |
| `player_peak_period_games` | Activity “best period”, not Elo |
| `K2_ESTABLISHED_MIN_GAMES` (20) | LB filter, ratio leaders, **and** when career peak/nadir **start** — same number, different roles |

---

## Rating club — rebuild status (May 2026)

- **Prod C++:** does **not** insert `player_milestones` (any key). New games do not unlock milestones until live writer or rebuild.
- **REP-008 SQL:** `player_milestones_rebuild.sql` — four keys only (`club_1700`, `1800`, `2000`, `2300`). Uses first `NewRating` cross + `playertable.PeakRating >= thresh`.
- **Local replay verify:** counts and first-unlock **games** match “first `NewRating >= threshold`” for all four keys; `PeakRating` join excludes **no one** on legacy peak data.
- **After peak-at-20 replay:** remove `PeakRating` join in rebuild; align live post-game with **`Rating`** only (or provisional players with `Rating >= 1700` and `PeakRating` still unset would miss `club_*`).

`club_1900` / `elite_altitude`: ideas/probes only — **not** in 112-key catalog or rebuild.

---

## Cutover sequence (minimal)

1. Staging: schema (if any) + **dry-run** new post-game on test game(s).
2. Staging: **full ladder replay** + `rebuild_website_derived_data_local.ps1` (or prod equivalents).
3. Parity: contract § **Global validation checklist** + milestone sanity scripts.
4. Prod: `migrate-work` → `seed-catalog` → `zero-derived` → **ops simul** → enable **PHP** `dispatch.php` on live games; retire C++ derived writer.
5. Site: `ranked5` tooltips/footer if personal `>` shipped; profile peak/nadir tooltips when ready.
6. **feature-log** + **MEMORY** — Prod live / done date.

---

## Related MEMORY / feature-log

- `PROJECT_MEMORY.md` — Recent log (career peak/nadir, milestones).
- `feature-log.md` — **Live cutover = Not executed** means Steve go-live scheduled, not incomplete repo work.
