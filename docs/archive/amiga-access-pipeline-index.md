# Amiga Access pipeline — archived docs index (DOC-1)

**Status:** **Archive index (Jul 2026)** — Access-era L0–L5 pipeline retired after modern ground cutover.

**Forward authority:** [`amiga-modern-ground-platform.md`](../amiga-modern-ground-platform.md) — living ground on `ko2amiga_work`, daily **simul**, staging export via `export_ko2amiga_work.ps1`.

---

**If you are a new agent:** read [`amiga-modern-ground-platform.md`](../amiga-modern-ground-platform.md) **§0** before any doc in the table below. Those docs describe the **Access-era** pipeline (historical).

## Archived specs (historical context only)

| Doc | Era | Notes |
|-----|-----|-------|
| [`amiga-ground-stack.md`](../amiga-ground-stack.md) | Jun 2026 | Strict L0→L5 chain intent |
| [`amiga-ground-layers-policy.md`](../amiga-ground-layers-policy.md) | Jun 2026 | Layer glossary, packs, G-rules |
| [`amiga-import-layer.md`](../amiga-import-layer.md) | Jun 2026 | L2→L3 witness import transforms |
| [`amiga-ground-layers-implementation-plan.md`](../amiga-ground-layers-implementation-plan.md) | Jun 2026 | Slice plan (complete) |
| [`amiga-modern-simul-implementation-plan.md`](../amiga-modern-simul-implementation-plan.md) | Jul 2026 | S-1 implementation plan (complete) |

## Still active (not archived)

| Topic | Doc |
|-------|-----|
| Living ground / simul | [`amiga-modern-ground-platform.md`](../amiga-modern-ground-platform.md) |
| Video on work | [`amiga-modern-video-policy.md`](../amiga-modern-video-policy.md) |
| L5 writers / verify | [`amiga-derived-write-policy.md`](../amiga-derived-write-policy.md) |
| Staging export/import | [`amiga-staging-handoff.md`](../amiga-staging-handoff.md) |
| **Staging authority (prod vs repair shop)** | [`amiga-staging-authority-policy.md`](../amiga-staging-authority-policy.md) |
| Live ops / community | [`amiga-live-ops-platform.md`](../amiga-live-ops-platform.md) |
| Data contract / DDL | [`amiga-data-contract.md`](../amiga-data-contract.md) |

## Shipped feature plans (historical execution only)

Jun–Jul 2026 features shipped via legacy **`prove`** on frozen **`ko2amiga_db`**. Banner types at doc top:

| Banner | Meaning |
|--------|---------|
| **Historical execution record** | Feature **complete** — steps are archaeology |
| **In progress** / **Partially complete** | Open track — only finished slices are historical |
| **Product policy** | Behaviour rules still authoritative; prove = ship path, simul = forward |
| **Oracle mechanics** / **Live ops policy** | Domain-specific (video, RTB) |

Each plan below has the appropriate banner — **do not run prove steps for new work**. Forward: **`simul`** on **`ko2amiga_work`**.

| Feature | Plan |
|---------|------|
| Player create (PC) | [`amiga-player-create-implementation-plan.md`](../amiga-player-create-implementation-plan.md) |
| Country registry (CR) | [`amiga-country-registry-implementation-plan.md`](../amiga-country-registry-implementation-plan.md) |
| Running tournament boundary (RTB) | [`amiga-running-tournament-boundary-implementation-plan.md`](../amiga-running-tournament-boundary-implementation-plan.md) |
| World Cup flag | [`amiga-world-cup-flag-implementation-plan.md`](../amiga-world-cup-flag-implementation-plan.md) |
| Community stats, WC HoF, snapshots, … | Grep `docs/amiga-*-implementation-plan.md` — read the banner before executing steps |

**Product policies** that mention `prove` carry a **Product policy** or domain banner — body text may still describe the ship-era path.

---

## Oracle commands (frozen `ko2amiga_db` only)

```powershell
python -m scripts.amiga prove          # nuclear L1→L5 — archaeology
python -m scripts.amiga parity         # work vs oracle compare
powershell -File scripts\export_ko2amiga_db.ps1   # oracle export shim
```

**Do not** assign daily staging refresh or forward features from archived Access pipeline docs.