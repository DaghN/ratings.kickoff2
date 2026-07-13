# Amiga tournament videos — game link anchors policy

> **Oracle mechanics (Jul 2026):** Match-fact remap after nuclear L3 reimport — true on frozen **`ko2amiga_db`** + legacy **`prove`**. **Forward living ground:** [`amiga-modern-video-policy.md`](amiga-modern-video-policy.md) — stable `game_id` on **`ko2amiga_work`**, **`align-video-work`**, simul video on by default.

**Status:** **Locked (Jul 2026)** — **GL-0…GL-6 shipped.** Match facts authoritative; sync remaps ids; verify oracle; dual-leg + **`video_game_links.csv`** sidecar (`stream_map` mode). **`verify_tournament_videos` OK** with empty sidecar (machinery ready for stream curation).

**Parent:** [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md) (product + catalog) · [`amiga-import-layer.md`](amiga-import-layer.md) (L3 witness / koatd)

**Related:** [`amiga-tournament-videos-implementation-plan.md`](amiga-tournament-videos-implementation-plan.md) · [`amiga-modern-video-policy.md`](amiga-modern-video-policy.md) (**living ground / `ko2amiga_work`** — canonical `game_id`, V-1) · [`k2-embedded-video-page-policy.md`](k2-embedded-video-page-policy.md) · [`scripts/amiga/tournament_videos/README.md`](../scripts/amiga/tournament_videos/README.md)

**Supersedes for game-link mechanics:** [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md) §12 bullet “Re-resolved from tournament + players + score via `resolve_games`” as the **sole** sync authority — that behaviour is **retired** by this policy.

**Authority:** Dagh + agent investigation (Jul 2026).

---

## 1. Executive summary

The tournament video catalog connects **YouTube clips** to **rated Amiga games**. That connection was implemented using **`amiga_games.id`** (MySQL auto-increment) as if it were a stable key. It is not — full L3 witness reimports rebuild ground tables and reassign auto-increment ids.

**Investigation conclusion:** Human curation already lives mostly in `review.csv` (tournament, players, score, stage, leg), but the **pipeline treats numeric game ids as curated truth** and **`prove` re-runs heuristics that overwrite overrides**. Verify checks id existence and loose player-pair match — **not** score correctness or multi-game links. Dual-leg videos (N=2) and future stream indexes (N≫2) share the same class of bug.

**Decision:** Split the pipeline into **editorial match facts** (stable, in git) vs **DB caches** (remapped on every sync). Sync **remaps** ids from facts; verify **fails loud** on any mismatch. Batch resolver proposes links for unverified rows only.

---

## 2. Problem statement

### 2.1 Symptoms observed (Jul 2026)

- Dual-leg knockout uploads (e.g. WC 2025 Milan final) appear **once** in video game indexes despite notes saying “dual-leg video”.
- Notes and `game_ids` can disagree after a full **oracle** `prove` reimport — editorial residue vs collapsed id list. **Forward path:** `align-video-work` on **`ko2amiga_work`** (V-1); simul includes video by default.
- Same underlying issue will block **long stream → many games** curation (dozens of links per `youtube_id`).

### 2.2 Root cause (not PHP UI)

Read paths already fan out **`game_ids[]`** to one index row per game (`amiga_tournament_videos_wc_game_index`, player Videos, All games “with videos”). The manifest simply **does not contain** all linked ids — zero manifest rows currently have `len(game_ids) > 1`.

Root cause is **catalog pipeline + verify**, not table rendering.

### 2.3 Resolver bug (batch layer)

`pick_game_ids()` in `resolve_games.py` returns early on **title home/away alignment** (exactly one game matches `player_a`/`player_b` order) **before** dual-leg detection. Two-leg finals with swapped home sides in leg 2 collapse to one id. Example: WC 2025 final `-OD-f0t92VQ` → `[27417]` instead of `[27417, 27418]`.

### 2.4 Structural bug (prove / sync)

Two pipelines exist; only one respects human overrides:

| Pipeline | Runs | Respects `ROW_PATCHES` / locks? |
|----------|------|----------------------------------|
| `apply_review` | Harvest refresh, manual curation | **Yes** — `apply_row_game_id_locks` after bulk match |
| `sync_db_ids` (in **`prove`**) | After every full L3 reimport | **No** — re-runs `resolve_row` on **all** match rows |

`verified=Y` does **not** protect `game_id_guess` from overwrite during sync.

---

## 3. Why Amiga differs from online

