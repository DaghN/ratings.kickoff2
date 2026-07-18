# Kick Off 2 ratings — project brief

**Audience:** Dagh and anyone (including Cursor agents) working on this site. Technical stack details stay light here; this is **orientation and intent**. Current logistics live in `PROJECT_MEMORY.md`; layout in `docs/PROJECT_MAP.md`; visuals in `docs/design-direction.md`.

---

## Context

- **Kick Off 2 retro community.** This repository builds and maintains **Kick Off 2 ratings** — a community website (and the data/ops backbone behind it) for people who play and follow KO2. The site covers two related worlds under one shell: the live **online** (KOOL) ecosystem, and the **Amiga 500** offline tournament tradition.

- **Online (KOOL).** Steve’s online Kick Off 2 world — the game app, hosting, and rated ladder — is the living pulse of competitive play on PC. This site shows that ladder and what derives from it: results, Elo and career truth, Status, Activity, leaderboards, milestones, the Games vault, Hall of Fame, and Play & Setup. Steve writes each rated game as **ground truth**; derived ratings and website stats are maintained in this repo.

- **Amiga 500.** The offline tournament universe — decades of World Cups, kitchen leagues, and related events — carried forward from the community’s Access archive into MySQL. It is **tournament-native and historical** (including ongoing community events), not a fake live UTC server: ratings and time travel, World Cups / Countries / Tournaments as destinations, Games, Activity, HoF, News, tournament video, and Live / organizer tooling as that matures.

- **What this project is for.** Be a **major content destination for the Kick Off 2 universe**: rooted in ratings, results, and tournament history, and **increasingly** also present-layer editorial, lore, media, onboarding, and organizer tooling as those mature. Historically the spine was **stats-first**; that spine remains essential wherever the page is a ladder, table, or record — but it is no longer the whole product identity.

- **What this is not.** Not an **umbrella for all things KO2**. Forums, Discord, kickoff2.com, play clients, and other scene sites stay **siblings**. This site should **credibly fit** next to them — approachable in tone, rich in content, honest about data — without trying to own the whole community.

- **What lives in the repo.** Website code, schema/contracts, derived writers and proof tooling (online PHP ops; Amiga modern ground / simul), durable docs, and **intentional database continuity backups** when sealed into git (Amiga already: day0 + work checkpoints; online the same habit is wanted). **Secrets** (credentials, local ini / `*.local.php`) stay out of git. Routine working exports may stay local or gitignored until a milestone is sealed — that is not a ban on database archives in the repo.

---

## North star

Build a **rich, trustworthy Kick Off 2 content platform** that regulars want to live in and strangers can enter without drowning — **fast and data-honest** where stats are the job, **warmly intentional** where editorial, media, and hang-out surfaces are the job — without becoming a generic consumer app or rewriting everything for its own sake.

---

## Vision — what we want (broad terms)

- **Honor the spine.** Dense, cross-linked ladder surfaces (hubs → leaderboards → profiles → games → records), sortable tables, charts fed by **stored truth**, and navigation that shows *where you are*. When the page is about comparison or career truth, keep it **fast, readable, and dense**.

- **Treat content beyond stats as first-class growth.** The site has already grown a **present layer** and culture surfaces — Amiga **News** (roll + pulse), evergreen lore (e.g. box-art story), **tournament video**, jukebox hang-out mood, About / footer chrome, Play & Setup onboarding, and **Amiga Live / organizer** workflows under development. Enrichment such as photos and deeper tournament storytelling belongs in that same arc when data and upkeep allow — not as accidental garnish.

- **Make it nicer to *be* here.** Feel and clarity: coherent chrome, calm typography and spacing, neon-noir stats atmosphere (data leads; accents spare), realm switcher Online · Amiga 500, and a sense that the scene is **alive** (online Status) or **worth lingering** (Amiga News, media, time travel). Prefer framing that answers an obvious human question over “another sortable column.”

