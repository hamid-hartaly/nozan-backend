# Nozan Backend

Laravel 12 + Filament 5 backend for Nozan Service Center.

## Included in this package

- Public landing page and operations preview
- Filament admin panel at `/admin`
- API auth endpoints at `/api/auth/*`
- Jobs API at `/api/jobs`
- Payment endpoint at `/api/jobs/{job}/payments`
- Customers, Jobs, Payments, and Inventory Filament resources
- Seed data for one admin user and demo records

## Quick start

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan optimize:clear
php artisan storage:link
php artisan serve
```

## Admin login

- Email: `hamid.hartaly@gmail.com`
- Password: `H@mid1990`

## API examples

### Create a job

```http
POST /api/jobs
Content-Type: application/json

{
  "customer_name": "Xalidq",
  "customer_phone": "07508389007",
  "tv_model": "Samsung 65\"",
  "category": "LED",
  "priority": "Urgent",
  "issue": "No display",
  "estimated_price_iqd": 75000
}
```

### List jobs

```http
GET /api/jobs
```

### Record payment

```http
POST /api/jobs/1/payments
Content-Type: application/json

{
  "amount_iqd": 25000,
  "method": "cash"
}
```
