# Amiga ops secrets (deployable)

Staging `/config` is root-owned — Dagh SFTP cannot write there. Ops passwords for import/export/fixtures live here so WinSCP can sync them with `public_html`.

- `amiga_ops_password.local.php` — gitignored; set `$admin_password` and `$organizer_password`
- `.htaccess` — denies HTTP access to this folder

Loader: `../includes/amiga_ops_password_lib.php`

Roles:
- **admin** — DB import / export
- **organizer** — tournament fixture manager (admin password also works there)