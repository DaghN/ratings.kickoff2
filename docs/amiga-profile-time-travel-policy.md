# Amiga profile — time travel policy

**Status:** **Implemented** (Jul 2026-14) — Profile tab fully time-travel compliant under `as=`. Plan: [`amiga-profile-time-travel-implementation-plan.md`](amiga-profile-time-travel-implementation-plan.md).

**Parent:** [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) (T15 uniform lens · T17 pre-debut) · [`amiga-profile-v0.md`](amiga-profile-v0.md) · [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §4

**Related:** [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md) §9.5 (Videos tab)

---

## 1. Purpose

When **`as=`** is active on `/amiga/player/profile.php`, every **profile-tab** block shows the player **as of the shared cutoff** — hero, LB wing mosaic, Moments, charts, and Videos nav pill (PPT1–PPT14).

**Shipped Jul 2026-14:** LB mosaic (`amiga_profile_lb_slices.php`), Moments (`amiga_player_moments_lib.php`), Videos pill (`amiga_player_has_videos()` + cutoff game index), TT chrome wired flag on Profile.

---

## 2. Scope

| In scope | Out of scope |
|----------|----------------|
| `/amiga/player/profile.php` tab body + profile-tab chrome flags | Other player wings (separate TT tracks; largely wired Jun 2026) |
| LB wing stat mosaic (`amiga_profile_lb_slices.php`) | Online realm profile |
| Moments (`amiga_player_moments_lib.php`) | New moments types or streak cards |
| **Videos** pill visibility on profile nav when `as=` active | Videos wing page behaviour (already wired) |
| TT chrome: wired flag + tab-active render order | Activity hub charts |
| Verify existing hero + chart TT behaviour unchanged | DDL / finalize writer changes |

**Migration tier:** **L0** — read-path + PHP only; snapshot columns already exist on `amiga_player_event_snapshots` and `amiga_player_current`.

---

## 3. Locked decisions

| # | Decision | Rule |
|---|----------|------|
| **PPT1** | **Uniform profile lens** | When `as=` is valid, **all** profile-tab derived stats and trophy cards reflect the cutoff. No silent present-day numbers (extends parent **T15**). |
| **PPT2** | **Present vs cutoff read** | **Present** (no `as=`): `amiga_player_current` (+ existing joins). **Cutoff**: latest `amiga_player_event_snapshots` row with `(event_date, event_chrono, tournament_id) ≤ cutoff` — reuse `amiga_player_snapshot_row_at_cutoff()`. |
| **PPT3** | **LB mosaic** | Same six wing sections and column set as today (Results · Goals · DDs & CSs · Victims & Culprits · Tournament honours · Calendar & geography · Peak rating). Values from **cutoff snapshot row**, not `amiga_player_current`. |
| **PPT4** | **Peak block semantics at cutoff** | `PeakRating`, `peak_rating_tournament_id`, `peak_elo_rank`, `peak_elo_rank_tournament_id`, `LowestRating`, `HighestRatedVictim`, `LowestRatedCulprit` — all from **snapshot at cutoff** (career peaks **through** cutoff, not all-time today). |
| **PPT5** | **Moments — trophy pointers** | `*GameID` fields and `HighestRatedVictim` / `HighestRatedVictimGameID` from **snapshot at cutoff**, same card order as present (Best scalp · Biggest win · Biggest draw · Goal festival · Total goals bonanza · Peak rating last). |
| **PPT6** | **Moments — game resolve** | Batched game fetch must apply **`amiga_snapshot_rated_game_cutoff_and_sql()`** so cards never surface games **after** the cutoff. |
| **PPT7** | **Moments — bonanza fallback** | Ratio fallback scan (`SumOfGoals` ordering) runs only on games **≤ cutoff** — not full-career `amiga_games` scan. |
| **PPT8** | **Moments — peak card** | Peak rating moment uses snapshot `PeakRating` + `peak_rating_tournament_id` at cutoff (tournament name/date from `tournaments`). Not present-day career peak. |
| **PPT9** | **Pre-debut (T17)** | No snapshot ≤ cutoff or zero games at cutoff: mosaic **omitted** (or empty — match present empty-state habit); Moments **hidden**; hero already shows — + note. |
| **PPT10** | **Videos tab pill** | Show **Videos** on profile nav only when the player has ≥1 video row **≤ cutoff** (same cutoff rules as `/amiga/player/videos.php`). Hide pill when none — do not link to an empty wing. |
| **PPT11** | **Charts** | Rating + rank charts **unchanged** — already pass `data-as` and filter API payloads ≤ cutoff. |
| **PPT12** | **Hero** | Rank · rating · games · events · WC count · WC medals **unchanged** — already cutoff-aware via `amiga_player_load()` + WC slice. |
| **PPT13** | **Link propagation** | All profile outbound links continue to preserve `as=` (`amiga_url_with_context`, `k2_amiga_route`). Moment game links must not drop the lens. |
| **PPT14** | **Wired chrome flag** | When this track ships, Profile sets `$k2AmigaPlayerTabWiredAtCutoff = true` and **suppresses** the transitional unwired note. |
| **PPT15** | **Tab-active before header** | All `/amiga/player/*` pages set `$k2AmigaPlayerTabActive` (and wired flag when applicable) **before** `site_header.php` so TT chrome notes and future per-tab chrome behave correctly. |

---

## 4. Data model

```text
Request with as=
       │
       ▼
amiga_snapshot_context_from_request()
       │
       ├─ present ──► amiga_player_current (+ peak tournament joins as today)
       │
       └─ cutoff ───► amiga_player_snapshot_row_at_cutoff()
                      │
                      ├─► LB mosaic row (wide career columns on snapshot)
                      ├─► Moments *GameID + PeakRating pointers
                      └─► Game rows (only if game_date/event ≤ cutoff)

Hero / charts: unchanged paths (already branch on ctx).
Videos pill: amiga_player_videos_game_index(..., ctx) non-empty.
```

**Authority:** Snapshot rows are finalize-time career state — same contract as leaderboards at cutoff ([`amiga-data-contract.md`](amiga-data-contract.md)). No live aggregation over `amiga_games` on profile hot path (**PPT6–PPT7**).

---

## 5. Rejected alternatives

| Alternative | Why rejected |
|-------------|----------------|
| Keep present mosaic + show unwired banner as end state | Misleading UX; banner does not render on Profile today; user wants full compliance |
| Recompute moments from live `amiga_games` at read time | Violates stored-truth habit; wide scans on hot path |
| Fork profile URL (`/amiga/player/profile-history.php`) | Parent **T6** — same paths, `as=` lens only |
| Show present moments with a per-section disclaimer | Noisy; T15 prefers uniform lens |
| Always show Videos tab; empty state on wing | Pill should reflect cutoff-visible content (**PPT10**) |

---

## 6. Verification (acceptance)

Browser on `ko2amiga_work` (or staged) with a player who has activity **before and after** a mid-career cutoff:

1. **Present** — profile unchanged vs today.
2. **`as=` mid-career** — mosaic counts **≤** present; moments cards only reference games on or before cutoff; peak moment ≤ present peak.
3. **`as=` pre-debut** — hero —; mosaic/moments absent; no present leakage.
4. **Videos pill** — hidden at cutoff when no clips ≤ cutoff; visible when clips exist.
5. **No** *"This page still shows present-day data."* on Profile when wired flag set.
6. **Charts** — x-axis ends at cutoff (regression).

---

## 7. Changelog

| Date | Change |
|------|--------|
| 2026-07-14 | **Implemented** — slices 0–4; Profile tab full cutoff lens (mosaic, moments, Videos pill, wired chrome) |
| 2026-07-14 | Policy locked — full Profile TT compliance; mosaic + moments + Videos pill; chrome order + wired flag |