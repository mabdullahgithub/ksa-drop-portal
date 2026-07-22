# KSA Drop — Shopify App Test Guide

Complete guide to testing the Shopify integration: automated tests, manual
end-to-end flows, GDPR/compliance webhooks, security checks, and the testing
instructions to hand Shopify's App Review team.

> **Scope:** the embedded Order Sync app — OAuth install, embedded Admin UI
> (dashboard + settings), order sync (backfill + live webhooks), sync filters,
> manual-approval queue, and the mandatory GDPR webhooks.

---

## 0. Prerequisites & environment

### Required environment variables

```env
SHOPIFY_API_KEY=<partner app client id>
SHOPIFY_API_SECRET=<partner app client secret>
SHOPIFY_SCOPES=read_orders,read_customers        # NOTE: both scopes — see below
SHOPIFY_REDIRECT_URI=https://<your-host>/shopify/callback
APP_URL=https://<your-host>
KSADROP_PORTAL_URL=https://<your-portal-host>     # falls back to APP_URL
SHOPIFY_APP_STORE_URL=https://apps.shopify.com/<handle>
```

> ⚠️ **`.env.example` currently ships `SHOPIFY_SCOPES=read_orders` only.** The app
> reads customer name/email/phone for fulfillment, and `shopify.app.toml` declares
> `read_customers,read_orders`. Set the env var to **`read_orders,read_customers`**
> so the manually-built authorize URL matches the TOML grant.

### Services that must be running

| Service | Why | Command |
|---|---|---|
| Web server (HTTPS) | OAuth callback + embedded iframe need valid TLS | your deploy / `php artisan serve` + tunnel for local |
| **Queue worker** | Webhooks and the initial sync run as **queued jobs** (`QUEUE_CONNECTION=database`) — nothing syncs without it | `php artisan queue:work` |
| Database | connections + orders | migrated: `php artisan migrate` |

For local testing against a real dev store you need a public HTTPS tunnel
(e.g. Cloudflare Tunnel / ngrok) because Shopify must reach the callback and
webhook URLs.

---

## 1. Automated tests

### Run everything

```bash
php artisan test
```

### Run only the Shopify suite

```bash
php artisan test --filter=Shopify
```

Expected: **32 passing** (as of this guide).

### Coverage map

| File | Covers |
|---|---|
| `tests/Feature/ShopifyConnectFlowTest.php` | OAuth callback (logged-in client, no-session install, invalid state, HMAC), token storage, **claim** flow incl. `claim_token` verification, sync-mode update |
| `tests/Feature/ShopifyWebhookTest.php` | Webhook HMAC rejection, **shop-domain header/body cross-check**, `shop/redact` (delete + PII redaction), `app/uninstalled` (disconnect, keep orders) |
| `tests/Unit/ShopifyOauthHmacTest.php` | OAuth HMAC signing/verification incl. `%3D`-padded `host` param |

### What automated tests intentionally do **not** cover

Run these manually (Sections 2–4): live iframe rendering, App Bridge session
token minting, real GraphQL order fetch, Polaris UI, browser/device matrix.

---

## 2. Manual end-to-end — install & embedded app

> Do this on a **Shopify development store** with the app installed from a
> Shopify-owned surface (Partner Dashboard "Test your app", or the App Store
> listing once unlisted-published). Never start install by typing a shop domain
> into the portal — that path does not exist by design (requirement 2.3.1).

### 2.1 Fresh install (no portal session)

1. From the Partner Dashboard, click **Test your app** → **Install**.
2. **Expect:** immediate redirect to the Shopify OAuth grant screen (no app UI
   renders first). ✅ requirement 2.3.2
3. Approve the requested scopes (`read_orders`, `read_customers`).
4. **Expect:** redirect back into the embedded app at
   `admin.shopify.com/store/<store>/apps/<api-key>`. ✅ requirement 2.3.3
5. Because no KSA Drop client is linked yet, the embedded app shows the
   **onboarding / "connect your KSA Drop account"** screen (not an error).

**Verify in DB:** a `client_shopify_connections` row exists with
`client_id = NULL`, `status = active`, and an encrypted `access_token`.

### 2.2 Link the store (claim flow)

1. In the onboarding screen, follow the link to the KSA Drop portal and log in
   as a client.
