# Amiga profile — time travel implementation plan

**Status:** **Implemented** (Jul 2026-14) — slices 0–4 complete. [`amiga-profile-time-travel-implementation-plan.md`](amiga-profile-time-travel-implementation-plan.md)

**Policy:** [`amiga-profile-time-travel-policy.md`](amiga-profile-time-travel-policy.md)

**Parent:** [`amiga-time-travel-implementation-plan.md`](amiga-time-travel-implementation-plan.md) (phase 1 complete; Profile was explicitly deferred) · [`amiga-profile-v0.md`](amiga-profile-v0.md)

**Migration:** **L0** — PHP read-path only. **No Part B** unless audit finds missing snapshot columns (unlikely — `*GameID` and peak fields exist on `amiga_player_event_snapshots` per `024_player_snapshots.sql`).

---

## In scope

- Profile tab: LB mosaic, Moments, Videos nav pill, TT chrome flags, render-order fix
- Doc closure: `amiga-profile-v0.md`, `amiga-time-travel-policy.md` T12, `amiga-player-universe-contract.md` §4

## Out of scope

- Online profile
- Other player wings (Games / Tournaments / Opponents / Videos page bodies)
- New moment card types
- DDL / Python finalize / simul (unless parity probe fails)
- Git commit unless Dagh asks

---

## How to use this plan

1. Execute slices **in order**.
2. One slice per session; browser STOP gate before next slice.
3. **Do not commit** unless Dagh asks.
4. After slice 4: **UPDATE_DOCS** Part A — MEMORY, policy status → Implemented, cross-links, optional `feature-log.md` L0 row.

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Policy + this plan | User OK — **done** Jul 2026-14 |
| **1** | Chrome render order — `$k2AmigaPlayerTabActive` before `site_header` on all `/amiga/player/*` shells | Profile shows unwired note **until** slice 4; other wings unchanged — **done** Jul 2026-14 |
| **2** | LB mosaic cutoff read path | Browser: mosaic numbers match snapshot row at cutoff; present unchanged — **done** Jul 2026-14 |
| **3** | Moments cutoff read path + cutoff game fetch | Browser: no post-cutoff game cards; peak card ≤ present — **done** Jul 2026-14 |
| **4** | Videos pill + wired flag + doc closure | No unwired note on Profile; pill respects cutoff — **done** Jul 2026-14 |

---

## Slice 1 — Chrome render order

### Goal

TT ribbon can see player tab context when rendering unwired note (and any future per-tab chrome).

### Tasks

- [x] Audit `/amiga/player/*.php` — set `$k2AmigaPlayerTabActive` (and `$k2AmigaPlayerTabWiredAtCutoff` where already used) **before** `include site_header.php`
- [x] Files: at minimum `profile.php`; align `games.php`, `tournaments.php`, opponents shells, `videos.php` for consistency
- [ ] **Do not** set `$k2AmigaPlayerTabWiredAtCutoff = true` on Profile until slice 4

### Verification

- Open `/amiga/player/profile.php?id={id}&as=event:{mid}` — transitional note **visible** in TT ribbon (expected until slice 4)
- Videos tab still suppresses note (already wired)

---

## Slice 2 — LB mosaic at cutoff

### Goal

`amiga_profile_lb_slices_load()` returns present row or cutoff snapshot row with same shape renderers expect.

### Tasks

- [x] Require `amiga_snapshot_context.php` in `amiga_profile_lb_slices.php`
- [x] Present: keep current `amiga_player_current` query (rename helper e.g. `amiga_profile_lb_slices_load_present()`)
- [x] Cutoff: load via `amiga_player_snapshot_row_at_cutoff()`; map column names to existing render functions (snapshot uses same career column names as current)
- [x] Peak-rating subsection joins (`peak_rating_tournament_id`, `peak_elo_rank_tournament_id`) — resolve tournament names/dates from **snapshot** IDs at cutoff, not `amiga_player_current`
- [x] Pre-debut: return `null`; `amiga_profile_render_lb_slices()` already no-ops on null — confirm matches **PPT9**
- [x] `profile.php`: pass `$ctx` or rely on `amiga_snapshot_context_from_request()` inside loader (ensure context initialized by `amiga_player_load` before close — **do not close DB before slice load** if loader needs `$con`)

### Reference

- Cutoff row helper: `site/public_html/includes/amiga_player_snapshot_lib.php` — `amiga_player_snapshot_row_at_cutoff()`
- LB wings at cutoff: leaderboards use same snapshot pattern via `amiga_lb_snapshot_lib.php`

