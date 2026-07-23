# Amiga tournament chrono — integer invariant policy (Jul 2026)

**Status:** **Locked** — one-time repair on `ko2amiga_work` + forward live-ops rules.

**Plan:** [`amiga-chrono-integer-implementation-plan.md`](amiga-chrono-integer-implementation-plan.md) · **Insert:** [`amiga-case-c-insert-finish-implementation-plan.md`](amiga-case-c-insert-finish-implementation-plan.md) CI11 · **Delete:** [`amiga-staging-backup-admin-delete-policy.md`](amiga-staging-backup-admin-delete-policy.md) Case C.

---

## 1. Problem

`tournaments.chrono` is catalog/ladder rank metadata copied to L5 as `event_chrono` at finalize. Import-era data used **fractional** chronos (mostly `.5`). `amiga_player_matchup_at_event.event_chrono` was **`int(11)`** and finalize **truncated** — ground/snapshots had `8.5` while matchup stored `8`. Same-day clusters could leak wrong Opponents-at-cutoff rows.

## 2. Invariant (after repair)

| Rule | Detail |
|------|--------|
| **I1** | Every `tournaments.chrono` is a **positive integer** |
| **I2** | Sorting by `(event_date, chrono, id)` ASC assigns ranks **1…N** with `chrono = rank` |
| **I3** | `event_chrono` on all L5 tables **equals** ground `tournaments.chrono` at finalize (matchup column is `double`) |
| **I4** | Tip promote still uses `global_max + 1` / same-day `+1` (integers only) |
| **I5** | Mid-history **insert** opens a slot: `+1` ground chrono on all tournaments strictly after prior **N**; **M** takes the freed integer |
| **I6** | Case C **delete** of **M** symmetrically `-1` on remaining forward ground after **N** |
| **I7** | No new fractional chronos in live-ops |

## 3. One-time repair pipeline (work → seal → staged)

1. **Park reference** — checkpoint `work-YYYY-MM-DD-pre-chrono-integer` + `companion/chrono_baseline_before.json`
2. **DDL + writers** — `event_chrono double` on matchup-at-event; remove `(int)` casts in PHP/Python finalize
3. **Ground renumber** — `python -m scripts.amiga renumber-chronos-integers --apply`
4. **Rebuild L5** — `python -m scripts.amiga simul` (applies schema + refinalize derived)
5. **Verify** — full verify suite + matchup/ground chrono oracle + integer audit
6. **Seal** — `work-YYYY-MM-DD-chrono-integer` in git
7. **Export** — `export_ko2amiga_work.ps1` for staged WinSCP import

## 4. What is *not* renumbered on delete

Legacy fractional deletes (pre-repair archaeology) are out of scope. After repair, all deletes use **I6**.

## 5. Authority

Ground `tournaments.chrono` is **ops-assigned metadata** (not derived from games). L5 `event_chrono` is a **finalize snapshot** — rebuilt by simul/refinalize, not hand-patched across tables.