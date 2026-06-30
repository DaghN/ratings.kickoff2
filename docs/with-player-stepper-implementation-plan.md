# With player stepper — implementation plan

**Status:** **Locked intent (Jun 2026)** — slices defined; code not started.  
**Policy:** [`with-player-stepper-policy.md`](with-player-stepper-policy.md)

---

## Slice 0 — Retire T18

**Goal:** Remove implicit player-page Event stepping; Event wing behaves realm-globally everywhere.

### Tasks

- [ ] Remove `amiga_player_event_stepper_applies()` branching from `amiga_snapshot_context.php`
- [ ] Remove picker accent wiring tied to player path in `amiga_snapshot_chrome.php`
- [ ] Delete or gut `amiga_player_event_stepper_lib.php` (participation query reintroduced in slice 1 if needed)
- [ ] Update `scripts/oneoff/amiga_snapshot_context_probe.php` — player-path Event prev/next equals hub-path
- [ ] Browser smoke: `/amiga/player/tournaments.php?id=X&as=event:Y` chevrons match rating LB for same `as=`

### Acceptance

- No code path changes Event prev/next based on `/amiga/player/` URL alone
- No link-star event picker rows unless explicit filter param exists (none in slice 0)

**No new UI in slice 0.**

---

## Slice 1 — TT Event ribbon (`as_with=`)

**Goal:** Opt-in filter on time-travel Event wing; replaces T18 capability properly.

### Tasks

- [ ] Participation key-set query helper (Amiga; `NumberGames > 0`)
- [ ] Pure `participation_step_keys(catalog, current, participated_set, direction)` helper (no URL)
- [ ] Parse / append / strip `as_with=` (TT link propagation family only)
- [ ] Listbox on Event ribbon row; chevron hrefs respect `as_with=`
- [ ] Optional: event picker link-star rows when `as_with=` matches participated event
- [ ] Extend snapshot context probe for `as_with=` stepping + cutoff clamp

### Acceptance

- `as_with=` off → identical to post–slice-0 realm-global stepping
- `as_with=354` → Event chevrons skip non-participated events; forward blocked at TT cutoff
- Param survives TT-aware internal navigation; not copied to tournament-only URLs unless both families apply

---

## Slice 2 — Tournament chevrons (`id_with=`)

**Goal:** League-style prev/next on tournament entity nav; independent of `as_with=`.

### Tasks

- [ ] Tournament catalog + step keys for `id=` axis
- [ ] Parse / append / strip `id_with=` on tournament folder URLs only
- [ ] Chevron + listbox row right of tournament segment nav (present + TT)
- [ ] Under TT Event wing: respect cutoff-truncated catalog; stay aligned with `as=event:{id}` sync

### Acceptance

- Tournament page can have `as_with=` and `id_with=` independently
- Changing tournament listbox does not change `as_with=` and vice versa
- Reuses participation lookup from slice 1; **no shared URL helpers with slice 1**

---

## Slice 3 — League periods (`start_with=`)

**Goal:** Online parity on `league.php` period row.

### Tasks

- [ ] Online participation per league period key
- [ ] Parse / append / strip `start_with=` on `league.php` peer links
- [ ] Listbox beside existing `k2-league-period__period-steps`

### Acceptance

- Same listbox UX as Amiga surfaces; separate param and propagation
- Online eligible player list (≥ 1 rated game)

---

## Changelog

| Date | Change |
|------|--------|
| 2026-06-30 | Initial plan — slice 0 T18 removal first; separate params per surface. |