# Amiga modern video — policy (living ground)

**Status:** **Shipped (Jul 2026)** — V-1 + PROMOTE-1: work manifest promoted via `promote-video-deploy` / `export_ko2amiga_work.ps1`; simul includes video by default.

**Parent:** [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) (§11 Video, slice **V-1**) · [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md) (product + UI)

**Legacy mechanics (frozen oracle path):** [`amiga-tournament-videos-game-links-policy.md`](amiga-tournament-videos-game-links-policy.md) — match-fact remap after nuclear reimport; **still true on `ko2amiga_db` + `prove`**.

**Related:** [`amiga-modern-simul-implementation-plan.md`](amiga-modern-simul-implementation-plan.md) § S-1.8 · [`scripts/amiga/tournament_videos/README.md`](../scripts/amiga/tournament_videos/README.md)

---

## 1. Executive summary

On **`ko2amiga_work`**, L3 game ids **stop churning** after day 0. Video links can treat **`amiga_games.id`** (the id in games-table column 1 and `/amiga/game.php?id=`) as the **canonical binding** once a row is verified — not koatd `source_scores_id`, not a perpetual remap pass.

Legacy **`prove`** needed match facts as authority because every nuclear reimport reassigned auto-increment ids. Modern **simul** does not truncate L3; video align becomes **validate + refresh name/tournament caches**, not “repair reimport damage.”

**MG11 for data:** fork video **write paths** and **work manifest** outputs; do not mutate oracle-tuned files under `data/amiga/tournament_videos/` or legacy `sync_db_ids` behaviour on `ko2amiga_db`.

---

## 2. Legacy vs modern (binding model)

| Topic | Legacy (`prove` on `ko2amiga_db`) | Modern (`simul` on `ko2amiga_work`) |
|-------|-------------------------------------|--------------------------------------|
| L3 ground | Truncated + reloaded from Access witness | **Frozen** historical games; append-only forward |
| `amiga_games.id` | **Unstable** across full prove runs | **Stable** for day-0 games; stable for new games after insert |
| Canonical link (verified) | **Match facts** in git (tournament + players + score + stage + leg) | **`game_ids[]` / `game_id_guess`** on work DB |
| `source_scores_id` | Witness key inside DB; useful for remap | **Provenance only** on historical rows; not curation target |
| Match facts | **Authority** — sync remaps ids from facts | **Curation + audit** — used to find/link; verify facts still match id |
| `sync_db_ids` role | Remap all caches after reimport; could overwrite heuristics | **Validate** verified links; refresh tournament/player id caches from names; **do not re-guess** locked rows |
| Catalog files | Single shared `review.csv` + `tournament_videos.json` | **Work outputs** separate from **oracle snapshot** (§4) |

**Product UI unchanged:** PHP still reads `tournament_videos.json` and `game_ids[]`. **PROMOTE-1** points runtime manifest at work build.

---

## 3. Identifiers (modern)

| Identifier | Role on work |
|------------|----------------|
| **`youtube_id`** | Stable editorial primary key for a clip |
| **`amiga_games.id`** | **Canonical** game pointer after verify (same as website games table id column) |
| **`tournaments.id`** | Canonical tournament pointer after verify (opaque; **not** chronological — sort by `event_date` / `chrono`) |
| **`amiga_players.id`** | Player cache in manifest |
| **Match facts** | How humans pick the game; verify oracle that id still matches DB goals/players |
| **`source_scores_id`** | Historical witness metadata on `amiga_games`; optional secondary cache; **not** the forward curation key |

Post–day-0 / live-ops games may have **no** koatd row — only **`amiga_games.id`** is universal.

---

## 4. File compartments (MG11)

Do **not** run modern align against legacy write targets.

```text
Shared editorial (git, human-curated)
  data/amiga/tournament_videos/review.csv     — stable columns: youtube_id, kind, players, score, stage, leg, verified, notes, …
  data/amiga/tournament_videos/video_game_links.csv
  data/amiga/tournament_videos/dropped.csv
  manual_rows.py, harvest raw/ (gitignored)

Oracle snapshot (read-only, P-1 baseline — seal at D0-2 / V-1.0)
  data/amiga/oracle/tournament_videos/      — copy of review + manifest aligned to frozen ko2amiga_db

Work outputs (writable by modern tooling only)
  data/amiga/work/tournament_videos/review.csv          — work DB cache columns + editorial mirror or overlay
  data/amiga/work/tournament_videos.json                — manifest for ko2amiga_work (PHP after PROMOTE-1)
  site/public_html/data/amiga/tournament_videos.json    — deploy copy from work build when promoted
```

**Rule:** Editorial facts live in **one** place (shared). **DB cache columns** and **built manifest** are **per-world** until PROMOTE-1 collapses to work-only.

**Forbidden:** `modern/video_align.py` calling legacy `sync_db_ids.run()` with default paths (current S-1.8 debt).

---

