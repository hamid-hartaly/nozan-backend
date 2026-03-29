# Nozan Backend

Laravel 12 backend for the Nozan Service System. This app provides:

- Filament admin panel at `/admin/login`
- API authentication endpoints under `/api/auth/*`
- Sanctum token authentication for the Next.js frontend

## Local Development

Requirements:

- PHP 8.2+
- Composer
- Node.js 20+

Setup:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
npm install
npm run build
```

Run the app:

```bash
php artisan serve
```

## Production Environment Variables

Minimum required variables:

```env
APP_NAME="Nozan Backend"
APP_ENV=production
APP_KEY=base64:replace-me
APP_DEBUG=false
APP_URL=https://your-backend-domain
FRONTEND_URL=https://your-frontend-domain
FRONTEND_URL_WWW=https://www.your-frontend-domain

LOG_CHANNEL=stack
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nozan
DB_USERNAME=nozan
DB_PASSWORD=secret

SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_STORE=file
MAIL_MAILER=log
```

If your provider uses PostgreSQL, change `DB_CONNECTION` to `pgsql` and set the matching port.

## Laravel Cloud

Recommended build command:

```bash
composer install --no-dev --optimize-autoloader; npm install; npm run build
```

Recommended post-deploy command:

```bash
php artisan migrate --force
```

If the app returns `500 Server Error` before migrations or environment variables are fully configured, these defaults allow the app to boot without depending on database-backed session, cache, or queue tables.

After the first production deploy, create the Filament admin user:

```bash
php artisan make:filament-user --name "hamidhartaly" --email "hamid.hartaly@gmail.com" --password "H@mid1990" --panel admin --no-interaction
```

## Frontend Integration

The frontend expects this variable:

```env
NEXT_PUBLIC_API_BASE_URL=https://your-backend-domain
```

This value is read by the frontend auth client and server auth helpers.

If both apex and `www` frontend domains are active, set both `FRONTEND_URL` and `FRONTEND_URL_WWW` in Laravel Cloud so CORS accepts both origins.
