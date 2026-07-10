# KSA Drop Shopify App Submission Checklist

## Pre-Submission Verification

- [x] `app/uninstalled` webhook handler implemented (disconnects connection, preserves order history)
- [x] `shop/redact` webhook handler implemented (GDPR 48h post-uninstall)
- [x] `customers/redact` webhook handler implemented (customer PII redaction)
- [x] `customers/data_request` webhook handler implemented (logging)
- [x] Session token verification (HS256 HMAC validation)
- [x] CSP frame-ancestors header for iframe embedding
- [x] Sync filters working (status, tags, payment method)
- [x] Dashboard with orders-per-day chart
- [ ] Privacy policy published at public URL
- [ ] Support email configured (support@ksadrop.com)

## Shopify Partner Dashboard Setup

### Basic App Information
- **App name:** KSA Drop Order Sync
- **App type:** Public (unlisted initially)
- **Distribution:** Public distribution
- **API credentials:** Already set up
- **App URL:** `https://ksa-drop-portal.test/embedded/shopify` (configure during submission)

### Required Scopes (already configured)
```
read_orders
read_products
write_orders
```

### Webhooks to Register (via GraphQL Admin API)
The app auto-registers these on first connection. Verify in Partner Dashboard:

```
1. orders/create    → /webhooks/shopify
2. orders/updated   → /webhooks/shopify
3. orders/paid      → /webhooks/shopify
4. orders/cancelled → /webhooks/shopify
5. app/uninstalled  → /webhooks/shopify (NEW)
6. customers/data_request → /webhooks/shopify (GDPR)
7. customers/redact → /webhooks/shopify (GDPR)
8. shop/redact      → /webhooks/shopify (GDPR)
```

## Submission Materials

### 1. App Listing Content (fill in Partner Dashboard)

**App name:** KSA Drop Order Sync

**Subtitle (52 chars max):**
"Manage Shopify order sync without leaving Admin"

**App introduction (required):**
Sync orders from Shopify to KSA Drop with full control. Manage which orders sync to KSA Drop directly from Shopify Admin. Filter by order status, tags, and payment method. Monitor sync activity in real time.

**App details (500 chars max):**
KSA Drop Sync brings order management into Shopify Admin, eliminating the need to leave your store to control your fulfillment workflow.

**What it does:**
- Real-time order sync dashboard showing processed, pending, and filtered orders
- Merchant-controlled filtering by financial/fulfillment status, order tags, and payment method (COD vs prepaid)
- 30-day order trends chart to track sync activity
- One-click settings to customize which orders sync to your KSA Drop account

Perfect for multi-channel sellers who need granular control over order routing without leaving Shopify.

**Features (minimum 3, maximum 5):**
1. Real-time order sync dashboard — View total orders synced, processed counts, pending review items, and orders filtered by your custom rules all in one place.
2. Advanced order filtering — Filter by financial status (paid/pending), fulfillment status (fulfilled/unfulfilled), Shopify tags (include/exclude), and payment method.
3. 30-day activity trends — Visualize order sync patterns over time with an interactive chart to identify peaks and manage capacity.
4. Settings management — Save and manage your sync filter preferences directly in Shopify Admin without leaving your store.

**Pricing:**
- Plan type: Free
- Description: Full access to order sync dashboard and filtering. All features included.

**Support (required):**
- Support email: support@ksadrop.com
- Support portal URL: https://ksadrop.com/support
- Support phone: +1-XXX-XXX-XXXX (optional)

**App discovery content:**
- Subtitle: Manage Shopify order sync without leaving Admin
- Search terms: order management, fulfillment, order sync, ksa drop, shopify integration
- Meta description: Control which Shopify orders sync to KSA Drop with advanced filtering and real-time insights.

**Privacy policy:**
https://ksadrop.com/policies/privacy-policy

**Categories:**
- Order Management
- Fulfillment

### 2. Screenshots (required: at least 3 valid desktop screenshots)

**Desktop Screenshots to capture:**
1. Dashboard overview — stat cards showing total/processed/pending/skipped counts
2. Dashboard with chart — orders-per-day area chart visible
3. Settings page — status/tag/payment method filters shown
4. Recent orders table — showing order number, customer, payment method, status, sync badge

**Mobile Screenshots (optional but recommended):**
- Dashboard on mobile view (responsive design)
- Settings page mobile view

**Screenshot requirements:**
- Don't display personally identifiable information (real store/customer data)
- Use test/demo orders only
- Include the app UI prominently
- Crop to focus on the app, not browser chrome
- At least 1280x720 resolution recommended

