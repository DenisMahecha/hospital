# Production deployment checklist

## HTTPS on XAMPP/Apache

1. Create or install a trusted certificate for the hospital hostname.
2. Enable `ssl_module` and `socache_shmcb_module` in `apache\conf\httpd.conf`.
3. Enable `httpd-ssl.conf` and configure the certificate and key paths.
4. Point the SSL virtual host document root to `C:/xampp/htdocs/hospital/public`.
5. Redirect port 80 to 443 after confirming the HTTPS virtual host works.
6. Set `APP_URL=https://hospital.example` and `SESSION_SECURE_COOKIE=true` in `.env`.

Do not enable `SESSION_SECURE_COOKIE=true` while testing on plain `http://localhost`, or the browser will not send the session cookie.

## Mail and 2FA

Set real SMTP values in `.env` before enabling production 2FA:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-account
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=security@hospital.example
MAIL_FROM_NAME="MediCore Hospital"
HMS_REQUIRE_TWO_FACTOR=true
HMS_LOGIN_MAX_ATTEMPTS=5
HMS_LOCKOUT_MINUTES=15
SESSION_LIFETIME=30
```

Production config automatically requires 2FA for super-admin and doctor roles. The local environment is intentionally left configurable so existing local sessions are not unexpectedly locked behind an unavailable mail server.

## Storage and database

```powershell
php artisan storage:link
php artisan migrate --force
php artisan config:clear
```

Radiology uploads are stored under `storage/app/public/radiology` and exposed through `public/storage`.

## DICOM

The system validates and stores `.dcm` files and provides an attachment link. Browser-native DICOM rendering is not included yet; add a Cornerstone/OHIF viewer when the hospital has a DICOM/PACS endpoint and access policy. Do not expose raw DICOM files publicly without authentication and patient-access controls.
