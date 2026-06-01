# Milestone unlock event UI — plan & register

**Status:** Implemented May 2026. **Authority** for how milestone unlock rows show **links** and **context** on the website.

**Not in DB:** `player_milestones.source_*` and `achieved_at` are rebuild/post-game evidence. This doc + JSON are **product UX** choices per `milestone_key`.

---

## Problem

Each unlock has:

1. **Milestone** — feat identity → `milestone.php?key=…`
2. **Unlock event** — the game, league period, lobby moment, or UTC day that qualified the player

Garden cards already pick an **event link** (`Game` / `League` / `Games` / none) from a per-key register. Other surfaces drifted:

- **Server Recent** (`milestones.php`) had no event link.
- **Milestone achievers** (`milestone.php`) used ad hoc rules (e.g. full **match line** for game keys like Double Digit Merchant) that do not work for league or day-complete keys.

We need **one register**, **two axes**, **one PHP resolver family**, multiple **surfaces**.

---

## Two axes per `milestone_key`

| Axis | Field in JSON | Question |
|------|----------------|----------|
| **Event link** | `event_link` | Where does the user go for the qualifying event? |
| **Event context** | `event_context` | How we **describe** that event (match line, league label, day copy, …) |
| **Event prose** *(optional)* | `event_context_label` | Fixed one-liner when `event_context` = `day_games` (`perfect_day`, `nightmare_day`) |

### `event_link` values

| Value | Link label | Href source |
|-------|------------|-------------|
| `game` | Game | `source_game_id` → `game.php` |
| `league` | League | `source_league_*` → Status league period page |
| `player_day_games` | Games | `individual3.php?id=&day=` from `achieved_at` day-close |
| `none` | *(no link)* | e.g. `entered_arena` |

### `event_context` values

| Value | Used on | Rendering |
|-------|---------|-----------|
| `match_line` | Achievers **detail** (default for `event_link=game`) | Status scoreline: **NameA · GoalsA–GoalsB · NameB** (official side order, not unlocker-first) |
| `league_period` | Achievers detail | Short league period label (+ link) |
| `day_games` | Achievers detail | One-line day summary (+ Games link) |
| `lobby_copy` | Achievers detail | “Joined the ladder” (no link) |
| `none` | Achievers detail | `—` |
| `link_only` | *(implicit on **compact** surface)* | No extra prose; link after rule only |

**Default mapping** (generator): `game`→`match_line`, `league`→`league_period`, `player_day_games`→`day_games`, `none`→`none`. **Overrides:** `entered_arena` → `lobby_copy`.

---

## Surfaces

| Surface | PHP constant | Pages | Link column | Event description |
|---------|--------------|-------|-------------|-------------------|
| **Compact** | `compact` | Garden date line; **server Recent** after `rule_short` | Yes | No (compact skips event-description HTML) |
| **Detail** | `detail` | `milestone.php` achievers table | **Link** | **Event** |

**Garden:** `achieved_label · {event link}` — compact.

**Server Recent:** `{player} · {feat link}{rule} · {event link}` — compact. Tier re-wrap decodes href entities once before `k2_h` (league `&` query params).

**Achievers:** **Event** = what happened (scoreline, league period words, day copy, lobby copy). **Link** = register deep link (`Game` / `League` / `Games`) or `—`.

---

## Lockstep artifacts

| Artifact | Role |
|----------|------|
| [`data/milestone_garden_links.json`](../data/milestone_garden_links.json) | Machine register (`event_link`, `event_context`, `notes`; `garden_link` mirrors `event_link` for legacy readers) |
| [`docs/milestones-catalog.md`](milestones-catalog.md) | **Generated master table** — tier, rule, Link, Event per key |
| [`docs/milestones-garden-links.md`](milestones-garden-links.md) | Link + Event index (subset of catalog) |
| [`scripts/oneoff/build_milestone_garden_links.py`](../scripts/oneoff/build_milestone_garden_links.py) | Regenerate JSON + md from seed + `OVERRIDES` |
| [`site/public_html/includes/milestone_garden_links.php`](../site/public_html/includes/milestone_garden_links.php) | Load JSON; `k2_milestone_unlock_event_link_html()`; `k2_milestone_unlock_event_context_html()` |
| [`site/public_html/includes/player_milestones_helpers.php`](../site/public_html/includes/player_milestones_helpers.php) | Garden, Recent query/render, achievers |

**Regenerate after catalog/key changes:**

```text
python scripts/oneoff/build_milestone_garden_links.py
```

**Staging:** copy JSON per [`coordination/milestones-staging-cutover-packet.md`](coordination/milestones-staging-cutover-packet.md).

---

## PHP API (do not bypass in UI)

```php
k2_milestone_unlock_event_link_html(int $playerId, array $unlockRow): ?string
k2_milestone_unlock_event_context_html(int $playerId, array $unlockRow, string $surface): ?string
```

`$unlockRow` must include: `milestone_key`, `achieved_at`, `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`, and for `match_line` a joined `ratedresults` row (`id`, `idA`, `idB`, `GoalsA`, `GoalsB`, `NameA`, `NameB`).

**Aliases (deprecated names):** `k2_milestone_garden_link_html()` → link resolver; `k2_milestone_garden_link_entry()` → profile with `event_link` / `event_context`.

---

## Non-goals

- No `event_link` / `event_context` columns on `player_milestones`.
- No full match lines on the 100-row **server Recent** feed (compact = link only).
- Do not fork link rules per page in templates (`if (perfect_day)` in PHP outside resolver).
- Tier colors (`--k2-pure-*`, `--k2-ms-holo`) unchanged by this work.

---

## Master table

**Per-key catalog:** [`milestones-catalog.md`](milestones-catalog.md) — all **112** keys (tier, title, rule, **Link**, **Event**, `rule_probe`).

**Workflows:** [`milestones-README.md`](milestones-README.md).

Regenerate after register edits:

```text
python scripts/oneoff/build_milestone_garden_links.py
```

**To change Link or Event prose:** edit `OVERRIDES` in `scripts/oneoff/build_milestone_garden_links.py`, run the script, deploy `data/milestone_garden_links.json`.

**To change title or rule:** edit `data/milestones_definitions_seed.json`, reload `milestone_definitions`.

---

## Sanity checklist (Dagh)

1. **Garden** — unlocked card: date · `Game` / `League` / `Games` / nothing (`entered_arena`).
2. **Recent** — `milestones.php`: each row has player, feat link, rule, then · `Game`/`League`/`Games` when applicable.
3. **`milestone.php?key=dd_merchant_10`** — Event = scoreline; Link = **Game**.
4. **League key** — Event = period label; Link = **League**.
5. **`perfect_day` / `nightmare_day`** — Event = day copy; Link = **Games**.
6. **`entered_arena`** — no event link; lobby copy in garden/achievers where shown.

---

## Related docs

- Evidence / rebuild: [`website-data-contract.md`](website-data-contract.md) § `player_milestones`
- Facilitation matrix: [`milestones-facilitation.md`](milestones-facilitation.md)
- Product tiers: [`milestones-product-spec.md`](milestones-product-spec.md)