2. Complete the connect action.
3. **Expect:** the store links to your client, and the initial 60-day order
   backfill dispatches (needs the queue worker running).

**Verify:** the connection row now has your `client_id`; recent orders appear
under `source = shopify`.

**Security check (IDOR):** the claim requires a `claim_token` minted from a
valid App Bridge session for that exact shop — posting a bare shop domain to
`/portal/api/shopify/claim` must fail with 422.

### 2.3 Embedded dashboard

Open the app inside Shopify Admin. Confirm:

- [ ] Stat cards: processed / pending review / skipped / dismissed / total
- [ ] 30-day orders-per-day chart renders (zero-filled days included)
- [ ] Recent orders table (last 10)
- [ ] `last_synced_at` shows a recent time
- [ ] No console errors; no third-party-cookie warnings (test in **incognito**,
      Chrome) ✅ requirement 1.1.1

### 2.4 Settings (sync filters)

In the embedded Settings page:

- [ ] Change **financial status** filter, save → Shopify save bar appears/clears
- [ ] Change **fulfillment status** filter, save
- [ ] Add **tag include** / **tag exclude** values, save
- [ ] Change **payment method** (all / COD / prepaid), save
- [ ] Reload → saved values persist
- [ ] Invalid values are rejected (validation) — e.g. an unknown status

---

## 3. Manual end-to-end — order sync behavior

Filters are **forward-only**: they apply to orders created *after* the filter is
set; changing a filter never re-evaluates existing orders.

### 3.1 Live webhook sync

1. In the dev store, create a test order (Orders → Create order).
2. With the queue worker running, **expect** the order to appear in the KSA Drop
   portal / embedded dashboard within seconds.
3. Update the order (mark paid, fulfill) → order updates in KSA Drop.
4. Cancel the order → status becomes `cancelled`.

### 3.2 Filter behavior

| Set filter | Create an order that… | Expected `shopify_sync_status` |
|---|---|---|
| financial = `paid` only | is `pending` | `skipped_filtered` |
| tag include = `vip` | has no `vip` tag | `skipped_filtered` |
| tag exclude = `wholesale` | has `wholesale` tag | `skipped_filtered` |
| payment = `cod` | is prepaid | `skipped_filtered` |
| (no filters) | any | `null` (auto) or `pending_review` (manual mode) |

### 3.3 Sync modes

- **auto_sync:** passing orders become visible immediately (`null` status).
- **manual_approval:** passing orders land in the pending-review queue.
  - Approve one → becomes visible (`approved`).
  - Approve in bulk → all selected become visible.
  - Dismiss one → hidden everywhere (`dismissed`), never imported.
  - **Security check:** approving/dismissing an order id belonging to another
    client returns 404 (queries are scoped to the acting client).

---

## 4. GDPR / compliance webhook testing

Shopify's review **actively sends** these. All are HMAC-verified and, for topics
whose body carries the shop domain, the body must match the
`X-Shopify-Shop-Domain` header.

### 4.1 Generate a valid signature (bash)

```bash
SECRET='your-api-secret'
BODY='{"shop_domain":"teststore.myshopify.com"}'
HMAC=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "$SECRET" -binary | base64)
echo "$HMAC"
```

### 4.2 `shop/redact`

```bash
curl -i -X POST https://<host>/webhooks/shopify \
  -H "Content-Type: application/json" \
  -H "X-Shopify-Topic: shop/redact" \
  -H "X-Shopify-Shop-Domain: teststore.myshopify.com" \
  -H "X-Shopify-Hmac-Sha256: $HMAC" \
  -d "$BODY"
```

- **Expect:** `200 OK`. Job (queue) deletes the connection + all its tokens and
  redacts PII on the shop's synced orders (totals/line items kept).

### 4.3 `customers/redact` and `customers/data_request`

Same signing pattern with the appropriate body:

```json
{ "shop_domain":"teststore.myshopify.com", "customer":{"id":123,"email":"x@y.com"}, "orders_to_redact":[5001] }
```

- `customers/redact` → PII on the listed order ids is nulled/`[redacted]`.
- `customers/data_request` → logged (operator responds within 30 days).

### 4.4 `app/uninstalled`

Body must include `myshopify_domain`. **Expect:** connection set to
`disconnected`, tokens cleared, **order history preserved**.

### 4.5 Negative tests (must all fail)

