# PG-NNN — [short title]

**Register:** [post-game-register.md](../post-game-register.md) · **Status:** draft | ready for Steve | deployed  
**Feature:** [link or one line]

---

## Summary

[One sentence: what happens after each rated game once this is live.]

---

## Anchor in `docs/ratings_cpp.txt`

- **Function:** `RatingProcedureUnity` (or other: _____)
- **Place in flow:** [e.g. step 5 — after `INSERT` into `ratedresults`, before first `UPDATE playertable` for player A]

---

## Insert instruction (for Steve)

[Plain language: add / replace / call from existing block. Name surrounding variables if known from excerpt.]

---

## C++ snippet

```cpp
// PG-NNN — [title]
// Paste into: [location]

// [your code — use real column names from docs/playertable-schema.md / ratedresults-schema.md]
```

---

## Data contract

| Table | Column | Read / write | Notes |
|-------|--------|--------------|-------|
| | | | |

---

## Replay mirror (Python)

- **File(s):** `scripts/ladder/…`
- **Parity:** [same formula / deferred / N/A]

---

## Smoke check (optional)

| Input | Expected |
|-------|----------|
| idA, idB, goalsA, goalsB | [fields on `ratedresults` / `playertable`] |

---

## Changelog

| Date | Who | Note |
|------|-----|------|
| | | Created |
