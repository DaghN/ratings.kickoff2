# Handoff 2026-07-04-006 — Track H tournament tail + entities TT perf

**Status:** Done · **Fixtures:** tournament **589**, game **27418**, player **382** · **Method:** [`docs/amiga-tt-query-optimization-playbook.md`](../../amiga-tt-query-optimization-playbook.md)

---

## Shipped

| Change | File(s) | Pattern |
|--------|---------|---------|
| Videos view defers standings + participation full loads; EXISTS probes for nav | `amiga_tournament_page.php`, `amiga_tournament_lib.php` | E |
| Shared video wing resolver + wc_game_index request cache (redirect + body) | `amiga_tournament_videos_lib.php`, `amiga_tournament_lib.php` | D |
| Tournament/game inner scan on rated-games subquery | `amiga_db.php`, `amiga_tournament_videos_lib.php` | narrow read |
| Player tournaments: catalog-stats knockout_ties join (not correlated subquery) + participation cache | `amiga_player_tournament_lib.php` | D + narrow read |

## Parity

- `scripts/oneoff/amiga_track_h_parity_probe.php` — PASS game 27418 · videos games tid=9 · player 382 ×3 cutoffs

## Curl before / after (worst per URL)

| URL | Before (worst) | After (worst) |
|-----|----------------|---------------|
| `/amiga/tournament/videos/games.php?id=589` | ~0.85-1.14 s | **0.43 s** |
| `/amiga/tournament/videos/atmosphere.php?id=589` | ~0.68-1.53 s | **0.42 s** |
| `/amiga/tournament/stages.php?id=589` | ~0.57-0.91 s | **0.46 s** |
| `/amiga/game.php?id=27418` | ~0.19-0.55 s | **0.26 s** |
| `/amiga/player/tournaments.php?id=382` | ~0.66-0.98 s | **0.39 s** |

All ≤0.8 s; no PHP errors in body. Local manifest has no videos for tid 589 (empty video pages still fast).