# K2 nav implementation checklist

**For agents (read before adding or refactoring page chrome nav -- hub tabs, wing ribbons, sub-nav rows, player nav, hub shells).**

Spacing contract: [`nav-spacing-policy.md`](nav-spacing-policy.md). Segment grammar: [`design-direction.md`](design-direction.md). Routes / sub-hubs: [`url-routes.md`](url-routes.md) section Sub-hub navigation.

**Do not invent a one-off nav bar or ad-hoc spacing.** Find the closest **reference include** below, read that file and its host page, then copy the pattern.

---

## 1) Pick a reference (read one file first)

| Scenario | Reference include | Host page / shell | Pattern |
|----------|-------------------|-------------------|---------|
| Online hub primary tabs | `includes/hub_nav.php` | Any online hub page after `site_header.php` | `.k2-hub-bar` > `.k2-hub-tabs` |
| Amiga hub primary tabs | `includes/amiga_hub_nav.php` | Amiga hub pages | Same; TT stamp may precede bar |
| Hub chapter (title + lede) | `includes/k2_hub_chapter.inc.php` | LB wing, Games/Milestones hub, WC hub | Set `$k2HubChapterTitle` / `$k2HubChapterLede` before include |
| Online LB wing ribbon | `includes/lb_nav.php` | `leaderboards/rating.php` | Pattern **A**: wing closes; content is **sibling** |
| LB Activity sub-nav | `includes/lb_activity_nav.php` | `leaderboards/activity/peaks.php` | Pattern **B**: wing + sub-nav **siblings**; sub-nav owns gap to table |
| LB League honours | `includes/league_honours_leaderboard.php` (panel markup) | `leaderboards/league-honours.php` | Pattern **C**: wing + `.k2-lb-league-honours` panel; subnav inside panel |
| Games hub sub-nav | `includes/games_hub_nav.php` | `games/recent.php` + `games_hub_shell_*.inc.php` | `.k2-games-hub-tabs` |
| Milestones hub sub-nav | `includes/milestones_hub_nav.php` | `milestones/recent.php` + `milestones_hub_shell_*.inc.php` | `.k2-ms-hub-tabs` in `player-milestones.css` |
| Amiga LB wing | `includes/amiga_lb_nav.php` | `amiga/leaderboards/rating.php` | `.k2-chrome-tabs.k2-amiga-lb-tabs` (segment width; online LB stays full-width for filters) |
| Amiga tournaments index filter | `includes/amiga_tournament_index_nav.php` | `amiga/tournaments.php` | `.k2-chrome-tabs.k2-amiga-tournament-index-tabs` |
| Amiga WC hub wing | `includes/amiga_world_cups_hub_nav.php` | `amiga/world-cups/` shell | `.k2-amiga-world-cups-hub-tabs` |
| Amiga WC inner tabs | `amiga_world_cups_players_nav.php`, `_countries_nav.php`, `_stats_nav.php` | WC players/countries/stats views | Stacked `.k2-chrome-tabs` siblings |
| Player profile nav | `includes/player_nav.php` | `player/*.php` with `body.k2-player-wing` | `.k2-chrome-tabs.k2-player-wing-tabs` (segment width; `.k2-player-nav-bar` stays full-width on tournament detail) |
| Amiga player profile nav | `includes/amiga_player_nav.php` | `amiga/player/*.php` | Same segment grammar |
| Player wing hub bar | `includes/player_wing_hub_nav.inc.php` | Online player shells after `site_header.php` | `.k2-hub-bar` (no active tab); tint on hub only |
| Amiga player wing hub bar | `includes/amiga_player_wing_hub_nav.inc.php` | Amiga player shells after `site_header.php` | Same; TT ribbon still from `site_header` above hub |
| Player opponents sub-nav | `includes/player_opponents_nav.php` | `includes/player_opponents_page.php` | Pattern **B** wrapper `.k2-chrome-tabs.k2-player-opponents` |
| Player milestones sub-nav | `includes/player_milestones_nav.php` | `includes/player_milestones_page.php` | Pattern **B** wrapper `.k2-chrome-tabs.k2-player-milestones` |
| Amiga player nav | `includes/amiga_player_nav.php` | Amiga player pages | `.k2-chrome-tabs.k2-player-wing-tabs` (see row above) |
| Amiga tournament nav | `amiga/tournament.php` markup + `amiga-tournament.css` | Tournament + stages | `.k2-player-nav-bar` + `.k2-amiga-tournament-stages-nav` |
| Hub shell (`.k2-page-nav` close) | `includes/milestones_hub_shell_end.inc.php` | Compare `games_hub_shell_end.inc.php`, `amiga_world_cups_hub_shell_end.inc.php` | One `</div><!-- .k2-page-nav -->` before `</body>` |

