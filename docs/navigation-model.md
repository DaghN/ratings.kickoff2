# Navigation model — realm hub vs entity pages

**Status:** Current navigation invariants (Jun 2026). Authority for *"which pages show an active hub pill, and where does an entity page live?"*

**Authority / related:** [`hub-ia-agreement.md`](hub-ia-agreement.md) (hub shape + tab order) · [`url-routes.md`](url-routes.md) (route map + foldered sub-hubs) · [`k2-page-structure-checklist.md`](k2-page-structure-checklist.md) (new page / tab / mode — read before choosing paths) · [`k2-nav-implementation-checklist.md`](k2-nav-implementation-checklist.md) (how to build nav) · [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md).

This doc states the **invariants** so they are not relitigated per feature. It is realm-agnostic (Online + Amiga). Decision IDs **NM1–NM6** are citeable.

---

## Two kinds of page

| Kind | What it is | Examples |
|------|-----------|----------|
| **Hub / sub-hub place** | A fixed node in the information architecture — a tab, or a mode within a tab. One instance; a known home. | Status, Activity, Leaderboards (+ wings), Games hub, Milestones hub; Amiga: News, Leaderboards, World Cups (+ wings), **Countries index**, **Tournaments index**. |
| **Entity page** | A single **instance** of an entity *type*, looked up by id / key / token. Many instances exist; the page shape is the same for each. (Informally: an "atom".) | a game, a player, a tournament, a country, a milestone. |

Entity **types** today: **game, player, tournament, country, milestone**. Many instances, one page shape each.

---

## Invariants

**NM1 — The hub bar is always present.**
Every page in a realm renders that realm's hub bar (Online `hub_nav.php`, Amiga `amiga_hub_nav.php`), on every page type, in both present and time-travel modes. In time travel the bar sits under the snapshot picker / ribbon machinery, but it is still there. The hub bar is a fixed constant users and devs can rely on.

**NM2 — An active pill marks a place, never an entity.**
A hub pill is shown active only on **hub / sub-hub places**. **Entity pages show no active pill** — the hub bar is present but inert. This is already how `game.php` and the player pages behave.

**NM3 — Entity pages live at the realm root as their own namespace.**
An entity page belongs to the realm, not to a hub tab. Its URL and its file live at the realm-root level, **never nested inside a hub-tab folder**. Naming convention:

- **Singular = entity namespace** — `game.php`, `player/`, `tournament/`, `country/`.
- **Plural = hub place** — `tournaments.php`, `countries/`, `world-cups/`, `leaderboards/`.

So a single tournament is `/amiga/tournament/...` (entity), distinct from the **Tournaments** hub listing `/amiga/tournaments.php` (place). A single country is `/amiga/country/...` (entity), distinct from the **Countries** hub `/amiga/countries/...` (place).

**NM4 — Folder if the entity has tabs; leaf file if single-page.**
Follows the foldered-sub-hub rule in [`url-routes.md`](url-routes.md):

| Entity shape | URL form | Examples |
|--------------|----------|----------|
| Single page (no internal tabs) | leaf `*.php?key=` | `game.php?id=`, `milestone.php?key=` |
| Has its own tabs / sections | folder `entity/{tab}.php?id=` | `player/{profile,games,...}.php?id=`, `tournament/{event-stats,games,...}.php?id=` |

**NM5 — The active pill reflects the page's identity, not how you arrived.**
Do not light a hub pill because of where the user came from, or where the data is "stored". A **World Cup is also a tournament** — reached from both the World Cups hub and the Tournaments hub — so it belongs to **no** hub exclusively. Multi-parent entities are exactly why entity pages carry no pill.

**NM6 — An entity may carry its own context sub-nav (below the hub bar).**
Having internal tabs does **not** change NM1–NM3. The hub bar stays; the entity adds its own nav *below* it — e.g. player Profile / Opponents / Milestones / Games; tournament Event stats / Standings / Games / Videos; country Roster / Rivals. This is a property of the entity, not a separate page tier. (There is no "hub bar gets replaced" tier — see history below.)

---

## Why (so we do not reopen this)

- **Uniformity** — one rule for chrome: users always have the hub bar; devs never re-decide "does this page swap its tabs out?".
- **Honesty** — the pill stops lying. Clicking a World Cup from the World Cups hub no longer dumps you on a lit **Tournaments** pill.
- **Bookmarks read as places** — `/amiga/tournament/...` and `/amiga/country/...` say "a tournament / a country", not "the Tournaments tab".

---

## Applying it (current state)

| Page | NM-correct? | Notes |
|------|-------------|-------|
| `amiga/game.php?id=` | Yes | — |
| `amiga/player/*.php?id=` | Yes — hub bar present, no pill; player sub-nav below (NM6) | — |
| `amiga/tournament/*.php?id=` | Yes (Jun 2026) | Entity namespace (sibling of the `tournaments.php` hub listing); active pill neutralized (`$k2AmigaHubTabActive = ''` in `amiga_tournament_page.php`); tournament section nav is its NM6 sub-nav |
| `amiga/country/roster.php?country=` · `amiga/country/rivals.php?country=` | Yes (Jun 2026) | Relocated out of the `countries/` hub folder to the singular `country/` namespace (NM3); **Roster · Rivals** segment (NM6); no active pill (NM2). Old `countries/roster.php` 302s to the new path. Rivals is a placeholder pending content. |
| `amiga/countries/index.php` | Yes | Hub **place** — keeps the **Countries** active pill |

**Stale doc corrected (Jun 2026):** earlier IA notes said player pages *replace* the hub tabs with player context tabs. No longer true — the hub bar is present with no active pill (NM1/NM2); the player nav is an NM6 sub-nav below it. Fixed in [`hub-ia-agreement.md`](hub-ia-agreement.md).

See [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) for the `country/` entity namespace and the Roster / Rivals segment.