### Verification

```text
Pick player with games spanning cutoff C:
  Present mosaic field X = amiga_player_current
  Profile ?as=event:C     field X = snapshot row at C (≤ present)
  Profile ?as=pre-debut     mosaic absent
```

Spot-check: Results games count, Victims DifferentOpponents, Peak rating value, honours event_gold.

---

## Slice 3 — Moments at cutoff

### Goal

Moments bundle built from snapshot pointers at cutoff; games resolved only within lens.

### Tasks

- [x] Refactor `amiga_player_moments_load($con, $playerId, ?AmigaSnapshotContext $ctx = null)`:
  - Present: `amiga_player_current_row()` (today)
  - Cutoff: snapshot row from **PPT2**; empty bundle if pre-debut
- [x] `amiga_player_moment_fetch_games()` — add cutoff SQL via `amiga_snapshot_rated_game_cutoff_and_sql()` when ctx active
- [x] `amiga_player_moment_load_bonanza_ratio_fallback()` — append same cutoff SQL to WHERE
- [x] Peak moment: use snapshot `PeakRating` + `peak_rating_tournament_id` (not current row)
- [x] Best scalp: snapshot `HighestRatedVictim` + `HighestRatedVictimGameID`
- [x] `profile.php`: pass ctx from `amiga_snapshot_context_from_request($con)` (after player_load establishes context)

### Verification

- Mid-career cutoff: moment game dates all ≤ cutoff event date
- A trophy earned only after cutoff **must not** appear
- Present mode: card set unchanged (regression — e.g. Oliver St / Dagh N smoke URLs in `amiga-profile-v0.md`)

---

## Slice 4 — Videos pill + wired closure

### Goal

Profile nav and chrome declare full TT compliance.

### Tasks

- [x] `amiga_player_has_videos($playerId, $con, ?AmigaSnapshotContext $ctx = null)` — when ctx active, use `amiga_player_videos_game_index($con, $playerId, $ctx)` instead of manifest-only shortcut; hide pill when index empty
- [x] `profile.php`: set `$k2AmigaPlayerTabWiredAtCutoff = true` before header (with slice 1 order)
- [x] Policy status → **Implemented**; update:
  - [`amiga-profile-v0.md`](amiga-profile-v0.md) — remove present-only on LB slices; note TT compliance
  - [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) — T12 / §4.3 Profile blocks → shipped
  - [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §4 surfaces register
- [x] **UPDATE_DOCS** Part A — `PROJECT_MEMORY.md` line

### Verification

- Profile `?as=` — **no** unwired banner
- Videos pill hidden when no videos ≤ cutoff; shown when clips exist ≤ cutoff
- Full acceptance checklist in policy §6

---

## Environment

| Item | Value |
|------|--------|
| Work DB | `ko2amiga_work` |
| PHP | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` |
| Proof | Browser on `ratingskickoff.test`; optional SQL spot-check snapshot row vs mosaic cell |

**No simul required** for L0 read-path — unless a probe shows snapshot column drift vs `amiga_player_current` (then stop and file schema gap; do not ship live aggregation workaround).

---

## Files (expected touch)

| File | Slice |
|------|-------|
| `site/public_html/amiga/player/profile.php` | 1, 4 |
| `site/public_html/amiga/player/games.php`, `tournaments.php`, opponents shells | 1 |
| `site/public_html/includes/amiga_profile_lb_slices.php` | 2 |
| `site/public_html/includes/amiga_player_moments_lib.php` | 3 |
| `site/public_html/includes/amiga_player_videos_lib.php` | 4 |
| `docs/amiga-profile-time-travel-policy.md` | 0 |
| `docs/amiga-profile-v0.md`, `amiga-time-travel-policy.md` | 4 |

---

## Changelog

| Date | Change |
|------|--------|
| 2026-07-14 | Track **complete** — slices 0–4 shipped; Profile tab fully TT-compliant under `as=` |
| 2026-07-14 | Slice 3 — moments bundle from snapshot pointers at cutoff; game fetch + bonanza fallback respect `amiga_snapshot_rated_game_cutoff_and_sql()` |
| 2026-07-14 | Slice 2 — LB mosaic reads snapshot row at cutoff via `amiga_profile_lb_slices_load_at_cutoff()`; present path renamed `load_present()` |
| 2026-07-14 | Slice 1 — tab-active + wired flags before `site_header` on profile, games, tournaments, opponents; videos already correct |
| 2026-07-14 | Plan created — four implementation slices after locked policy |