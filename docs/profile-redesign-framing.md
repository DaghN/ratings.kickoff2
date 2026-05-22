# Profile redesign — Phase 0 framing

**Status:** May 2026. Governs the three Profile lab mocks (`profile_mock_a/b/c.php`) for player **Steve (`id=237`)**.

**Authority:** `PROJECT_BRIEF.md` for north star. This doc + mocks are exploratory — not a production spec until Dagh picks a direction.

---

## 1. Who visits the ratings site

| Visitor | Typical entry | What they need emotionally |
|---------|---------------|----------------------------|
| **The player themselves** | Bookmark, forum link, post-session check | Recognition, story of *their* arc, pride without a report card |
| **Regular rivals** | Leaderboard → profile, Status name link | Context for the next match — form, history, respect not fear |
| **Community insiders** | Deep links, Discord, “look at this game” | Proof the ladder is alive; memorable extremes and rivalries |
| **Curious newcomers** | kickoff2 / search / word of mouth | In 10 seconds: *what is this person on the ladder, are they active, is this site serious?* |

Nobody opens a KO2 profile to audit spreadsheets. They come for **identity in a competitive scene** — online play since 2017, thousands of rated games, names that recur for years.

---

## 2. Jobs to be done (profile page)

1. **Orient** — Who is this? How do they sit on the ladder right now (rank, rating, activity)?
2. **Feel the person** — Are they active? What's their "story" (peak, longevity, signature moments)?
3. **Celebrate participation** — Volume and longevity are virtues in this community; 5,940 games is not a footnote.
4. **Optional depth** — Charts, H2H, opponent tables for those who want analyst mode — **after** the human layer.
5. **Navigate onward** — Games tab for ledger, other feast tabs for specialist tables, links to opponents and games.

**Not the Profile tab's job:** Replace `individual2a`–`c` opponent breakdowns or duplicate every `playertable` row. Those stay on sibling tabs.

---

## 3. Anchor player — Steve (`id=237`)

Snapshot from **`ko2unity_db`** (May 2026):

| Field | Value |
|-------|------:|
| Name | Steve |
| Rank | **#22** (among displayed players) |
| Rating | **1926** |
| Peak | **2279** |
| Games | **5940** |
| W / D / L | 3177 / 705 / 2058 (~53.5% wins) |
| Joined | Apr 2016 |
| Last game | May 17, 2026 |
| Longest win streak | 17 |
| Biggest win | 11–0 (game 9669, 2020) |
| Biggest draw | 9–9 vs Lee (game 6860) |
| Goal festival | 16–5 vs Eternalstudent (game 6551) |
| Top rival | **Lee — 2042 rated games** |

**Why Steve is a strong design anchor:** Extreme **volume** and **longevity** stress-test density; **peak vs current** enables a non-judgmental arc narrative; **active last week** proves "alive" cues; **Lee rivalry** is a built-in story beat no fictional mock would invent.

**Sanity check later (not mock v1):** a low-game or unranked player so empty states don't surprise us at promote time.

---

## 4. What's wrong with production `individual1.php` today

- **First screen = analyst stack:** rating chart, games/month, rating-by-game#, win-rate buckets, top opponents, H2H — before identity lands.
- **Tone = exhaustive ledger:** encyclopedic `playertable` tables reward insiders but feel like homework for everyone else.
- **Hierarchy flattens everything:** a 11–0 from 2020 and yesterday's 8–1 loss have equal visual weight in the scroll.
- **Missed stories:** 5,940 games, #22, Lee ×2042, peak 2279 — buried in cells.

Metrics stay **true**; the **order and framing** are what we're fixing.

---

## 5. Design principles (testable)

1. **First screen answers:** who · how active · one-sentence ladder story · one reason to smile.
2. **Participation is prestige** — game count and tenure get visual honor, not small grey text.
3. **Highlights before histograms** — legendary games and streaks before bucketed win-rate charts.
4. **Comparison is opt-in** — H2H / vs-opponent analytics live in a clearly labeled depth layer.
5. **Losses exist, don't lead** — full truth on other tabs; Profile fold shouldn't open with biggest loss.
6. **Cool = confident, not cruel** — neon noir, bold type, motion with restraint; Blade Runner control room, not RGB guilt trip.

---

## 6. Three mock theses (one pass)

| Mock | Name | Thesis | First-screen bet |
|------|------|--------|------------------|
| **A** | **The Chronicle** | Magazine cover — editorial narrative, trophy moments, rating as supporting story | You *read* Steve before you *analyze* him |
| **B** | **The Wire** | Live pulse — recency, form, rivals; charts support rhythm | Steve feels *present on the ladder tonight* |
| **C** | **The Vault** | Hall of fame — peak, volume, exhibits as monuments | Steve feels *legendary*; depth is museum placards |

Shared across mocks: real `id=237` data, global `theme.css` tokens, site header, feast pills for context, link to production profile.

**Only global site theming is fixed** — layout, components, and Profile IA are free in mocks.

---

## 7. After review

Pick A, B, C, or a labeled hybrid → short **profile contract** (section order + tone rules) → promote to `individual1.php` in small slices.

Do not link mocks from hub nav. Deploy to staging only when ready for wider eyes.

---

*Last updated: May 2026.*
