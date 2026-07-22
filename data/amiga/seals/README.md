Place WinSCP'd staging seals here, e.g.:

  data/amiga/seals/seal-20260721-232103Z-manual/

Copy the whole folder from staging:

  .../amiga/_backups/seal-20260721-232103Z-manual/

Then run:

  powershell -ExecutionPolicy Bypass -File scripts\compare_ko2amiga_seal_to_work.ps1 -SealDir data\amiga\seals\seal-20260721-232103Z-manual

Imports into side DB ko2amiga_seal_cmp only — does not touch ko2amiga_work.