| | **Online realm** | **Amiga realm** |
|---|------------------|-----------------|
| Game ground truth | Ladder-owned; ids frozen in prod flow | Imported from **koatd** witness (L3); tables truncated and rebuilt on holy loop |
| External witness | None for website ids | Alkis zip / Access `Scores` can add, delete, or correct rows |
| Video catalog | Out of scope v1 | Curated map: **what was filmed** → **which match** |
| Stable game key | `ratedresults` / ladder id | **`source_scores_id`** in DB; **match fact tuple** in video editorial |

Videos are ground truth for **“this clip shows this match”**, not for **“MySQL row 27417 today”**. The bridge must be **match identity**, remapped to current `amiga_games.id` on each sync.

---

## 4. Three identifiers (do not conflate)

| Identifier | Stable across L3 reimport? | Role |
|------------|----------------------------|------|
| **`amiga_games.id`** | **No** | Runtime cache for PHP / manifest `game_ids[]` |
| **`source_scores_id`** | **Mostly yes** — unless koatd `Scores` row removed/changed | DB ground key (`UNIQUE` on `amiga_games`); import ordering; **should be synced into manifest as secondary cache**, not hand-edited |
| **Match fact** (tournament + players + score + stage + leg) | **Yes** (editorial) | **Authoritative** video→game link in git |

There is **no frozen global list of `amiga_games.id`** on the **legacy prove path** — remap from facts each reimport. **`source_scores_id`** is the witness-stable key inside the DB; **match facts** are the video-stable key in the catalog.

**Modern (`ko2amiga_work`):** after day 0, verified links use **stable `amiga_games.id`** as canonical — see [`amiga-modern-video-policy.md`](amiga-modern-video-policy.md). This section applies to **`prove` / `ko2amiga_db`**.

### 4.1 Alkis / koatd change scenarios

| Change | Effect on `source_scores_id` | Effect on `amiga_games.id` | What policy requires |
|--------|------------------------------|----------------------------|----------------------|
| New Scores row added | New id | Insert order may shift many auto-increment ids | Remap caches from match facts; verify passes if facts unchanged |
| Scores row deleted | Id gone | Stale manifest ids invalid | Verify **FAIL**; human updates editorial or witness |
| Score corrected in koatd | Same id, goals may change | Id may shift on full rebuild | Verify **FAIL** if CSV score ≠ DB goals |
| Player merge in witness | Unchanged per game | Player auto-increment ids shift | Remap `player_*_id` from names; game remap from facts |

---

## 5. What we already recorded vs gaps

Audit of `data/amiga/tournament_videos/review.csv` (Jul 2026):

| Metric | Count |
|--------|------:|
| `kind=match` rows | 233 |
| Both player names present | 233 |
| `score` present | 185 |
| `game_id_guess` present | 233 |
| `verified=Y` | 99 |
| Match rows with game id **but no score** | 48 |
| Manifest rows with `len(game_ids) > 1` | 0 |
| Rows tagged dual-leg in notes | 9 (all single id) |

**Recorded:** tournament label, players, stage/leg/score on most rows, human patches in `ROW_PATCHES`.

**Not recorded reliably:** multi-game links per video; score on ~20% of linked matches; verify-grade proof that cached id matches fact; `source_scores_id` cache in catalog.

