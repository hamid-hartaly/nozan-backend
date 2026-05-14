# Auth API Contract

Base URL:

- Production: `https://nozanservice-main-mzzlod.free.laravel.cloud`
- Production: `https://nozanservice-main-mzzlod.laravel.cloud`
- Local: `http://127.0.0.1:8000`

All requests and responses use JSON.

## POST /api/auth/login

Request body:

```json
{
    "email": "hamid.hartaly@gmail.com",
    "password": "P@ssword123"
}
```

Successful response:

```json
{
    "token": "1|plain-text-token",
    "user": {
        "id": "1",
        "uid": "1",
        "name": "Ahmad",
        "full_name": "Ahmad",
        "email": "hamid.hartaly@gmail.com",
        "role": "staff",
        "is_active": true,
        "can_record_payment": false
    }
}
```

Validation failure response:

```json
{
    "message": "The provided credentials are incorrect.",
    "errors": {
        "email": ["The provided credentials are incorrect."]
    }
}
```

## GET /api/auth/me

Required header:

```text
Authorization: Bearer <token>
```

Successful response:

```json
{
    "user": {
        "id": "1",
        "uid": "1",
        "name": "Ahmad",
        "full_name": "Ahmad",
        "email": "hamid.hartaly@gmail.com",
        "role": "staff",
        "is_active": true,
        "can_record_payment": false
    }
}
```

Unauthorized response:

```json
{
    "message": "Unauthenticated."
}
```

## POST /api/auth/logout

Required header:

```text
Authorization: Bearer <token>
```

Successful response:

```json
{
    "message": "Signed out successfully."
}
```

## User payload notes

- `id` and `uid` are currently the same value.
- `role` is expected to be one of `admin`, `accountant`, or `staff` for internal users.
- `can_record_payment` is `true` only for `admin` and `accountant`.
- Frontend code should treat missing optional fields as absent rather than required.

## Change policy

- Do not rename fields in this payload without updating frontend types and tests.
- Any auth payload change should update `tests/Feature/AuthApiTest.php` and the frontend consumer types.
