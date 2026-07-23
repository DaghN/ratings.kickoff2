# Amiga organizer backdate guard — policy (Jul 2026)

**Status:** **Implemented** Jul 2026 — create-league gate on `fixtures.php` (server authoritative + client panel hint). **Staged smoke PASS** Jul 2026-24 (Dagh).

**Parent:** [`amiga-organizer-track-status.md`](amiga-organizer-track-status.md) · [`amiga-staging-backup-admin-delete-policy.md`](amiga-staging-backup-admin-delete-policy.md) **AD8**.

**Surface:** [`fixtures.php`](../site/public_html/amiga/ops/fixtures.php) — **Create league** submit (kitchen compose), not mid-history Finish. Panel toggle is **inline in `fixtures.php`** (primary); optional backup [`amiga-organizer-backdate-guard.js`](../site/public_html/js/amiga-organizer-backdate-guard.js).

---

## 1. Problem

Organizers may set any `event_date` at create (CI1 — played Saturday, Finish Monday). That is correct for **recent** events. Accidental or careless **deep backdates** (e.g. year 2022) trigger **Case C insert** repair chains, re-finalize many forward tips, and are easy to get wrong. Secretaries should not casually insert tournaments far in the past.

**Distinction:** This guard is about **create date**, not **mid-history Finish**. Case C insert (AD7) remains the right tool when a **recent** kitchen’s catalog order sorts before later official tips.

---

## 2. Locked rules (AD8)

| Id | Rule |
|----|------|
| **AD8.1** | **Threshold** = `event_date` more than **one calendar month** before **today** (UTC `gmdate` / `DateTimeImmutable` UTC — matches create day picker default). Dates **on or after** `today − 1 month` are organizer-ok. |
| **AD8.2** | **Organizer session alone** is **not** enough to submit create when AD8.1 fires. |
| **AD8.3** | UI shows an inline panel: *“Only admins can insert tournaments that are more than one month old. Please input admin password.”* + password field + submit (same POST as create). |
| **AD8.4** | **Verify** `$admin_password` on that submit (`amiga_ops_password_matches(..., 'admin')`). Organizer password does **not** satisfy. Explicit re-entry each create — does **not** elevate session. **Admin session alone does not bypass.** |
| **AD8.5** | **Admin-only create** for deep backdates is intentional; historical repair belongs to admins who also have backup/restore/delete surfaces. |
| **AD8.6** | **No change** to Case C insert/delete, chrono bump, or BA2 seals — those run after a valid create. |
| **AD8.7** | **Future:** optional stricter cap (e.g. 7 days) is a product tweak — default v1 = **1 month**. |

---

## 3. Implementation (shipped)

| Piece | Detail |
|-------|--------|
| Helpers | `amiga_fixture_backdate_admin_threshold_ymd()`, `amiga_fixture_event_date_requires_admin_backdate()`, `amiga_fixture_require_admin_backdate_password()` in `fixtures.php` |
| Server gate | `create_kitchen` POST checks date **before** `amiga_fixture_create_kitchen_tournament` |
| Field | `backdate_admin_password` |
| Error copy | `Admin password required for dates more than one month ago.` |
| Client | **Inline script in `fixtures.php`** (primary) + optional `amiga-organizer-backdate-guard.js`; panel toggled from `data-threshold` |
| Defense | `amiga_fixture_create_kitchen_tournament(..., $adminBackdateAuthorized)` refuses deep backdates unless flag set after password verify |

---

## 4. UX notes

- Show the admin password panel **when date picker crosses threshold** (client hint) and **re-validate server-side** on POST (authoritative).
- Do not use native browser `confirm()` — use the same live-ops form styling as finish-confirm (`preview-note--warn`).
- Error copy on wrong/missing admin password: generic “Admin password required for dates more than one month ago.”

---

## 5. Out of scope

- Blocking **edit** of `event_date` on existing tournaments (separate if ever needed).
- CLI `build-tournament` / local repair shop (Dagh machine).
- Changing CI1 / catalog chrono authority.

---

## 6. Changelog

| Date | Change |
|------|--------|
| 2026-07-24 | **Staged smoke PASS** (Dagh) — panel + admin password gate live after fixtures.php sync (inline JS). |
| 2026-07-23 | Harden: inline panel JS (survives `/js` sync miss); create() requires `$adminBackdateAuthorized`; admin session never bypasses. |
| 2026-07-23 | **Implemented** — create gate + panel + JS; threshold UTC −1 month. |
| 2026-07-23 | Locked AD8 intent from Dagh feedback after staged chrono + insert/delete parity pass. |