- **Open the door for newcomers.** Enough welcoming layer that a passerby understands *what this is*, *that it’s current*, and *where to explore or join* — Status / Play & Setup online; News pulse + sidebar CTAs on Amiga — without consumer-app fluff or preachy meta copy.

- **Respect realm asymmetry.** Online and Amiga share design language and many patterns, but **not** a mirrored feature checklist. Skip surfaces that don’t fit (e.g. Amiga has no live Status pulse or Play & Setup hub tab); lean into Amiga-native strengths (tournaments, World Cups, Countries, time travel, video, organizer ops).

- **Add capability in small phases.** Charts, opponent breakdowns, time travel, media catalogs, editorial shelves, organizer confirm flows — ship as **short vertical slices**, gated on clarity and maintainability. Creative expansion often follows a known recipe: obvious human question → existing infrastructure → one framing device → mood that invites staying awhile.

- **Stay truthful.** Polish must never disguise or toy with metrics. Broken or ambiguous data outranks decoration. Editorial and media are real product — they are not a license to invent ladder numbers.

---

## What we’re *not* optimising as a primary goal

- Becoming the **single homepage / CMS / forum** for all Kick Off 2 community life.

- Full accessibility certification agendas (many players are mouse/visual PC-first; **basic readability** still matters).

- Mandatory **mobile parity** with desktop. Phone should be **usable when convenient**; dense tables stay tables — deliberate **read-first, pinch-second** (not card reflow). See [`docs/k2-mobile-smartphone-policy.md`](docs/k2-mobile-smartphone-policy.md).

- Re-platforming **for aesthetics alone**, or rewriting everything “because we can.” Default path: **incremental improvement** on what exists.

- Forcing **online ↔ Amiga feature parity** or inventing integrations that aren’t real yet. Parity of **spirit** (density, honesty, cross-links) — not copy-paste of every wing.

---

## How Dagh wants to work (agents + Cursor)

- **Primary workspace:** Local development in **Cursor** (Laragon hosts; see `docs/LOCAL_DEV.md`).

- **Collaboration:** Dagh steers **goals and taste in plain language**. Agents handle implementation detail unless asked otherwise — propose **small, reversible steps** aligned with this brief.

- **Rhythm:** Short vertical slices (one nav pass, one table/chrome fix, one chart endpoint, one organizer confirm step) rather than sprawling refactors justified by vibes alone. Multi-session features use the agent-track habit (policy → plan → slices) when the work needs locked rules.

- **Version control:** **Git** as durable history — commits/branches, not zip files.

- **Ship path:** Develop locally first; sync website PHP to staging (typically **WinSCP**). Steve runs live hosting and online ground insert + dispatch invoke. Amiga staging follows the pull → repair/simul → push loop documented under `docs/` — this brief does not own those runbooks.

- **Doc habit:** Substantial slices update living docs the same turn they ship (`docs/UPDATE_DOCS.md`). This brief stays about **purpose and taste**; feature truth lives in specs.

---

## Logistics context (minimal)

- **Code vs data:** Repo holds website code, agreed schema/contracts, scripts, docs, and **sealed DB continuity backups** (see Continuity in `README.md`). **Secrets** stay out of git. Do not treat “database dumps must never be committed” as policy — that was an agent misunderstanding.

- **Stack pointer (not a runbook):** PHP + MariaDB/MySQL via `mysqli`; theme and chrome in `site/public_html/`; online derived ops under `site/public_html/ops/`; Amiga pages under `site/public_html/amiga/`. Details: `README.md`, `docs/PROJECT_MAP.md`, `PROJECT_MEMORY.md`.

---

## If this conflicts with Dagh

**Dagh’s stated preference wins.** Agents should reconcile this doc with Dagh’s latest message if they diverge.

---

*Redrafted Jul 2026 (dual-realm content platform). Supersedes the May 2026 “legacy stats site” framing.*