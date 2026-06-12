# Amiga standings scope unification — slice 4 handoff

**Date:** 2026-06-11  
**Slice:** 4 — Readers and URLs  
**Plan:** [`amiga-standings-scope-implementation-plan.md`](../../amiga-standings-scope-implementation-plan.md)

---

## Goal

Public tournament page and libs use `league`; legacy `?scope=overall|group` redirect to canonical URLs.

---

## Checklist

- [x] `tournament.php` — default `league` + `''`; labeled league tabs; implicit tab only when mixed scopes; early 302 redirect bootstrap
- [x] `amiga_tournament_lib.php` — `list_league_labeled_scopes`, `canonicalize_scope_request`, URL builder, phase resolve, `league_scopes` index column
- [x] Legacy `?scope=overall` → 302 `?id=N`; `?scope=group&scope_key=…` → `scope=league`
- [x] Phase link title → "Phase standings"
- [x] `amiga_tournament_format_kind()` uses labeled league scopes

### Verification

- [x] `php -l` tournament.php + amiga_tournament_lib.php
- [x] curl: `?scope=overall` id=24 → 302 → table with 5 players
- [x] curl: `?scope=group&scope_key=League+Stage` id=22 → 302 canonical league URL
- [x] Browser: id=24 — Athens XCII standings, no "Overall" tab
- [x] Browser: id=22 — "League Stage" tab + bracket

---

## Files changed

| File | Change |
|------|--------|
| `site/public_html/amiga/tournament.php` | League nav + redirect bootstrap before HTML |
| `site/public_html/includes/amiga_tournament_lib.php` | Scope helpers, URLs, resolve, catalog `league_scopes` |

---

## Verification output

```
curl -sI .../tournament.php?id=24&scope=overall
→ 302 Location: /amiga/tournament.php?id=24

curl -sI .../tournament.php?id=22&scope=group&scope_key=League+Stage
→ 302 Location: ...&scope=league&scope_key=League+Stage

curl -sL .../id=24&scope=overall → Athens XCII + Alkis P in standings table

Browser id=24: 5 player links, Event stats nav, no Overall tab
Browser id=22: League Stage (active), Bracket, 12-player league table + KO bracket
```

---

## STOP GATE C

User browser check on tournaments **22** and **24** before slice 5.

URLs:
- http://ratingskickoff.test/amiga/tournament.php?id=24
- http://ratingskickoff.test/amiga/tournament.php?id=22
- Legacy: http://ratingskickoff.test/amiga/tournament.php?id=24&scope=overall

---

## Next slice

**Slice 5** — `standings_parity.py`, verify extensions, catalog stats writer grep guards.
