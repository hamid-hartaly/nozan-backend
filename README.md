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

## Diagnostics

To inspect invoice-payment route registration, run:

```bash
php artisan route:diagnose-invoice-payment
php artisan route:diagnose-api-registration-matrix
php artisan route:probe-invoice-payment-shapes
```

This checks whether `POST /api/finance/invoices/{invoiceId}/payments` is actually registered at runtime and whether the route text exists in `routes/api.php`, `routes/api_with_invoice_payment.php`, `routes/web.php`, or a combination of them.

Current runtime setup:

```bash
bootstrap/app.php -> routes/api_with_invoice_payment.php
```

The wrapper route file includes `routes/api.php` and adds the invoice payment endpoint under normal API middleware. This is the production-safe workaround for the file-persistence issue that prevented direct `routes/api.php` edits from becoming the runtime file seen by Laravel.

To probe the same behavior in a separate PHP process, run:

```bash
composer run probe:invoice-route
```

That standalone script boots temporary apps with temporary route files so you can compare route registration outside the currently running Artisan process.
\*\*\* Delete File: c:\Users\dell\nozan-service-system\backend\storage\framework\check_runtime_api_file.php
