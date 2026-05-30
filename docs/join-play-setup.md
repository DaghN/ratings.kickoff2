# Play & setup page (`join.php`)

**Status:** shipped in repo (May 2026). Conversion / onboarding layer for online play and community join.

**Related:** `docs/hub-ia-agreement.md` (header link), `PROJECT_BRIEF.md` (welcoming layer), product audit May 2026 (Play / Join doorway).

---

## Purpose

Answer for newcomers and returners:

- The scene is still alive (WC since **2001**, online most evenings, this ladder).
- **Honest path:** download (`kickoff2.net`) → Discord → beta key for **in-app registration** (login/lobby) → joystick → play.
- Keyboard: poke around only; joystick expected for real play.
- **Inside the app** (scent, not brochure): lobby, matchmaking, spectate, CPU/empty practice, optional custom graphics, recorded/archived games.
- Tone: warm and understated — show the scene, avoid preachy meta copy; do not centre “rated” or WC-as-purpose-of-online.

Does **not** document Steve’s employment constraints or release politics — page only describes community beta + Discord access.

---

## Routes & chrome

| Item | Location |
|------|----------|
| Page | `site/public_html/join.php` |
| Markup | `includes/join_page_section.php` (warm community copy; May 2026) |
| `join_alt.php` | 302 → `join.php` (legacy eval URL) |
| Outbound URLs | `includes/join_page_links.php` — **edit links here** |
| Hub tab | `includes/hub_nav.php` — **Play & Setup** (last tab, after Hall of Fame); `$k2HubTabActive = 'join'` on `join.php` |
| Header | No separate header link (hub tab only) |

---

## Canonical outbound links

Maintained in `join_page_links.php`:

| Key | URL |
|-----|-----|
| Discord | https://discord.com/invite/mXcmuE4kzj |
| kickoff2.net | https://kickoff2.net/ |
| kickoff2.com | https://kickoff2.com/ |
| KOA forum | https://ko-gathering.com/forum |
| Promo video (embed) | YouTube `-OD-f0t92VQ` (WC playlist clip on join page) |
| Goal tutorial | https://www.youtube.com/watch?v=rOunQzfpmGM |
| WC finals playlist | YouTube playlist `PL_BZxDPPd88YKbQHLod_1EFHPa2iJSIQY` |
| KO2CV TV | https://www.youtube.com/@KO2CV_TV |

USB adapters: Immortal Joysticks, Sordan.ie (eBay), Stepstick USBJoy, Monster Joysticks — see PHP array.

---

## Copy facts (public)

- World Cups since **2001** (no front-page name drop for organisers — lore inside community).
- **27,000+** Amiga results in database.
- Online: **every night**, **growing number of regulars**.
- Netcode: **rollback + fast-forward**; online valuable on its own, not framed as WC prep.
- Amiga realm on site: planned (matches header switcher defer).

---

## Tone rules

- Warm peer, not marketing; not condescending.
- Do not say “just use keyboard” for real play.
- Do not shame newcomers; do not worship veterans as gatekeepers.
- Discord beta step is **transparent**, framed as small-scene hospitality.

---

## Future (optional)

- Live Status snippet (online count) when prod DB is live.
- Reciprocal link from kickoff2.com → ratings.
- Expand FAQ; link milestones when hub tab ships.

*Last updated: May 2026.*
