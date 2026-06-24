# Self-hosted font files

WOFF2 files in this directory are served from this site (not Google Fonts CDN).

| Family | Weights | License |
|--------|---------|---------|
| Exo 2 | 500, 600, 700 | [SIL Open Font License 1.1](https://scripts.sil.org/OFL) |
| IBM Plex Sans | 400, 500, 600, 700 | [SIL Open Font License 1.1](https://scripts.sil.org/OFL) |
| IBM Plex Mono | 400, 500 | [SIL Open Font License 1.1](https://scripts.sil.org/OFL) |
| DSEG7 Classic | 400 | [SIL Open Font License 1.1](https://scripts.sil.org/OFL) — [keshikan/DSEG](https://github.com/keshikan/DSEG) v0.46 |

Fetched via `scripts/sync_self_hosted_fonts.ps1` from [@fontsource](https://fontsource.org/) packages (latin subset).

Regenerate after changing weights:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/sync_self_hosted_fonts.ps1
```
