# Amiga live operations platform — design (Jul 2026)

**Status:** **Policy locked** — architecture and operational boundaries agreed Jul 2026. **Implementation:** **practice-first** (see §12) — each infra slice ships only when a reference-tournament drill exposes real friction; most Lane B/C verbs not shipped yet. **Active track:** [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md).

**Audience:** Dagh, Cursor agents, future community organisers / secretaries (via ops UI).

**Online analogue:** [`ladder-ops-platform.md`](ladder-ops-platform.md) — online prod/staging = `site/public_html/ops/` + `kooldb*`. Amiga live realm = `site/public_html/amiga/ops/` + `ko2amiga_db` + server filesystem for uploads.

**Related:** [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) (**locked Jul 2026** — living ground, day 0 bootstrap, simul; Lane A local authority defers here) · [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md) (reference formats, drill loop, pain-point log — **start here for implementation**) · [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) (**locked Jul 2026** — running vs official universe; Make official = only boundary) · [`amiga-running-tournament-boundary-inventory.md`](amiga-running-tournament-boundary-inventory.md) · [`amiga-player-create-policy.md`](amiga-player-create-policy.md) (organizer newcomer naming — **shipped Jul 2026** PC-1–PC-7) · [`amiga-player-create-implementation-plan.md`](amiga-player-create-implementation-plan.md) · [`amiga-data-contract.md`](amiga-data-contract.md) (layers, table register) · [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md) (L5 writers, prove-only repair today) · [`amiga-staging-handoff.md`](amiga-staging-handoff.md) (export/import loop) · [`amiga-ground-stack.md`](amiga-ground-stack.md) (L0–L5 Access era — archive) · [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) (stage/fixture/game model) · [`archive/orchestration/browser-organizer-workflow-checkpoint.md`](archive/orchestration/browser-organizer-workflow-checkpoint.md) (organizer UX gaps) · [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) · [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md) · [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md) · [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) · [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md) · [`archive/retired-amiga-refinalize-2026-06.md`](archive/retired-amiga-refinalize-2026-06.md)

---

## 1. Executive summary

The Amiga realm is **two products** that must not be conflated:

| Product | Question it answers | Natural home |
|---------|---------------------|--------------|
| **Historical canon** | “Rebuild the Access lineage and prove writers still match oracle.” | **Local** — Python `scripts/amiga/`, `koatd.mdb`, L1–L5 pipeline |
| **Live community realm** | “Run tournaments, enter results, finalize, attach media, fix mistakes.” | **Staging** (then prod) — PHP `amiga/ops/`, `ko2amiga_db`, server disk |

Today’s habit — **local `prove` → export SQL → WinSCP → browser import** — made sense when staging was a **read-only mirror of local canon**. It breaks once **community members create tournaments on staging** that never existed on the laptop.

This document locks **three operational lanes**, **where code runs**, **timeline vs present projection** (repair semantics), **bidirectional data flow**, and **editorial media** (YouTube URLs, photos). Full `python -m scripts.amiga prove` remains the **deep oracle** for canon and writer regression; it is **not** the default response to secretary mistakes or live uploads.

**Prove runtime (Jul 2026):** full holy loop is **~30 minutes** locally after DB expansion — anchored repair on staging becomes **essential**, not optional.

---

## 2. Vocabulary

| Term | Meaning |
|------|---------|
| **Lane A — Canon pipeline** | L0 `koatd.mdb` → L1 mirror → L2 prune → L3 witness → L4 structure → L5 replay/prove. Rare, heavy, local. |
| **Lane B — Live ladder ops** | Community tournament ground (L3+L4), result entry, finalize, cancel/repair, verify-lite. Daily, staged PHP + DB. |
| **Lane C — Live editorial ops** | Tournament media (YouTube URLs, photos), future News/Misc content. Staged PHP + DB + filesystem. **Not L5 derived.** |
| **Timeline (authority)** | Sparse event-indexed rows: `amiga_player_event_snapshots`, `amiga_player_matchup_at_event`, `amiga_realm_snapshots`, `amiga_community_stats_snapshots`, slice `*_at_event`, etc. |
| **Present (projection)** | Convenience rows for website present mode: `amiga_player_current`, `amiga_player_matchup_summary`, `amiga_generalstats` id=1, `amiga_community_stats` id=1, `*_totals`, `*_present`. **Recomputable from timeline.** |
| **Cutoff N** | Last good finalized tournament in catalog chrono order after a repair or delete. |
| **Anchored repair** | Truncate derived timeline after N, re-project present at N, optionally re-finalize forward — **without** full L0–L5 prove. |
| **Ground pack** | Export/import slice of L3+L4 (+ optional editorial) for one `tournament_id` — backup and pull from staging. |
| **Canon export** | Full Pack C push local → staging (`export_ko2amiga_db.ps1` + browser import). |