If unsure: **grep** the nearest neighbour for `k2-chrome-tabs`, `k2-hub-bar`, or `k2-player-nav-bar` in `site/public_html/` and open that include.

---

## 2) Required markup grammar

All page-chrome nav uses the **segment track + outline active cell** (see design-direction):

- Outer: `<div class="k2-chrome-tabs …">`
- Bar: `<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="…">`
- Tab: `<a class="k2-chrome-tabs__tab is-active" …>`

Hub primary tabs use `.k2-hub-bar` > `.k2-hub-tabs.k2-nav-pills` > `.k2-hub-tabs__btn` instead -- still segment grammar.

**Set active state in PHP** before include (`$k2HubTabActive`, `$k2LbWingActive`, `$k2LbActivityView`, etc.) -- do not hard-code `is-active` in new pages without the same variable pattern as the reference.

---

## 3) Spacing rules (mandatory)

Token: **`--k2-nav-gap: 12px`** in `theme.css`. Full policy: [`nav-spacing-policy.md`](nav-spacing-policy.md).

| Rule | Do | Don't |
|------|-----|-------|
| **Bottom-only** | Each nav layer: `margin-bottom: var(--k2-nav-gap)` | Content `.k2-table-wrap { margin-top: … }` for chrome spacing |
| **Sub-layers** | `margin-top: 0` on sub-nav chrome | `margin-top` to separate wing from sub-nav |
| **Wing base** | Rely on `.k2-chrome-tabs { margin-bottom: var(--k2-nav-gap) }` | Literal `4px` / `16px` / `20px` on new nav hooks |
| **Pattern B wrapper** | Wrapper `margin-bottom: 0`; inner `__nav` owns gap to content | Wrapper `margin-bottom: 4px` + inner gap (old bug) |
| **Conditional spacing** | Same gap everywhere | New `:has(+ …)` lists to zero wing margin |
| **Realm parity** | Reuse online classes/includes on Amiga | Amiga-only spacing tokens or forks |

**Documented exceptions** (do not "fix" without product ask): H2H opponents nav 20px to picker block; `.k2-hub-bar` top 16px; HoF `--k2-hub-chapter-to-content-gap` 22px. See policy Phase 3 audit.

Adding a **new sub-nav class** to `theme.css`? Add it to the existing sub-nav block (`.k2-games-hub-tabs`, `.k2-lb-activity-tabs`, …) with `margin-top: 0; margin-bottom: var(--k2-nav-gap)` -- do not create a parallel spacing system.

---

## 4) Page shell checklist

1. **`site_header.php`** opens `<div class="k2-page-nav">` -- do not open a second one.
2. **Hub / wing / sub-nav includes** live inside `.k2-page-nav` before main content (or wrap main inside page-nav for hub shells -- match reference shell).
3. **Close `.k2-page-nav` once** before `</body>` (see `milestones_hub_shell_end.inc.php`). Do **not** resurrect `lb_nav_end.php` (removed Jun 2026).
4. **LB plain wing pages:** `lb_nav.php` already closes its `.k2-chrome-tabs` wrapper; table follows as sibling.

---

## 5) Before shipping -- self-check

- [ ] Read reference include + host page from section 1 (not only this checklist).
- [ ] New tabs use existing segment markup; active state via PHP variable.
- [ ] No new literal nav spacing in page CSS -- extend `theme.css` only if reference does, using `--k2-nav-gap`.
- [ ] No `:has()` spacing switches; no `.k2-chrome-tabs__bar + .k2-table-wrap` resurrection.
- [ ] Hard refresh neighbour URL: gap below each nav layer looks **12px** (24px wing-to-table when sub-nav stacked).
- [ ] Amiga page: same classes/rules as online counterpart.
- [ ] Part A docs if new route or sub-hub: [`url-routes.md`](url-routes.md), [`UPDATE_DOCS.md`](UPDATE_DOCS.md) (`PROJECT_MEMORY.md` line).

---

## 6) When *not* to use this checklist

- **Adding one tab** to an existing include (edit the PHP tab array only).
- **Panel-internal** controls (Status period tabs, milestone detail panel tabs, tier filters inside a card) -- local margins OK; see policy Out of scope.
- **Tables** -- use [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) instead.

---

## Related

- [`nav-spacing-policy.md`](nav-spacing-policy.md) -- locked decisions N1-N10, patterns A/B/C
- [`nav-spacing-implementation-plan.md`](nav-spacing-implementation-plan.md) -- shipped phases + smoke URLs
- [`design-direction.md`](design-direction.md) -- segment track visuals, tint picker
