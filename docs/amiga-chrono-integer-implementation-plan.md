# Amiga chrono integer — implementation plan (Jul 2026)

**Status:** **Complete** — repair on work Jul 2026; seals `pre-chrono-integer` + `chrono-integer`.

**Policy:** [`amiga-chrono-integer-policy.md`](amiga-chrono-integer-policy.md)

---

## Slices

| # | Slice | Proof |
|---|--------|-------|
| **0** | Seal `work-2026-07-23-pre-chrono-integer` + `chrono_baseline_before.json` | Done |
| **1** | `052_matchup_event_chrono_double.sql` + finalize PHP/Python `float` + verify oracle | `verify-player-matchups` |
| **2** | `renumber-chronos-integers` CLI | audit: ints 1..N, ladder order preserved |
| **3** | `python -m scripts.amiga simul` on work | verify green |
| **4** | Seal `work-2026-07-23-chrono-integer` | manifest + git |
| **5** | `amiga_chrono_integer_lib.php` — insert `+1` / delete `-1` | insert+delete parity vs seal |
| **6** | `export_ko2amiga_work.ps1` | staged `_import/` ready |

## Insert prepare (Case C)

After `truncate > N` and `reset forward`:

1. `amiga_chrono_integer_bump_forward_after_cutoff($con, $n, $mId)` — DESC `+1` on all rows strictly after **N** except **M**
2. Returns slot integer → `UPDATE tournaments SET chrono = slot WHERE id = M`
3. Promote M if needed → phased finalize chain (unchanged)

## Delete prepare (Case C)

After `DELETE` M ground:

1. `amiga_chrono_integer_decrement_forward_after_cutoff($con, $n)` — ASC `-1` on all rows strictly after **N**
2. Reset forward → project → refinalize (unchanged)

## Parity smoke (Dagh on staged)

1. Note ground chronos at tip from seal manifest
2. Mid-history insert test event → forward recomputed
3. Case C delete test event → ground chronos match pre-insert seal

## Comparison report

`python -m scripts.amiga chrono-integer-compare` — diffs `chrono_baseline_before.json` vs live work; reports fractional→int mapping and matchup mismatch before/after.