Policy §12 ([`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md)) described stable vs cache columns correctly in prose — **implementation did not enforce it**.

---

## 6. What verify does today (insufficient)

`verify-tournament-videos` / `validate_catalog()` currently checks:

- Manifest ids exist in DB
- Tournament and player name ↔ id consistency
- First linked game’s **player pair** matches manifest players (order-insensitive)

It does **not** check:

- CSV **score** vs DB goals
- Second and later `game_ids` (dual-leg / stream)
- Re-resolution from match facts vs cached ids
- Ambiguous or missing resolution after koatd change

**Alarm system for “video ground truth vs game ground truth” does not exist yet.** This policy adds it in §8.

---

## 7. Locked architecture — three layers

```
┌─────────────────────────────────────────────────────────────┐
│  A. Video editorial (stable, human-curated, committed)       │
│     youtube_id + one or more match facts per linked game     │
│     tournament, player_a, player_b, score, stage, leg          │
│     optional: start_sec (later), game_link_mode                │
└────────────────────────────┬────────────────────────────────┘
                             │ deterministic resolve / remap
┌────────────────────────────▼────────────────────────────────┐
│  B. Witness ground (koatd → L3 → amiga_games)                │
│     source_scores_id = stable DB game key                     │
│     amiga_games.id = remapped cache                           │
└────────────────────────────┬────────────────────────────────┘
                             │ verify oracle (fail loud)
┌────────────────────────────▼────────────────────────────────┐
│  C. Shipped manifest (tournament_videos.json)                 │
│     game_ids[] + optional source_scores_ids[] (caches)        │
└─────────────────────────────────────────────────────────────┘
```

**Authority rule:** **A** wins on link count and match identity. **B** wins on whether a match exists in DB. **C** is always **derived** — never hand-edit JSON ids except via sync output committed after verify.

---

## 8. Locked process rules

### 8.1 Two pipeline roles (raw vs override)

| Layer | Purpose | Tools |
|-------|---------|-------|
| **Raw / batch** | Harvest, enrich, **propose** links for empty or `auto` rows | `harvest`, `resolve_games`, `apply_review` → `bulk_game_match` |
| **Override / verified** | Human evidence; **must not be shrunk or replaced** by heuristics | `ROW_PATCHES`, `verified=Y`, future `video_game_links.csv`, `game_link_mode` |

`apply_review` already applies overrides then **`apply_row_game_id_locks`**. **`sync_db_ids` must do the same** after remap (see §8.3).

### 8.2 `game_link_mode` (editorial column — shipped)

| Mode | Meaning |
|------|---------|
| *(empty)* / harvest default | Resolver may propose while unverified |
| `single` | Exactly one linked game (typical per-leg upload) |
| `multi` | Authoritative comma-separated link set — **count is sacred** (dual-leg, edited compilations) |
| `stream_map` | Links loaded from **`video_game_links.csv`** sidecar (long streams, N≫2) |

Treat `verified=Y` + explicit `ROW_PATCHES` / sidecar rows as **locks** even when mode is empty.

### 8.3 Sync (`sync_db_ids`) — remap, not re-guess

After every full L3 reimport (and in `prove`):

1. Refresh **`tournament_id`** / **`player_*_id`** from canonical names (existing behaviour).
2. For each linked game (from CSV + sidecar):
   - **Resolve** current `amiga_games.id` (+ `source_scores_id`) from **match fact** via deterministic lookup (shared with verify).
   - Write **`game_id_guess`** / manifest **`game_ids[]`** and optional **`source_scores_id`** cache.
3. **Do not** call `resolve_row` heuristics on rows with `verified=Y`, `game_link_mode` ∈ {`single`, `multi`, `stream_map`}, or `ROW_PATCHES` game lock.
4. **Never shrink** link count: if editorial has N facts and resolver returns `< N`, keep editorial set and **escalate** (verify will fail).
5. Run **`apply_row_game_id_locks`** (or equivalent) **after** remap.

**`resolve_games`** remains a **proposal tool** (`--all` on demand for harvest gaps), not the prove-time authority.

### 8.4 Verify oracle — fail loud (shipped)

For every match link (including each row in a multi-game sidecar), when `verified=Y` or link mode is locked:

1. Resolve match fact → DB game(s).
2. **Assert** cached `game_id`(s) match resolved id(s).
3. **Assert** if `score` present → DB goals match (home/away orientation rules documented in resolver).
4. **Assert** `stage` / `leg` consistent with DB `phase` when specified.
5. **Assert** `multi` / dual-leg / sidecar → `len(game_ids)` equals editorial link count.

**Outcomes:**

- No match → **FAIL** (koatd row gone or facts wrong)
- Ambiguous match → **FAIL**
- Id or score mismatch → **FAIL**
- Pass → safe to ship manifest

Prove must not succeed with a silently wrong link.

### 8.5 Multi-game links (N = 2 … dozens)

One **`youtube_id`** may map to **many games**. Mechanisms:

- **Dual-leg (N=2):** same as today’s comma-separated `game_id_guess` / `game_ids[]`; each leg is its own **match fact** (players may swap home/away; score may be empty until known).
- **Streams (N≫2):** sidecar **`data/amiga/tournament_videos/video_game_links.csv`** — one row per linked game; merged at build time.

**Timestamps (`start_sec`)** are optional metadata for in-video seek ([`k2-embedded-video-page-policy.md`](k2-embedded-video-page-policy.md)); forum stream indexes use **H:MM** wall-clock into the broadcast — store as `H:MM` or YouTube seconds in `video_game_links.csv` (see `scripts/amiga/tournament_videos/README.md`).

**UI (shipped Jul 2026):** tournament Videos → Games wing indexes all linked `game_ids[]` (`kind=match`, `game_link_mode` `multi`/`stream_map`); player Videos wing uses the same game-id fan-out filtered by participation.

---

## 9. Implementation plan (agreed order)

Do **not** reorder without updating this doc.

| Slice | Deliverable | Done when |
|-------|-------------|-----------|
| **GL-0** | **This policy doc** | Committed |
| **GL-1** | **Audit script** — for each editorial link, resolve from facts → compare to cached ids + score vs DB; report mismatches (dry-run, no writes) | **Shipped** — `audit_game_links.py` |
| **GL-2** | **Verify hardening** — score match, multi-id count, fact-vs-cache id oracle in `verify-tournament-videos` | **Shipped** — `validate_catalog` + `audit_row_links` |
| **GL-3** | **Sync rewrite** — remap-not-resolve; locks; no shrink; `apply_row_game_id_locks` in sync path | **Shipped** — dual-leg rows survive `prove` |
| **GL-4** | **Editorial backfill** — scores on gap rows; dual-leg facts; raise `verified=Y` where eyeball-verified | **Deferred** — human curation; code patches for dual-leg + reversed scores shipped |
| **GL-5** | **`video_game_links.csv` + `stream_map` mode** (schema + merge + verify) | **Shipped** — sidecar sync/build/verify; optional manifest `game_start_sec[]` |
| **GL-6** | **Policy §12 trim** — main videos policy points here for anchors; README + implementation plan updated | **Shipped** |

**Resolver fix** (`pick_game_ids` dual-leg ordering) belongs in **GL-3** or earlier as part of batch proposals — **not** treated as the durable fix alone.

---

## 10. Editorial file contract (target)

### 10.1 `review.csv` (one row per `youtube_id`)

Stable keys (human-edited): `youtube_id`, title, `tournament_guess_label`, `player_*_guess`, `score`, `stage`, `leg`, `kind`, relations, notes, `verified`, **`game_link_mode`**.

Caches (sync-written only): `guessed_tournament_id`, `player_*_id_guess`, `game_id_guess` (comma-separated for multi; **also written from sidecar** on sync).

### 10.2 `video_game_links.csv` (one row per linked game — shipped)

```text
youtube_id,link_ordinal,tournament_label,player_a,player_b,score,stage,leg,start_sec,verified
```

Example — WC 2025 final dual-leg in one upload:

```text
-OD-f0t92VQ,1,World Cup XXIII (Milan),Dagh N,Gianni T,,final,1,,Y
-OD-f0t92VQ,2,World Cup XXIII (Milan),Gianni T,Dagh N,,final,2,,Y
```

### 10.3 Manifest (derived)

`game_ids[]`, optional parallel **`game_start_sec[]`** (from sidecar), optional **`game_link_mode`** — built by `build_manifest`, validated by verify, read by PHP.

---

## 11. Commands (after GL-3+)

Unchanged entry points; behaviour changes per §8:

```powershell
python -m scripts.amiga.tournament_videos.apply_review
python -m scripts.amiga.tournament_videos.sync_db_ids
python -m scripts.amiga.verify_tournament_videos
python -m scripts.amiga prove
```

**GL-1 audit:**

```powershell
python -m scripts.amiga.tournament_videos.audit_game_links
```

**Stream sidecar curation (GL-5):** edit `data/amiga/tournament_videos/video_game_links.csv`, set `game_link_mode=stream_map` on the stream row in `review.csv`, then sync + verify (same commands as above).

---

## 12. Non-goals (this track)

- Freezing `amiga_games.id` across reimports
- Automatic stream splitting / computer vision
- Online realm video linking
- Replacing koatd with video catalog as L3 witness

---

## 13. References

| Item | Location |
|------|----------|
| Catalog product policy | [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md) |
| Sync / verify code | `scripts/amiga/tournament_videos/manifest_db.py`, `scripts/amiga/verify_tournament_videos.py` |
| Overrides | `scripts/amiga/tournament_videos/apply_review.py` → `ROW_PATCHES` |
| Resolver (batch only) | `scripts/amiga/tournament_videos/resolve_games.py` |
| DB ground key | `amiga_games.source_scores_id` — [`scripts/amiga/sql/ground/001_core.sql`](../scripts/amiga/sql/ground/001_core.sql) |
| PHP index fan-out | `site/public_html/includes/amiga_tournament_videos_lib.php` → `amiga_tournament_videos_wc_game_index()` |