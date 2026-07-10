# Shopify App Store Submission Guide

**Status:** Ready for submission ✅  
**App Name:** KSA Drop Order Sync  
**Privacy Policy:** https://ksadrop.com/policies/privacy-policy.html  
**Support Email:** support@ksadrop.com

---

## Quick Checklist Before Submitting

- [x] Backend webhooks implemented (app/uninstalled, GDPR handlers)
- [x] Privacy policy published and accessible
- [x] Session token authentication working
- [x] Dashboard and settings page tested
- [x] Order filtering tested (all filter types)
- [x] CSP headers configured
- [ ] Screenshots captured (3+ desktop, 1280x720 min)
- [ ] Screencast video created (optional but recommended)
- [ ] Support email configured
- [ ] Privacy policy URL validated

---

## Step 1: Prepare App Listing Assets

### Screenshots (Required: 3 Desktop)

You need to capture **3-5 desktop screenshots** of your app in action. Use a test store with dummy orders.

#### Screenshot 1: Dashboard Overview
**What to show:**
- The stat cards showing: Total orders synced, Processed, Pending review, Skipped by filter
- Use realistic numbers if possible (can be edited/faked)

**How to capture:**
```bash
# Use a test store with seeded data
# Navigate to /embedded/shopify in Shopify Admin
# Take a 1280×720+ screenshot of the dashboard
```

#### Screenshot 2: Orders Chart
**What to show:**
- The 30-day orders-per-day area chart
- Stat cards above it

**How to capture:**
- Scroll down in the dashboard to show the chart
- Make sure the chart has some visible data (non-zero days)

#### Screenshot 3: Recent Orders Table
**What to show:**
- Recent orders table with columns: Order#, Customer, Payment, Status, Sync, Total
- Show a few diverse orders (different statuses, payment methods)

**How to capture:**
- Continue scrolling to show the table
- Make sure no real customer/payment data is visible

#### Screenshot 4: Settings Page (Optional)
**What to show:**
- Settings page with filter controls
- Status filters, tag filters, payment method toggle
- Shopify save bar at the top (if visible)

**How to capture:**
- Click "Settings" in sidebar navigation
- Show all three filter cards

### Screencast Video (Optional but Recommended)

Create a **2-3 minute YouTube video** (unlisted) demonstrating:
1. Opening the app in Shopify Admin
2. Viewing the dashboard with stats and chart
3. Configuring a filter (e.g., exclude test orders by tag)
4. Creating a test order in Shopify
5. Verifying the order appears in the dashboard
6. Showing the settings page and sync information