**Elo and ladder stats are L5 derived truth** — same class as online post-game derived columns. Editorial media is **not** L5.

---

## 3. Three lanes — responsibilities

### 3.1 Lane A — Canon pipeline (local)

**Purpose:** Rebuild historical ground from Access lineage; regression-test finalize writers; publish baseline to staging.

| In scope | Out of scope |
|----------|--------------|
| `python -m scripts.amiga prove` (L1→L5 + verify suite) | Daily secretary workflows |
| `import-pristine`, `import-prune`, `import-witness`, `apply-structure` | Community tournament creation |
| Access ODBC, `data/amiga/exports/`, disposition register | Live media uploads |
| Harvest/build historical video catalog (`scripts/amiga/tournament_videos/`) | Staged DB as write authority |
| `sync_db_ids` after full L3 reimport (video manifest FK refresh) | Anchored repair on staging |

**Frequency:** When import rules change, new historical tranches, major L5 writer work, or before promoting a large canon refresh to staging.

**Output:** Verified local `ko2amiga_db` → **canon export** to staging (existing handoff).

**Prove modes (intent — not all implemented):**

| Mode | Input | Use |
|------|-------|-----|
| **Full prove** | L0→L5 | Canon regression, schema/writer sign-off |
| **L5-only replay** | Existing L3+L4 in DB | Sign-off when ground unchanged; faster than full prove |
| **Verify-only** | Current DB | Read-only oracles after anchored repair |

### 3.2 Lane B — Live ladder ops (staging)

**Purpose:** Community tournaments, fixtures, results, finalize, cancellation, anchored repair.

| Capability | Status (Jul 2026) | Location |
|------------|-------------------|----------|
| Structure + fixtures + results | **Shipped (RTB Jul 2026)** | Running scores on `tournament_fixtures` until **Make official** (`promote_running_tournament` → `finalize_tournament`); broadcast table on organizer + Live hub — [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) |
| Finalize one tournament | **Shipped** | Browser Table **Make official** or `finalize-tournament` (promote prefix when zero `amiga_games`) |
| Zero derived | **Shipped** (PHP) | Same runner |
| Delete/cancel tournament (guarded) | **Not shipped** | Planned ops verbs |
| Truncate derived after cutoff N | **Not shipped** | Planned |
| Project present at N | **Not shipped** | Planned (SQL + PHP helpers) |
| Re-finalize forward from N+1 | **Not shipped** | Loop live finalize path |
| Verify-lite on staging | **Partial** | Python verify local only |
| Pull ground from staging | **Not shipped** | Ground pack export |

**Runtime rule:** Lane B = **`public_html/amiga/ops/` + `ko2amiga_db`**. Same WinSCP sync habit as online `ops/`. **No** requirement for Python on the server for daily ops (optional later for verify).

**Bootstrap rule (S4):** Finalize and replay **must not** read present projections (`amiga_player_current`, `amiga_generalstats` id=1) to seed **next event cumulative state**. Prior **snapshots** only. See [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) S4. Refinalize was retired because this rule was violated in practice — [`archive/retired-amiga-refinalize-2026-06.md`](archive/retired-amiga-refinalize-2026-06.md).

### 3.3 Lane C — Live editorial ops (staging)

**Purpose:** Community-attached **non-ladder** content: YouTube URLs, tournament photos, later News/Misc posts.

| Property | Rule |
|----------|------|
| **Layer** | Editorial overlay on tournaments — **not L5**; no Elo/post-game writers |
| **Write authority** | **Staged DB** (+ filesystem for photo bytes) — not git JSON as live store |
| **Read path** | Website PHP (present + time travel where product allows — TV14) |
| **Moderation** | `pending` → `approved` before public tabs (intent) |
| **Auth** | Reuse tournament organiser trust model (`fixtures.php` password / future roles) |

