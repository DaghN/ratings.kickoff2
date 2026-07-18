# Amiga ops secrets (deployable)

Staging `/config` is root-owned — Dagh SFTP cannot write there. Ops password for import/export/fixtures lives here so WinSCP can sync it with `public_html`.

- `amiga_ops_password.local.php` — gitignored; set `$password`
- `.htaccess` — denies HTTP access to this folder

Loader: `../includes/amiga_ops_password_lib.php`