**Tool:** Use Loom (https://loom.com) or ScreenFlow (Mac) for quick video creation.

---

## Step 2: Update Privacy Policy

**Privacy Policy URL:** https://ksadrop.com/policies/privacy-policy.html

Verify that:
- [ ] The URL is publicly accessible
- [ ] It's served over HTTPS
- [ ] It covers:
  - What order data you collect
  - How you use it
  - How long you retain it
  - GDPR/CCPA compliance
  - Contact info for data requests

A template has been created at `public/policies/privacy-policy.html`. Update it with:
- Your actual company address
- Your support contact info
- Any company-specific details

---

## Step 3: Configure Support Channels

In Shopify Partners Dashboard:

1. **Support Email:** support@ksadrop.com (configure your actual support email)
2. **Support Portal:** https://ksadrop.com/support
3. **Support Phone:** +1-XXX-XXX-XXXX (optional)

Make sure these are monitored — Shopify may send review feedback or merchant issues to these channels.

---

## Step 4: Fill Out App Listing in Shopify Partners

Go to **Shopify Partners** → **Apps** → **Your App** → **App Listing**

### Section 1: Basic App Information
```
App name: KSA Drop Order Sync
Subtitle: Manage Shopify order sync without leaving Admin
App logo: [Use your KSA Drop logo]
App category: Order Management, Fulfillment
```

### Section 2: App Store Listing Content

**Introduction (required, 100 chars max):**
```
Sync orders from Shopify to KSA Drop with full control. Manage 
which orders sync to KSA Drop directly from Shopify Admin. Filter 
by order status, tags, and payment method. Monitor sync activity in real time.
```

**App Details (required, 500 chars max):**
```
KSA Drop Sync brings order management into Shopify Admin, 
eliminating the need to leave your store to control your fulfillment workflow.

**What it does:**
- Real-time order sync dashboard showing processed, pending, 
  and filtered orders
- Merchant-controlled filtering by financial/fulfillment status, 
  order tags, and payment method (COD vs prepaid)
- 30-day order trends chart to track sync activity
- One-click settings to customize which orders sync to your 
  KSA Drop account

Perfect for multi-channel sellers who need granular control over 
order routing without leaving Shopify.
```

**Features (required: min 3, max 5):**
1. Real-time order sync dashboard
2. Advanced order filtering (status, tags, payment)
3. 30-day activity trends chart
4. Settings management (customize sync behavior)

### Section 3: Pricing

```
Pricing model: Free
Plan name: Free
Description: Full access to order sync dashboard and filtering. 
All features included.
```

**Note:** If you want to charge later, select "My app requires custom setup."

### Section 4: Screenshots

Upload 3-5 desktop screenshots (captured in Step 1).

For each screenshot:
- **Image:** The screenshot file (1280×720 or larger)
- **Alt text:** Describe what the screenshot shows (e.g., "Dashboard showing order statistics and 30-day trend chart")

### Section 5: Support

```
Preferred support channel: Support email address
Support email: support@ksadrop.com
Support portal URL: https://ksadrop.com/support
Support phone: (optional)
```

### Section 6: Privacy Policy

```
Privacy policy URL: https://ksadrop.com/policies/privacy-policy.html
```

### Section 7: App Discovery Content

**Subtitle (52 chars max):**
```
Manage Shopify order sync without leaving Admin
```

**Search terms (1-5, required):**
```
1. order management
2. fulfillment
3. order sync
4. ksa drop
5. shopify integration
```

**App card subtitle:** (Short description for App Store listings)
```
Control which Shopify orders sync to KSA Drop with advanced 
filtering and real-time insights.
```

### Section 8: Testing Information

**Screencast URL (optional):**
```
https://youtu.be/[your-screencast-id] (unlisted YouTube video)
```

**Test account (optional but recommended):**
```
Store URL: https://usama-92130260.myshopify.com
Username: [test-merchant-email@example.com]
Password: [secure-test-password]
Instructions:
1. Log in to the Shopify store
2. Navigate to Apps → KSA Drop Order Sync
3. Click "Install" to authorize the app
4. Create 2-3 test orders with different statuses and tags
5. View the dashboard to see orders synced
6. Navigate to Settings and configure a filter (e.g., exclude "test" tag)
7. Create a new test order with the "test" tag and verify it's skipped
8. Create a test order without the tag and verify it syncs
9. Uninstall the app and verify the connection is cleanly disconnected
```

---

## Step 5: Submit for Review

1. Scroll to the bottom of the App Listing page
2. Click **"Submit for review"** (or "Request review")
3. Confirm the submission

**Expected timeline:**
- Initial review: 24-48 hours
- Questions/feedback: 1-3 days
- Approval (if all passes): 2-7 days from submission

---

## Step 6: Monitor Review & Respond to Feedback

While your app is under review:

1. **Check Partner Dashboard daily** for status updates
2. **Respond to any feedback promptly** (usually within 24 hours)
3. **Don't re-submit** unless explicitly asked

**Common feedback requests:**
- Screenshots: "Add alt text" → Go back and add descriptions
- Privacy Policy: "Clarify data retention" → Update your policy and relink
- Testing: "Can't reproduce feature" → Reply with detailed reproduction steps
- Security: "Verify GDPR compliance" → Confirm your webhooks are registered

---

## Step 7: After Approval

Once approved:

1. **App appears in Shopify App Store**
2. **Merchants can find and install** via the store link
3. **You can track installs** in the Partner Dashboard (Analytics → Installs)
4. **Monitor support channels** for merchant questions

### Ongoing Maintenance

- Monitor for errors in logs (logs visible in Partner Dashboard)
- Respond to support requests quickly
- Update privacy policy if data handling changes
- Test on fresh installs regularly (simulate merchant behavior)

---

## Troubleshooting

### App Won't Install
- **Check:** API credentials are correct in `config/services.shopify.php`
- **Check:** Scopes include `read_orders`, `read_products`, `write_orders`
- **Check:** OAuth redirect URI matches in both Partner Dashboard and `.env`

### Webhooks Not Firing
- **Check:** Webhooks are registered (Partner Dashboard → Webhooks)
- **Check:** HMAC verification is passing (look for errors in logs)
- **Check:** App URL is publicly accessible over HTTPS

### Session Token Validation Fails
- **Check:** App Bridge is loaded from Shopify CDN (not locally)
- **Check:** Session token validation middleware is active
- **Check:** API secret matches in `config/services.shopify.php`

### Orders Not Syncing
- **Check:** `orders/create` webhook is triggered (monitor logs)
- **Check:** `evaluateSyncFilters()` is passing (not returning `skipped_filtered`)
- **Check:** Orders exist in the test store

---

## Support

For app submission questions:
- **Shopify Support:** https://support.shopify.com/en/partners
- **Partner Community:** https://partners.shopify.com/community
- **Your Support Team:** support@ksadrop.com

For technical issues:
- Review logs at: `storage/logs/laravel.log`
- Check webhook status in Partner Dashboard
- Use `artisan` CLI to test locally: `php artisan tinker`

---

**You're ready!** Proceed to Shopify Partners and submit. 🚀