**Historical video catalog v1** (`tournament_videos.json` + `review.csv` in repo) remains **canon editorial** curated locally. Lane C **supersedes the live write path** for new community clips; git/CSV remains merge/archive target, not staging runtime. See §8.

---

## 4. Where things run

```text
┌─────────────────────────────────────────────────────────────────┐
│ LOCAL (Dagh dev machine + git)                                  │
│  L0 koatd.mdb · scripts/amiga/ · prove · harvest · export pack  │
│  Lane A only · deep verify oracles                              │
└────────────────────────────┬────────────────────────────────────┘
                             │ canon export (push) ───────────────►
                             │ ground/media pack (pull) ◄──────────
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│ STAGING ratings.kickoff2.com (then prod Amiga)                  │
│  public_html/amiga/ · public_html/amiga/ops/ · ko2amiga_db      │
│  Lane B + Lane C · community ground authority                   │
│  Server disk: uploads (photos) — not in git                     │
└─────────────────────────────────────────────────────────────────┘
```

| Asset | Local | Staging | Git |
|-------|-------|---------|-----|
| PHP website + `amiga/ops/` | dev copy | **runtime** | yes (WinSCP) |
| `ko2amiga_db` community ground | clone/pull | **authority** | no (SQL export only) |
| `scripts/amiga/` Python | **runtime** | not required | yes |
| `koatd.mdb` | **runtime** | no | no (L0 artefact) |
| Historical video JSON manifest | build | read (synced) | yes → until DB migration |
| Live media rows + photo files | pull/merge | **write** | no |

**Agent trap:** Do not assign “run prove on staging” for community mistakes. Do not treat “scripts on staging” as “install full Python prove stack on server” — Lane B/C need **PHP write paths**, not L0 Access.

---

## 5. Data model — timeline, present, and time travel

L5 derived truth uses **sparse timelines** + **present projections** (locked in event-snapshot, matchup-at-event, realm-snapshot, community-stats policies).

| Domain | Timeline (authority at cutoff N) | Present (website default) |
|--------|----------------------------------|---------------------------|
| Player career | `amiga_player_event_snapshots` | `amiga_player_current` |
| H2H pairs | `amiga_player_matchup_at_event` | `amiga_player_matchup_summary` |
| Realm / HoF record book | `amiga_realm_snapshots` | `amiga_generalstats` id=1 |
| Community headline stats | `amiga_community_stats_snapshots` + `amiga_community_stat_facts` | `amiga_community_stats` id=1 |
| WC player slice | `amiga_player_slice_at_event` | `amiga_player_slice_totals` |
| WC country slice | `amiga_country_slice_at_event` | `amiga_country_slice_totals` |
| WC HoF | `amiga_wc_hof_snapshots` | `amiga_wc_hof_present` |

**Sparse, not dense:** rows exist at **participated events** (or pair×event for matchups), not for every player at every tournament. **Time travel at N** = latest timeline row ≤ N per entity — same query habit as **re-project present at N**.

**Finalize write order (conceptual):**

1. Load prior cumulative state from **timelines** (not present).
2. Process games → `amiga_game_ratings`.
3. Write participant **snapshot + current** paired per player.
4. Write matchup at-event + summary upsert, community snapshot + present, realm snapshot + generalstats, slices, etc.
5. Verify tournament finalize checks.

Present rows are **output** of finalize, not input for the next event’s career bootstrap.

**Verify oracles (local today):** `verify-event-snapshots`, `verify-realm-snapshots`, `verify-player-matchups` assert present = latest timeline projection. These define **project-present-at-N** semantics.

---

## 6. Bidirectional flow

### 6.1 Today (canon push — keep for historical refresh)

```text
local prove → export_ko2amiga_db.ps1 → WinSCP → run_import_ko2amiga.php
```

See [`amiga-staging-handoff.md`](amiga-staging-handoff.md). Staging does **not** run Python replay on import.

### 6.2 Required (community pull — not shipped)

Once staging owns community tournaments:

```text
staging ko2amiga_db → ground pack / delta export → local clone for dev
staging media + metadata → pull for backup / optional canon merge
```

**Rule:** Community ground created on staging may **never** exist locally until pulled. Local full prove must not be the only recovery path for staged mistakes.

### 6.3 Promotion (optional)

Community ground or media may be **promoted to canon** (git manifest, disposition register) on a human schedule — not automatic on every upload.

---