## 5. Modern video align (simul step)

When `python -m scripts.amiga simul` (video on by default; `--skip-video` to opt out):

1. Target DB = **`ko2amiga_work`** only (`KO2AMIGA_DATABASE`).
2. **Always refresh** shared editorial `video_game_links.csv` + `dropped.csv` into work; **`start_sec` reads from shared git path only** (work must not fork editorial).
3. For **verified / locked** rows: assert `game_id_guess` exists in work DB; assert facts match DB; **do not** heuristic-replace id.
4. For **unverified** rows: allow resolver proposals (same fact machinery as legacy).
5. Refresh `tournament_id` / `player_*_id` from names where needed.
6. Write **work** `review.csv` cache columns + rebuild **work** manifest.
7. Run `verify-tournament-videos` against work — includes **`game_start_sec[]` vs shared sidecar parity** (catches minutes-vs-seconds drift).

**Default:** simul runs video align + verify unless `--skip-video`. **`promote-video-deploy`** snapshots prior deploy manifest, runs **align**, enforces **`game_start_sec[]` parity**, then copies work → deploy. Export calls **`promote-video-deploy`** only (align is inside promote). Legacy **`sync_db_ids`** / **`build_manifest`** refuse when `KO2AMIGA_DATABASE=ko2amiga_work`.

Harvest (`harvest`, `apply_review`, `resolve_games`) remains **offline editorial** — not every simul.

---

## 6. Curation workflow (forward)

| Step | Tool | Notes |
|------|------|-------|
| Find match | Human / `resolve_games` proposal | Facts help search |
| Lock link | Set `game_id_guess` + `verified=Y` (and `game_link_mode` / sidecar if multi-game) | **Id is the commitment** on work |
| Simul | default (or `--skip-video`) | Validates; rebuilds work manifest |
| New community game | Live ops → new `amiga_games.id` | Link video to that id directly; no koatd key |

Dual-leg / stream_map rules from [game-links policy](amiga-tournament-videos-game-links-policy.md) §8 still apply to **link count**; authority of each linked id is **work `amiga_games.id`**, not remap-from-facts.

---

## 7. Relationship to legacy game-links policy

[`amiga-tournament-videos-game-links-policy.md`](amiga-tournament-videos-game-links-policy.md) remains the spec for **GL-0…GL-6** on the **prove** path (fact remap, verify oracle, sidecar).

This doc **extends** it for **living ground**:

- §4 three-layer diagram: on work, layer C (`game_ids[]`) is **stable truth** when verified, not derived fresh each simul.
- Legacy §3–§4 “`amiga_games.id` not stable” → **true only on nuclear reimport path**.

Do not edit legacy policy to contradict prove behaviour; agents implementing work use **this doc**.

---

## 8. Cutover slices (V-1)

| ID | Work | Exit |
|----|------|------|
| **V-1.0** | Seal oracle video snapshot under `data/amiga/oracle/tournament_videos/` from current `ko2amiga_db`-aligned catalog | **Done** — `python -m scripts.amiga seal-video-oracle` |
| **V-1.1** | `modern/constants` video paths; fork `sync_db_ids` / `build_manifest` writers with path overrides (**MG11**) | **Done** — `modern/video_catalog.py` + `work_video_paths()` |
| **V-1.2** | Seed work catalog from shared editorial + align to `ko2amiga_work` (day-0 ids should match oracle caches today) | **Done** — align + verify green; 13 non-fatal remap escalations |
| **V-1.3** | Wire `simul --with-video`; add `verify-tournament-videos` to modern suite when video on | **Done** — verify suite step when `include_videos` |
| **V-1.4** | **PROMOTE-1** hook: PHP reads work manifest (or copy step in export) | **Done** — `promote-video-deploy` + `export_ko2amiga_work.ps1` |

**Out of scope V-1:** Lane C DB migration · re-harvest cadence changes · rewriting PHP read paths (already id-based).

---

## 9. Agent rules

1. **Modern simul / video** → read **this doc** + [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md).
2. **Legacy prove / oracle** → [`amiga-tournament-videos-game-links-policy.md`](amiga-tournament-videos-game-links-policy.md).
3. **MG11:** copy `tournament_videos/*.py` entry points into `scripts/amiga/modern/` before changing connect/paths; do not mutate legacy modules for V-1.
4. **Do not** treat `source_scores_id` as the forward video key.
5. **Do not** share writable manifest between oracle and work.

---

## 10. Changelog

| Date | Change |
|------|--------|
| 2026-07-08 | **PROMOTE-1** — `export_ko2amiga_work.ps1`, `promote-video-deploy`, simul video default on. |
| 2026-07-08 | **V-1 shipped** — work/oracle compartments, align + verify CLIs, simul video wiring; path patch for legacy module imports. |
| 2026-07-08 | Policy locked — canonical `amiga_games.id` on work, file compartments, V-1 slices; legacy fact-remap scoped to prove path. |