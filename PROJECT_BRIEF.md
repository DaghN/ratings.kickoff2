# KOOL Kick Off 2 ratings site — project brief

**Audience for this doc:** Dagh and anyone (including Cursor agents) helping improve the legacy **KOOL Rating** statistics website. Technical stack details are intentionally light here; this is orientation and intent.

**Context**

- **Kick Off 2 retro community.** The production site (**KOOL Rating**) is a **statistics and ladder website** — **game results**, derived metrics, and an **Elo rating** computed from **online play** logged on the ladder.

- **Data scope.** Online results are **the core feed today.** Over time there may also be comparable treatment of **offline** (or other) competitions — phased and only when that data discipline exists; agents should assume **parity of spirit** without inventing integrations that aren’t real yet.

- **What this document governs.** The **purpose of this project** is **iterative improvement** of this stats/ratings website: clearer design and feel, newcomer-friendly cues, correctness, and gradual new capability — **not replacing the community ecosystem around it.**

- **Umbrella community.** KOOL/forums/other retro-game sites live **alongside** the ladder as part of one broader KO2/community scene; the ladder should **credibly fit** next to forums and chatter (tone can be approachable; structure stays stats-first unless Dagh directs otherwise).

**North star:** Keep the ladder **fast, trustworthy, and data-dense** for regular KO2/KOOL players **on PC**, while making the surface **friendlier for strangers** and more **warmly intentional** — without turning the site into a different product or rewriting everything “because we can.”

---

## Vision — what we want (broad terms)

- **Honor what works:** The current site already has strong bones: coherent structure (server → ranks → profiles), dense stats, responsive-feeling sorted tables (client-side sorting/paging/filtering), and sensible cross-linking between games and players.

- **Make it nicer to *be* here:** Incremental lifts to **feel and clarity** — navigation that shows where you are, clearer grouping of related views (e.g. “Results / Goals / …” reads as related lenses, not orphaned buttons), calmer typography and spacing, a coherent **dark or light** visual theme with readable contrast for big tables.

- **Open the door for newcomers:** A modest **welcoming layer** above the spreadsheets-for-insiders baseline — enough that a passerby understands *what this is*, *that it’s current*, and *where to explore next*. This is not mandatory “consumer app” fluff; it can be short copy, freshness cues, and small curated hooks (recent highlights, interesting records—only if inexpensive to maintain).

- **Add novelty gradually:** Features that used to feel out of reach (e.g. **rating vs time graphs**, richer opponent breakdowns once data/SQL catches up, light comparison or “interesting stats” summaries) belong in **small phases**, gated on clarity and upkeep — not rushed into one megachange.

- **Stay truthful to the ladder:** Polish should never disguise or toy with metrics. If something is broken or ambiguous, fixing **correctness or empty states** outranks decoration.

---

## What we’re *not* optimising as a primary goal on day one

- Full accessibility/certification agendas (many players use mouse/visual PC-first workflows; **basic readability** still matters).

- Mandatory mobile parity identical to desktop; mobile should be **usable** when convenient, without forcing compromises on dense desktop tables. **Agent contract:** deliberate **read-first, pinch-second** model — dense tables stay tables; not card reflow — [`docs/k2-mobile-smartphone-policy.md`](docs/k2-mobile-smartphone-policy.md).

- Re-platforming **for aesthetics alone** — the default path assumes **incremental improvement on what exists**.

---

## How Dagh wants to work (agents + Cursor)

- **Primary workspace:** Local development in **Cursor**.

- **Style of collaboration:** Dagh steers **goals and taste in plain language** (“incremental polish,” “Tabs / active state reads better,” “player page deserves a rating graph”). Agents handle **implementation details** Dagh prefers not to babysit unless he asks—propose **small, reversible steps** aligned with this brief.

- **Rhythm:** Work in **short vertical slices** (one nav improvement, one table chrome pass, one graph endpoint + chart) rather than sprawling refactors justified by vibes alone.

- **Version control:** **Git**, with commits or branches as the durable history layer. Dagh wants history and rollbacks — not emailing zip files around.

- **Deployment / server:** Dagh’s collaborator **Steve** hosts the live site and databases. Coordination (**SSH**, `git pull`, SFTP—whatever stays low-friction for Steve) is **to be agreed** later. Dagh develops locally **first** and ships when ready — no requirement to define deploy mechanics in advance.

---

## Logistics context (minimal)

- Production **code vs data:** Repository holds **website code** and any **schema snippets** Dagh agrees should live in Git — **database contents and secrets do not.**

- **Staging database (confirmed May 2026):** **MariaDB 10.11.7** on the ratings host (MySQL-compatible). PHP talks to it via **`mysqli`**. Modern analytics SQL (e.g. **window functions** for “Nth game per player” milestones) is viable on staging without schema changes; local dev still needs **`ko2unitydb_config.php`** + a reachable DB copy.

---

## If this conflicts with Dagh

**Dagh’s stated preference wins.** Agents should reconcile this doc with Dagh’s latest message if they diverge.

---

*Last drafted for handoff May 2026.*