## 7. Anchored repair (Lane B)

Full `prove` (~30 min) is **canon regression**, not the default fix for:

- Delete secretary training tournament (latest event).
- Remove a finalized mistake at the catalog tail.
- Truncate forward derived after a bad finalize.

### 7.1 Case A — Unfinalized tournament

Delete L3+L4 ground (tournament, games, entrants, fixtures, standings). **No L5 timeline rows exist.** No present reprojection needed.

### 7.2 Case B — Finalized, **latest** event (common WC training case)

1. Delete tournament **ground** (if removing entirely) or fix ground in place.
2. Delete all **derived rows keyed to that `tournament_id`** (snapshots, at-event, realm snapshot, community facts, game_ratings, etc.).
3. **Re-project all present tables** from max timeline at previous cutoff N (same queries as verify oracles).
4. Fix **cumulative stores** that are not simple projections if the event had games (e.g. reproject `matchup_summary` from `matchup_at_event` at N; delete orphan summary pairs only created in removed event).
5. Run **verify-lite** (staging PHP or pulled DB + Python verify subset).

**No full prove required** if verify passes.

### 7.3 Case C — Finalized with **later** events still in catalog

Deleting event N+1 **poisons** all forward timeline rows computed including its effects.

1. Delete N+1 ground + derived (as Case B).
2. **Truncate all derived timeline rows** for tournaments with chrono **> N** (not only N+1).
3. Re-project present at N.
4. Reset `rating_finalized=0` and clear per-game ratings for each tournament T > N to re-finalize.
5. **Re-finalize forward** in chrono order using **live finalize path** (each step loads prior snapshots from DB — no day-zero in-memory replay required).
6. Verify-lite, then optional local full prove as oracle.

**Retired:** single-tournament refinalize without forward truncate — [`archive/retired-amiga-refinalize-2026-06.md`](archive/retired-amiga-refinalize-2026-06.md).

### 7.4 Planned ops verbs (Lane B)

| Verb | Guard | Effect |
|------|-------|--------|
| `delete-unfinalized-tournament` | No `rating_finalized` | L3+L4 delete |
| `delete-last-finalized-tournament` | Chrono-last finalized | Case B pipeline |
| `truncate-derived-after` | Cutoff tournament id | Case C step 2–3 |
| `project-present-at` | Cutoff tournament id | Rebuild all present projections |
| `refinalize-forward-from` | After truncate + project | Case C step 5 loop |
| `verify-derived` | — | Read-only checks (subset of prove verify) |

Implement under `site/public_html/amiga/ops/` with same CLI/bootstrap habits as `run_process_game.php`.

---

## 8. Editorial media — Lane C (videos, photos)

### 8.1 Problem with v1 video model for community

