# Hostinger deployment notes

## Upload layout

Upload the contents of this project folder into `public_html` for `mhally.com`.

This project includes a root `.htaccess` file that sends all web requests to Laravel's `public` directory while blocking direct browser access to folders such as `app`, `config`, `database`, `resources`, `routes`, and private storage logs.

## Required files

- Upload `.env`; it contains the production app URL and database connection.
- Upload `vendor` if Hostinger SSH/composer is not available.
- Upload `public`, `app`, `bootstrap`, `config`, `database`, `resources`, `routes`, and `storage`.

## Hostinger settings

- PHP version: use PHP 8.1 or another PHP 8.x version supported by this Laravel 8 install.
- Document root: `public_html`.
- Database host: `localhost` unless Hostinger shows a different MySQL host in hPanel.
- Make these folders writable by PHP:
  - `storage`
  - `storage/framework`
  - `storage/framework/cache`
  - `storage/framework/sessions`
  - `storage/framework/views`
  - `storage/logs`
  - `bootstrap/cache`

## After upload

If SSH is available, run these from `public_html`:

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:cache
```

If SSH is not available, upload the project after running the clear commands locally and make sure `bootstrap/cache` does not contain old cached config from another domain.

## Logs and browser errors

Production debug is off, so stack traces are not shown publicly. When a server error happens, the browser shows a reference ID. The matching details are written to:

```text
storage/logs/laravel-YYYY-MM-DD.log
```

The same error is also sent to PHP's error log through the `errorlog` channel, which can help when checking Hostinger logs.