### 3. Test Account Setup (optional but recommended)

Provide Shopify with test account access so reviewers can test the app:
- **Store URL:** https://usama-92130260.myshopify.com (or your dev store)
- **Username:** [provide test merchant account]
- **Password:** [provide test merchant password]
- **Instructions:** 
  1. Log in to the Shopify store
  2. Navigate to Apps → KSA Drop Order Sync
  3. Click "Install" (or the app will auto-install on your dev store)
  4. Create 2-3 test orders with different statuses
  5. View the dashboard to see orders synced
  6. Configure filters in Settings and verify filtering works
  7. Uninstall the app and verify it disconnects gracefully

### 4. Screencast URL (optional but helps reviewers)

Create a 2-3 minute screencast demonstrating:
- Installing the app
- Viewing the dashboard (stat cards + chart)
- Configuring filters (status, tags, payment method)
- Creating a test order and seeing it sync
- Checking that filtered orders are skipped

Upload to YouTube (unlisted) and provide URL.

## Launch Readiness Checklist

### Code & Security
- [x] Session token validation (HMAC HS256)
- [x] CSRF exemption for `/embedded/shopify/api/*` routes
- [x] CSP frame-ancestors header set
- [x] No hardcoded secrets in code
- [x] Webhook HMAC verification
- [x] GDPR webhooks implemented
- [x] app/uninstalled handler implemented

### Privacy & Compliance
- [ ] Privacy policy published and linked
- [ ] GDPR data retention policy documented
- [ ] Support email/portal configured
- [ ] Terms of Service (if applicable)

### Testing
- [ ] Test on fresh dev store
- [ ] Test order sync with various statuses
- [ ] Test all filter types
- [ ] Test reinstall behavior
- [ ] Test on mobile view
- [ ] Verify dashboard loads without errors
- [ ] Verify settings save/load correctly

### Documentation
- [ ] Privacy policy link in listing
- [ ] Support instructions in Partner Dashboard
- [ ] FAQ/knowledge base articles (optional)

## Steps to Submit

1. **Go to Shopify Partners:** https://partners.shopify.com
2. **Select your app:** Apps → Your App Name
3. **Click "App listing":** Fill in all required fields (see "Submission Materials" above)
4. **Upload screenshots:** At least 3 desktop screenshots, alt text required
5. **Configure app URL:** Set to https://ksa-drop-portal.test/embedded/shopify (will be your production domain)
6. **Set pricing:** Select Free plan
7. **Add support info:** Email + portal URL
8. **Add privacy policy URL:** https://ksadrop.com/policies/privacy-policy
9. **Review & submit:** Shopify team will review within ~48 hours

## After Submission

### Shopify Review
- Shopify's team will install the app on a test store
- They'll verify functionality, security, and GDPR compliance
- They may ask for additional information (usually ~2-3 days)

### If Rejected
- Review feedback in Partner Dashboard
- Address issues
- Resubmit (no limit on resubmissions)

### If Approved
- App will appear in the Shopify App Store
- Merchants can install via the store link
- You can track installs in Partner Dashboard

## Shopify API Webhooks (Auto-Registered)

Your app should register these webhooks via GraphQL API (or manually in Partner Dashboard):

```graphql
mutation RegisterWebhook($topic: WebhookSubscriptionTopic!, $url: URL!) {
  webhookSubscriptionCreate(
    topic: $topic
    webhookSubscription: { callbackUrl: $url, format: JSON }
  ) {
    webhookSubscription {
      id
      topic
      format
      callbackUrl
    }
    userErrors {
      field
      message
    }
  }
}
```

## Scopes Required

Ensure these scopes are requested in your app config:

```
read_orders      — View order data
read_products    — View product data (for filtering context)
write_orders     — Update order status (not currently used, but good to have)
```

## Known Limitations (v1)

1. **Filters are forward-only** — changing filters doesn't re-evaluate existing orders
2. **No payment_method backfill** — only new orders get normalized payment method
3. **No historical tag backfill** — only new orders get shopify_raw_tags populated
4. **Test account access** — Shopify may request access during review

## Support & Escalation

If you encounter issues during submission:
1. Check Shopify Partner Dashboard for feedback
2. Review app logs for webhook failures
3. Verify webhooks are registered: Settings → Webhooks
4. Test with a fresh install on a new dev store
5. Contact Shopify support for review-specific questions