Shipped v1 ([`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md)):

- **Read:** `site/public_html/data/amiga/tournament_videos.json`
- **Write (canon):** local `review.csv` + Python harvest/build + `sync_db_ids` after full L3 reimport
- **Stable keys:** `youtube_id`; DB ids are caches

Community uploads on staging **cannot** use git JSON as the live write path.

### 8.2 Target model (locked intent)

| Layer | Historical canon | Live community |
|-------|------------------|----------------|
| **Authority** | CSV/JSON in git (until migrated) | **`amiga_tournament_media` table (DDL TBD)** on staging |
| **YouTube** | Harvest + human review | Web form → validate ID → DB row → embed via `k2_youtube_embed_url()` |
| **Photos** | Rare / manual | Upload → server filesystem → DB row (path, checksum, moderation) |
| **Read lib** | JSON manifest (+ DB after migration) | **DB first**, JSON fallback for legacy bulk |
| **Prove** | `sync_db_ids` after L3 reimport | **Not per upload**; remap game/player FKs when ids shift |

**Moderation:** rows start `pending`; public Videos tab shows `approved` only (aligns with CSV `verified` culture).

**Filesystem (intent):** photo bytes under server path **outside** `public_html` or via guarded PHP serve — e.g. `data/amiga/uploads/tournament/{id}/` on host (not committed). See [`self-hosted-assets.md`](self-hosted-assets.md) — photos are self-hosted unlike YouTube embeds.

**TV1 preserved:** separate **Videos** and future **Photos** tabs — not one “Media” blob.

### 8.3 Lane C ops surface (staging)

| Capability | Runtime |
|------------|---------|
| Add/edit YouTube URL | PHP POST on staging |
| Upload photo | PHP multipart → disk + DB |
| Approve/reject | PHP ops (organiser/admin) |
| Delete media for tournament | Cascade DB + unlink files |
| Export media slice in ground pack | PHP or CLI export |

**Not required on staging:** Python harvest crawlers, `prove`, Access.

### 8.4 Canon merge loop (optional)

```text
staging DB media rows → export slice → local merge into review.csv / JSON
OR promote to git on release schedule
```

`youtube_id` remains stable across L3 reimports; `game_id` / player id caches still need `sync_db_ids` on full canon prove — §12 of video policy unchanged for **canon** path.

---

## 9. Ground packs and backup

Tournament **ground** is already in MySQL (L3 games + L4 structure). There is no separate “tournament file format” — only **export granularity**.

### 9.1 Tournament ground pack (L3+L4)

Per `tournament_id`, export:

- `tournaments`, `amiga_games`, `tournament_entrants`, stages, fixtures, stage players
- Optional: `amiga_tournament_finish_override`, lifecycle fields
- **Exclude L5** (recomputable via finalize)

**Uses:** backup before finalize; pull staging community event to local; restore after mistaken delete of **ground only**; handoff between organisers.

### 9.2 Cutoff checkpoint

Snapshot manifest of **entire DB derived state through cutoff N** OR host mysqldump — for disaster recovery faster than 30-minute prove. Heavier than per-tournament pack; optional ops habit before WC.

### 9.3 Ground pack + media

Include Lane C media metadata + file references in the same pack so cancel/repair does not orphan photos on disk.

---

## 10. Relationship to online ladder ops

| Aspect | Online [`ladder-ops-platform.md`](ladder-ops-platform.md) | Amiga live ops (this doc) |
|--------|-----------------------------------------------------------|---------------------------|
| Ops tree | `public_html/ops/` | `public_html/amiga/ops/` |
| DB | `kooldb*` / work DBs | `ko2amiga_db` |
| Ground insert | Steve per game | Community fixtures + games on staging |
| Derived | Per-game PHP + periodic | **Tournament finalize** only (L5) |
| Simul | `run_ops_sim.php` | `python -m scripts.amiga replay` / live finalize loop |
| Canon rebuild | zero-derived + simul on work | full prove local; L5-only replay intent |
| Present projection | `playertable`, `generalstatstable` | snapshots → `*_current` / id=1 rows |
| Deep verify | `run_verify_ops_sim.php` | Python verify suite; staging verify-lite TBD |

Same **philosophy:** stored timeline truth where needed; present rows for hot reads; simul/replay proves writers — but Amiga **commit boundary is finalize**, not per-game.

---

## 11. Locked decisions

| # | Decision | Rule |
|---|----------|------|
| **ALO1** | **Staging is live ground authority** | Community tournaments and Lane C writes authoritative on staged `ko2amiga_db`; local is canon lab + merge target |
| **ALO2** | **Three lanes** | A = canon pipeline local; B = ladder ops staged; C = editorial ops staged — do not merge responsibilities |
| **ALO3** | **Prove is not daily ops** | Full prove = canon/writer regression (~30 min); anchored repair + verify-lite = live mistakes |
| **ALO4** | **Present is disposable** | Repair may truncate timeline and re-project present; snapshots/at-event/realm timelines are authority through N |
| **ALO5** | **S4 bootstrap** | Finalize/replay forward loads prior **snapshots**, never present rows for cumulative seed |
| **ALO6** | **No refinalize resurrection** | No single-tournament reopen/refinalize CLI; use anchored repair pipeline or full prove — [`archive/retired-amiga-refinalize-2026-06.md`](archive/retired-amiga-refinalize-2026-06.md) |
| **ALO7** | **Live media write = DB** | Community YouTube/photo adds write staged DB (+ disk for photos); git JSON is canon archive, not live store |
| **ALO8** | **Bidirectional flow** | Canon push remains; **pull** ground/media packs from staging is required for community era |
| **ALO9** | **Lane B/C code in `amiga/ops/`** | Same deploy habit as online ops — WinSCP `public_html/`; not `scripts/` on server for daily paths |
| **ALO10** | **Editorial ≠ L5** | Media uploads do not invoke finalize writers; optional link to `game_id` is editorial FK only |
| **ALO11** | **Start = public live** | Generated fixture-backed tournaments with `lifecycle_status = running` appear on `/amiga/live-tournaments.php` automatically — **no config allowlist**. Setup stays private; **Make official** = finalize (N→N+1). No hide toggle in v1. |

---

## 12. Implementation — practice-first sequencing

**Policy defines boundaries (ALO1–ALO10). Practice defines priority.**

Do **not** implement §12.2 infra phases in numeric order. Each slice ships when a **reference-tournament drill** (§12.1) hits a concrete pain point, then **re-runs the same drill** as the smoke test before the next slice.

**Living log:** [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md) — pain points, slice queue, drill checklist.

### 12.1 Reference formats and drill loop (Track L — live maturity)

**v1 live product = two shapes only.** Everything else (Swiss, WC-class, promotion graph, bulk historical materialize) is **Track C — canon** (§12.3), not live v1.

| ID | Shape | Players | Create today | Drill exit |
|----|-------|---------|--------------|------------|
| **Ref-League-A** | Kitchen marathon — one `round_robin` stage | 4–6 | Browser [`amiga/ops/fixtures.php`](../../site/public_html/amiga/ops/fixtures.php) | All fixtures played → **finalize** → tournament page + rating movement sane |
| **Ref-Cup-A** | Single elimination (`knockout` ties) | 4 or 8 | CLI initially (`build-tournament create-group-knockout` / smallest KO); browser play + finalize | Winner + honours visible on site |

**Drill loop** (repeat between every implementation slice):

```text
1. Create   → name, date, players (by name search, not raw ids)
2. Start    → enter all fixture results
3. Finalize → `run_process_game.php finalize-tournament`
4. Website  → tournament page, standings, profile/LB spot-check
5. (Later)  → delete/cancel training event once repair verbs exist
```

**Track L order** (gates — do not skip ahead without a drill reason):

| Step | Work | Infra from §12.2 | Exit |
|------|------|------------------|------|
| **L0** | Run **Ref-League-A** on staging **as-is**; log pain in practice track | — | Pain-point log started |
| **L1** | Organizer UX only where L0 blocked repeat ([`browser-organizer-workflow-checkpoint.md`](archive/orchestration/browser-organizer-workflow-checkpoint.md)) | — | League drill repeatable same evening |
| **L2** | **Ref-League-A ×3** until boring | — | You can narrate lifecycle without opening PHP |
| **L3** | Minimal **Ref-Cup-A** create path (CLI OK) | — | One cup finalized on staging |
| **L4** | **Ref-Cup-A ×2** | — | League + cup feel like one product |
| **L5** | **Mistake-driven repair** — delete training events, fix present projection | Phases **1–3**, **5** (Case B first) | Training tournament removed without full `prove` |
| **L6** | Pull staging ground you created | Phases **4**, **8** | Ground pack on laptop |
| **L7** | Media on a tournament you ran | Phases **6–7**, **10** | YouTube URL on staging event |

**Slice sizing:** one agent chat = one pain point → one fix → **same drill re-run**. Not “Phases 1–3 in one go.”

**Agent gate:** No new Lane B/C verb without naming **which reference format**, **which drill step**, and **which logged pain point** it resolves.

### 12.2 Infra capability menu (not default sequence)

Ship when §12.1 unlocks it. Technical exit criteria unchanged; **behavioural** exit = drill re-run green.

| Phase | Deliverable | Lane | Unlocks at | Technical exit |
|-------|-------------|------|------------|----------------|
| **0 — Doc** | This policy + practice track | — | Done | Agents cite ALO1–ALO11 + practice-first |
| **1 — Verify-lite PHP** | `verify-derived` subset on staging | B | **L5+** (after first repair) | Present=timeline checks pass on staging |
| **2 — Project present** | `project-present-at` + SQL helpers | B | **L5** (paired with delete) | Matches Python oracle on sample DB |
| **3 — Delete last finalized** | Guarded delete + Case B pipeline | B | **L5** (first motivated infra) | Training tournament removed without `prove` |
| **4 — Ground pack v0** | Export/import one tournament L3+L4 | B | **L6** | Pull staging event to local |
| **5 — Truncate + refinalize forward** | Case C pipeline | B | After **L5** Case B trusted | Mid-history delete without full `prove` |
| **6 — Media DDL + upload API** | `amiga_tournament_media` + YouTube form | C | **L7** | Secretary adds URL; tab after approve |
| **7 — Photo upload** | Filesystem + thumbnails + Photos tab | C | **L7** | Self-hosted serve; pack includes files |
| **8 — Pull export** | Staging → download ground/media pack | B/C | **L6** | Local dev without manual mysqldump |
| **9 — L5-only prove flag** | `prove --l5-only` or `replay` without L1–L4 | A | **Track C** | Faster writer sign-off when ground unchanged |
| **10 — Media read migration** | PHP lib reads DB first, JSON fallback | C | **L7** | Historical JSON fallback; live rows on staging |

**Do not block** Lane B repair on Lane C media DDL — independent once their L-step arrives.

### 12.3 Track C — canon / history (parallel, do not mix with drill sessions)

Separate agent track. Does **not** gate Ref-League-A / Ref-Cup-A practice.

| Work | Examples |
|------|----------|
| Disposition review | 44 `pending_review` in disposition register |
| Bulk materialize | Structure plan slices 6–6wc (tier B/C/WC) |
| Prove speed / modes | Full prove ~30 min; Phase 9 L5-only |
| Format backbone expansion | Swiss product surface, promotion graph, WC generator |

**Rule:** Do not assign “implement Phase 2 project-present” when Dagh says “I’m running my first league today” unless a drill just failed on present projection.

---

## 13. Agent policy

- **Live ops implementation** → read [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md) first (reference formats, pain log, slice queue); then **this doc** for lanes/repair boundaries.
- **Community tournament / staging mistake / cancel / media upload** → this doc + [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md) for what may write L5 today.
- **Practice-first:** every Lane B/C slice must cite a **logged pain point** + **drill re-run** as smoke test (§12.1). Do not burn through §12.2 phases infra-blind.
- **Live visibility (ALO11):** no config allowlist — `running` generated events are public on Live hub; finalize = **Make official** in organizer UX.
- **Do not** instruct full `prove` as the first fix for staging-only ground errors.
- **Do not** resurrect refinalize / batch `*-rebuild` CLIs — [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md).
- **Do not** add live community writes to git-tracked `tournament_videos.json` without an explicit migration slice.
- **New Lane B/C ops verbs** → under `site/public_html/amiga/ops/`, mirror `run_process_game.php` bootstrap; register in practice track + this doc §7.4 / §12.2 when shipped.
- **Part B migration registers** apply when media DDL or repair verbs change stored schema — [`UPDATE_DOCS.md`](UPDATE_DOCS.md).

---

## 14. Rejected alternatives

| Alternative | Why not |
|-------------|---------|
| Run full prove on staging for every repair | No Access/L0; ~30 min; wrong tool for community ground |
| Keep JSON manifest as live write path for uploads | Staging cannot write git; breaks community workflow |
| Single refinalize tournament without forward truncate | Retired — cumulative corruption — T24 class bugs |
| Store community tournaments only in local prove loop | Staging community data never reaches local |
| Dense snapshot table (every player every event) | Unnecessary — sparse timeline + TT queries already shipped |
| Merge Lane C into L5 finalize writers | Media is editorial; would coupling repair to Elo |

---

## 15. Open questions (not locked)

- Exact `amiga_tournament_media` DDL and moderation roles.
- Whether staging runs optional Python verify in CI/cron vs PHP-only verify-lite.
- Prod Amiga host: same as staging pattern or separate DB.
- News/Misc present-layer posts — DB vs static includes ([`present-layer-ia.md`](present-layer-ia.md)) — separate from tournament media but same Lane C habit.
- Automated pull from staging on schedule vs manual export only.

---

## 16. Changelog

| Date | Change |
|------|--------|
| 2026-07-07 | **RTB shipped** — Lane B running scores on fixtures; Make official = promote + finalize; `verify-running-tournament-boundary` in prove. |
| 2026-07-07 | **Start=public live + Make official** — removed config allowlist; running generated events auto on Live hub; organizer **Make official** = finalize (ALO11). |
| 2026-07-07 | **Practice-first sequencing** — §12 rewritten: Ref-League-A / Ref-Cup-A drill loop gates §12.2 infra; Track L vs Track C; [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md) living log. |
| 2026-07-05 | Initial policy — three lanes, staging authority, timeline/present repair, ground packs, Lane C media, bidirectional flow, phased roadmap (ALO1–ALO10). |