CCAPDEV Group 8 S17

## PHP migration notes

This project now runs as a PHP application in XAMPP (Apache) with MySQL (phpMyAdmin) storage.

### Run

1. Put the project under your XAMPP `htdocs` folder.
2. Start Apache in XAMPP.
3. Start MySQL in XAMPP.
4. Open phpMyAdmin and run `sql/schema.sql`.
5. Open `http://localhost/lance/IT-PROG%20MP/`.

If your MySQL root account has a password, create `php/config.php` from `php/config.example.php` and set credentials.

### DB config (optional)

Defaults:

- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_NAME=lab_res_db`
- `DB_USER=root`
- `DB_PASS=` (empty)

You can override these in your environment variables.

Or set them in `php/config.php`.

### Troubleshooting

- `404 Not Found` on `http://localhost/lance/IT-PROG%20MP/`:
	- Restart Apache after updating `.htaccess`.
	- Ensure Apache `mod_rewrite` is enabled and `AllowOverride All` is allowed for `htdocs`.
- `SQLSTATE[HY000] [1045] Access denied`:
	- Update DB credentials in `php/config.php` (or env vars).

### Stack changes

1. `index.php` is now the app entry point and router.
2. Handlebars views were replaced by HTML templates rendered through PHP in `php/views`.
3. API and auth logic were moved from Express/Mongoose JS to PHP (`php/lib`).
4. Persistence is now MySQL tables (managed in phpMyAdmin).
5. `sql/schema.sql` creates all required tables.
6. `.htaccess` routes clean URLs to `index.php`.
