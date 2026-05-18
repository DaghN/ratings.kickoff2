# PROJECT_MEMORY — running context for agents

**Who reads this:** Primarily Cursor agents between sessions. Keep it **short and current**; trim or archive stale bullets rather than appending forever.

**Authority (when documents disagree):** `PROJECT_BRIEF.md` defines purpose and taste. **Dagh’s latest message in chat wins** on scope and direction. This file records **logistics, recent work, and near-term intent** — not a second brief.

---

## Current focus

- **Operational loop works:** mirror → edit locally/Git → deploy to **staging** with **WinSCP** (**Synchronize** `site/public_html/` → remote `public_html/`). Prefer **hard refresh** in browser after CSS/JS/PHP uploads.
- **DB-backed PHP** locally still blocked without **`site/config/ko2unitydb_config.php`** + reachable MySQL (see Quick facts); staging uses server-side config. **No schema / writable DB work** until Steve provides dev DB + clearer config exposure.
- Keep changes **small and reversible** (see brief): polish, correctness, phased features — no speculative re-platform.

---

## Next (intended, not committed)

- From Steve: **`config/`** visibility or template; **writable dev DB** (duplicate of KOOL) vs dump — align laptop + staging **`config`** shapes without committing secrets (`gitignored` locals only).
- Optional: **`git add` remaining mirror files** under `site/public_html/` so repo matches SFTP snapshot (audit for credentials first).
- When DB path exists: skim data pages end-to-end, propose next **vertical slice** with clear acceptance checks.

---

## Recent log

| When (approx.) | What |
|----------------|------|
| 2026-05 | Git on **`main`** → [ratings.kickoff2](https://github.com/DaghN/ratings.kickoff2). Workflow: solo dev, shallow branches acceptable. Branch protection deferred. |
| 2026-05 | **`scripts/sftp_mirror.py`** (**Paramiko**) — env-based SFTP **download** of staging home into **`site/public_html/`**; **`ko2unitydb_config.php`** absent from mirrored tree (expected under **`public_html/../config/`** on server). |
| 2026-05 | **Laragon** (`C:\laragon`), junction **`C:\laragon\www\ratingskickoff`** → **`site/public_html`** — **`http://ratingskickoff.test/`** after **Start All** ( **`index.php`** works without DB; ladder pages need config+DB locally). |
| 2026-05 | Linux **404** asset fixes: folder **`Images`→`images`**, **`main2.css`** uses **`url(../images/…)`**. |
| 2026-05 | **`/javascript/elolist.js`** returned **404** on staging though file existed — likely **blocked URL segment** `/javascript/`; moved script to **`/js/elolist.js`**, updated **`<script src>`** references sitewide. |
| 2026-05 | **`table-autopage:30`** substituted for **`:20`** on **ranked1–6**, **individual2/2a/2b/2c**, **individual3**, **server3**. (Other pages still use their original paging sizes where different.) |
| 2026-05 | **WinSCP** (installed). **Staging deploy** routine: synchronize local **`site/public_html`** to server **`public_html`**. Verified sorting + paging on **ratings.kickoff2.com**. |

*(Append concise rows; prune old noise.)*

---

## Deferred / blocked

- GitHub branch protection / enforced PRs — when collaborators land.
- **Schema changes + bulk imports + Amiga/offline datasets** until writable dev plumbing agreed with Steve.

---

## Quick facts

| Item | Value |
|------|--------|
| GitHub repo | https://github.com/DaghN/ratings.kickoff2 |
| Default branch | `main` |
| Staging SFTP host | **`ratings.kickoff2.com`**, port **`5322`**, user form **`dagh@ratings.kickoff2.com`** |
| Deploy | **WinSCP** — **Synchronize** local **`…\site\public_html`** → remote **`public_html`** |
| Legacy public reference | https://joshua.kickoff2.net/ratings/ |
| Local mirrored web root | **`site/public_html/`** |
| Server DB config location | **`../config/ko2unitydb_config.php`** vs `DOCUMENT_ROOT`, **not mirrored by default** |
| Local preview | **Laragon** junction → **`ratingskickoff.test`** |

---

## Agent hygiene

- After completing a slice: **one line** under Recent log; tweak **Current focus** / **Next** accordingly.
- **Never** paste secrets/SFTP/MySQL passwords in this doc or commits.
- Prefer relative asset paths that match Linux **case-sensitive** filesystems (`images/`, **`js/`** not **`javascript/`** for this stack).
