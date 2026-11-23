# Amiga data — from the Access database to today

How the Amiga results came from one Access file to the live site, and where the evidence for every step is kept. For how things work now, read [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §0.

---

## The steps

| When | What happened | Kept in |
|------|---------------|---------|
| Until 2025 | The scene kept its results in a Microsoft Access file: 27,408 games, 477 player names, 604 tournaments (2001-11-03 to 2025-11-01). Players were identified by name, and games had no time of day. | `data/amiga/source/koatd.mdb` (the original, 5.6 MB) · its contents: [`amiga-schema-discovery.md`](amiga-schema-discovery.md) |
| Jun 2026 | **Import.** A chain of steps, L0 to L5, turned the Access file into MySQL. It mirrored every table, kept the games, catalogue and players, applied the corrections, laid out the tournament structure and calculated everything else. | Tool: `python -m scripts.amiga prove` with `scripts/amiga/import_access.py` (kept as a repair tool) · how it works: [`amiga-ground-stack.md`](amiga-ground-stack.md), [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md), [`amiga-import-layer.md`](amiga-import-layer.md) |
| Jun–Jul 2026 | **Corrections.** Where the forum or other sources showed the Access file was wrong, the import changes it: merged player names, corrected dates, events split in two, finish places. Each correction names its source. | `scripts/amiga/import_corrections.py` · `data/amiga/day0/manifests/` · [`amiga-l3-legacy-fixes-inventory.md`](amiga-l3-legacy-fixes-inventory.md) |
| 2026-07-08 | **Day 0.** The corrected results were sealed and committed to git: 605 tournaments, 469 players, 27,418 games. From then on, this is the starting point, not the Access file. | `data/amiga/day0/` (manifest + SQL) |
| Jul 2026 | **Living ground.** The working database `ko2amiga_work` started from day 0. A full rebuild (`simul`) recalculates everything from the stored results. The old import database `ko2amiga_db` on this PC was frozen as a reference. | [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) |
| Jul 2026 | **Live site and organizers.** The live database on the server became the authority: pull from live, repair here, push back. Organizers run new tournaments on the website and Finish them there. | [`amiga-staging-authority-policy.md`](amiga-staging-authority-policy.md) · [`amiga-staging-handoff.md`](amiga-staging-handoff.md) · [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) |
| Nov 2026 | **New results and later corrections.** World Cup XXIV (Oslo) was added, World Cup groups and knockouts were stored from a register, and extra-time results were reviewed against the evidence. | [`amiga-extra-time-review-2026-11.md`](amiga-extra-time-review-2026-11.md) · `scripts/amiga/tournament_structure/world_cup_register.json` |
| Nov 2026 | **Checks.** The website's Finish is compared with the Python rebuild, and every stored number is counted again from the results. | [`amiga-php-finalize-parity-protocol.md`](amiga-php-finalize-parity-protocol.md) §0 · [`amiga-recount-policy.md`](amiga-recount-policy.md) |

## Old plans, handoffs and logs

The step-by-step plans, agent handoffs, starter prompts, attempt logs and session logs behind this story were removed from the working folder on 2026-11-23. Nothing is lost; git keeps them. To read them, check out the tag that marks the state just before the cleanup:

```powershell
git checkout archive/before-cleanup-2026-11-23
```

Go back with `git checkout main`. To look at a single file without switching: `git show archive/before-cleanup-2026-11-23:docs/<path>`.
