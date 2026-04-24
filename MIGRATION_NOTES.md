# Muhalli Laravel Admin Migration Notes

This Laravel project replaces the old simple PHP admin/API panel while keeping the Android app's legacy API contract:

```text
/api/index.php?endpoint=buyer/categories
/api/index.php?endpoint=supplier/products
```

That means the Android app can keep using:

```text
https://hiskytechs.com/muhali/api/index.php
```

as long as the production `/muhali` web root now points to this Laravel project's `public` directory.

## Production Setup

1. Upload this Laravel project to the server.
2. Point the hosting document root to:

```text
Muhalli Laravel Admin/public
```

3. Set `.env` for production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hiskytechs.com/muhali

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_live_database
DB_USERNAME=your_live_user
DB_PASSWORD=your_live_password
```

4. Install optimized dependencies:

```bash
composer install --no-dev --optimize-autoloader
```

5. Run migrations:

```bash
php artisan migrate --force
```

6. Cache production config/routes:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Important

- Do not expose the Laravel project root publicly. Only `public` should be web-accessible.
- Keep the existing production database; the migration upgrades it in place and adds missing columns such as offer pricing, maximum offer quantity, and buyer/supplier latitude/longitude.
- If production still serves the old PHP folder at `/muhali`, the Android app will keep hitting PHP. To fully shift to Laravel, `/muhali` must serve this Laravel `public` directory.
- The app's API URL remains compatible with Laravel because this project implements `api/index.php`.
