# KSA Express Rider — phone app

The riders' app for Android and iPhone, built with [NativePHP for Mobile](https://nativephp.com/docs/mobile) (v4).

It is a thin shell. The screens are the portal's own rider app
(`../resources/js/features/rider-app`), shown in a full-screen web view, and
every screen calls the portal's `/rider/api/*`. Nothing from the portal's
backend or its `.env` is in this folder, because everything here ships inside
the app and can be read by anyone who unpacks it.

## How it fits together

| Piece | Where |
|---|---|
| Rider screens (shared with the web app) | `../resources/js/features/rider-app` |
| The seam between web app and phone app | `../resources/js/features/rider-app/lib/host.ts` |
| Phone app entry: who is signed in, token storage | `resources/js/native-app.tsx`, `resources/js/session.ts` |
| The one page the web view loads | `routes/web.php`, `resources/views/app.blade.php` |
| Portal address, app id, deep links | `.env` (see `.env.example`) |

Sign-in: the portal's web app is signed in by an HttpOnly cookie. The phone
app runs on its own origin (`http://127.0.0.1` on Android, `php://127.0.0.1`
on iPhone), so it sends `X-Rider-Client: native`, gets the device token in the
sign-in answer and sends it back as a Bearer token. See
`RiderAuthService::tokenFrom()` in the portal.

## Set up

Node packages come from the portal, one folder up: run `npm install` there
first. The only package installed here is axios, which NativePHP's Vite
plugin requires.

```bash
composer install
cp .env.example .env && php artisan key:generate
npm install
npm run build                       # copies icons from the portal, then builds
php artisan native:install          # android, ios or both
php artisan native:run              # on a connected phone or emulator
```

To try the shell in a desktop browser, point `RIDER_PORTAL_URL` at a local
portal (`http://127.0.0.1:8000`) and run `php artisan serve --port=8001`.

## What you need to build

- **Android:** Android Studio 2024.2.1 or later, on macOS, Windows or Linux.
- **iPhone:** an Apple Silicon Mac with Xcode 26 or later. An Intel Mac
  cannot build the iPhone app.

## After changing a rider screen

The web app and the phone app share the screens, so a change needs both:

1. In the portal: `npm run build`, commit `public/build` (the web app).
2. Here: `npm run build`, then a new app build and store release.

## Not done yet

The web view cannot use the phone's camera, location or file picker, so these
still need NativePHP plugins before the app can replace the web app:

- Scanning parcels: Scanner plugin (paid).
- Location on status updates: Geolocation plugin (paid).
- Proof and profile photos: Camera plugin (`nativephp/mobile-camera`, free).
- Sign-in links opening the app: `/.well-known/assetlinks.json` and
  `apple-app-site-association` on the portal, once the signing keys exist.
- App icon and splash screen artwork.
