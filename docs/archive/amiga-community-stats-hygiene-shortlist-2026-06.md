# Amiga community stats — hygiene shortlist (archived)

**Archived:** Jun 2026 — merged into [`../amiga-community-stats-implementation-plan.md`](../amiga-community-stats-implementation-plan.md) § **Phase 2 — Verify hygiene**.

---

# Amiga community stats — hygiene shortlist (recentering)

**Purpose:** Checklist before adding **new fact grains** or chart read paths on `amiga_community_*`.

**Authority:** [`amiga-community-stats-policy.md`](../amiga-community-stats-policy.md) · [`amiga-derived-write-policy.md`](../amiga-derived-write-policy.md) (prove-only writes).

**Repair / corrections:** `python -m scripts.amiga prove` only.

---

## What is already solid (do not re-litigate)

| Area | Gate |
|------|------|
| Python holy loop | `replay` → `persist_community_for_tournament` each finalize |
| Stored headline + facts | `verify-community-stats` in `prove` (multi-event oracle) |
| PHP **build** math | `verify-php-community-parity` (sample tournaments + T24) |
| HoF vs community split | `035` — aggregates off realm/HoF tables |
| Activity present + TT headline | `amiga_activity_summary.php` → `amiga_community_headline_load` |
| Export | `export_ko2amiga_db.ps1` dumps all three community tables |

---

## `verify_php_finalize_parity` — intentional absence

Retired with refinalize (Jun 2026). See [`retired-amiga-refinalize-2026-06.md`](retired-amiga-refinalize-2026-06.md). Community PHP confidence = `verify-php-community-parity` (build-only).

---

## Suggested hygiene work (before new grains)

### P0 — Verify-only hardening (no new writers)

| # | Item | Deliverable |
|---|------|-------------|
| 1 | **Stronger `verify-community-stats` SQL guards** | Every snapshot has facts; no orphan facts; timeline cols match `tournaments` |
| 2 | **Registry parity unit test** | Python `COMMUNITY_HEADLINE_COLUMNS` == PHP registry |
| 3 | **`verify-php-community-parity` fail if PHP missing** | Optional `AMIGA_REQUIRE_PHP=1` on dev |

### P1 — Doc + code hygiene

| # | Item |
|---|------|
| 4 | Realm/community implementation plans: mark batch rebuild tasks retired | **Done** Jun 2026 — live docs sweep ([`amiga-derived-write-policy.md`](../amiga-derived-write-policy.md)) |
| 5 | Dead bulk helpers in Python modules (optional delete pass) |
| 6 | `amiga_community_facts_query()` when chart APIs ship |

### Explicitly rejected

- Batch `community-stats-rebuild` or any derived repair CLI — see [`amiga-derived-write-policy.md`](../amiga-derived-write-policy.md)
- Restoring refinalize / `verify_php_finalize_parity`

---

## Sign-off

```powershell
python -m scripts.amiga prove
```

*Updated Jun 2026 after derived-write policy lock.*