| Test | Expected |
|---|---|
| Wrong/absent HMAC | `401 Unauthorized` |
| Valid HMAC, but body `shop_domain` ≠ header | `401 Shop mismatch` |
| Malformed JSON body | still HMAC-checked; no crash |

---

## 5. Security test checklist

- [ ] **Webhook HMAC** — forged signature → 401 (§4.5)
- [ ] **Webhook retargeting** — signed body for shop A + header shop B → 401
- [ ] **Session token** — call `/embedded/shopify/api/dashboard` with no / expired
      / wrong-`aud` Bearer JWT → 401
- [ ] **Session token happy path** — valid App Bridge token → 200
- [ ] **Claim IDOR** — claim without a valid `claim_token` → 422
- [ ] **OAuth state** — tampered `state` on the callback → redirected out with an
      error, no token exchange
- [ ] **OAuth HMAC** — tampered query params → rejected
- [ ] **SSRF** — a non-`*.myshopify.com` shop value is rejected before any
      outbound call (verify via unit test / code path, not live)
- [ ] **Token exposure** — connection JSON never includes `access_token` /
      `refresh_token`; DB stores them encrypted
- [ ] **CSRF** — only `webhooks/*` and `embedded/shopify/api/*` are exempt

---

## 6. Browser / device matrix

Test the embedded app in Shopify's supported set:

- [ ] Chrome (incl. **incognito** — no third-party cookies) desktop
- [ ] Safari desktop
- [ ] Firefox desktop
- [ ] Edge desktop
- [ ] Mobile (Shopify mobile app webview / responsive)

Confirm: dashboard + settings render, save bar works, no console errors, no
horizontal overflow.

---

## 7. Testing instructions for Shopify App Review

Paste this (updated with real values) into the Partner Dashboard testing
instructions — requirements 4.5.4 / 4.5.5.

```
TEST STORE
  Store: <your-dev-store>.myshopify.com
  The app is already installed on this store.

KSA DROP PORTAL LOGIN (full-access test account)
  URL:      https://portal.ksadrop.com/login
  Email:    <reviewer test email>
  Password: <reviewer test password>

HOW TO TEST
1. Install/open the app from the store's Apps section — it opens embedded in
   Admin. (Install begins OAuth immediately; no UI before authorization.)
2. On first open the app shows an onboarding screen. Click through to the KSA
   Drop portal and log in with the credentials above to link the store.
3. Return to the embedded app: the Dashboard shows sync stats, a 30-day orders
   chart, and recent orders.
4. Open Settings to configure order sync filters (status / tags / payment
   method) and sync mode (automatic vs manual approval). Save.
5. Create a test order in the store; with sync active it appears on the
   Dashboard. Filter behavior is forward-only (applies to new orders).

GDPR
  All mandatory compliance webhooks (shop/redact, customers/redact,
  customers/data_request) are implemented at POST /webhooks/shopify and are
  HMAC-verified.

NOTES
  - App is free; no billing.
  - Scopes: read_orders, read_customers (order + customer data for fulfillment).
```

Also attach: a **2–3 min screencast** (English or English subtitles) walking
through install → link → dashboard → filters → a synced order.

---

## 8. Troubleshooting

| Symptom | Likely cause | Check |
|---|---|---|
| Order never syncs | queue worker not running | `php artisan queue:work`; inspect `failed_jobs` |
| Backfill empty | app not approved for **Protected Customer Data** → `ACCESS_DENIED`; connection flips to `status = error` | Partner Dashboard → API access; then re-run webhook registration (`/portal/api/shopify/retry-webhooks`) |
| Embedded app blank | App Bridge / API key | `<meta name="shopify-api-key">` set; `app-bridge.js` loads first; check console |
| 401 on embedded API | session token invalid/expired | token `aud` must equal `SHOPIFY_API_KEY`; clocks in sync |
| OAuth loops or errors | redirect URI mismatch | `SHOPIFY_REDIRECT_URI` must exactly match an Allowed redirection URL in the Partner Dashboard |
| Webhook HMAC fails | wrong secret | `SHOPIFY_API_SECRET` matches the app; body signed raw (unmodified) |
| `shop mismatch` 401 | header/body shop differ | expected for forged/retargeted webhooks; benign for legitimate Shopify traffic |

Logs: `storage/logs/laravel.log` (search `Shopify`).
