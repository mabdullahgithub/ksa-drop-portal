# Test Scripts

This folder contains utility scripts for testing various features of the application.

## 📧 Email Testing Scripts

### setup_email_test.php
**Purpose**: Sets up email configuration for testing with Mailtrap

**Usage**:
```bash
php artisan tinker < tests/Scripts/setup_email_test.php
```

**What it does**:
- Creates default email settings with Mailtrap configuration
- Creates default user preferences
- Provides instructions for next steps

**Note**: Remember to update the Mailtrap credentials in the script before running.

---

### test_email.php
**Purpose**: Sends a test welcome email and displays comprehensive diagnostics

**Usage**:
```bash
php artisan tinker
```
Then paste the contents of this file.

**What it does**:
- Checks email system status
- Verifies SMTP settings
- Displays user email preferences
- Sends test welcome email
- Shows email log entry
- Provides troubleshooting tips

**Requirements**:
- Email settings configured
- Queue worker running (`php artisan queue:work`)
- At least one user in database

---

## 🔔 Notification Testing Scripts

### create_sample_notifications.php
**Purpose**: Creates sample notifications for testing the notification system

**Usage**:
```bash
php create_sample_notifications.php
```
Or:
```bash
php artisan tinker < tests/Scripts/create_sample_notifications.php
```

**What it creates**:
- Welcome notification
- Order status notifications (3 different statuses)
- Settings updated notification
- User created notification
- User updated notification

**Use case**: Testing the notification dropdown, pagination, and mark as read functionality.

---

### create_many_notifications.php
**Purpose**: Creates a large number of notifications for testing pagination and performance

**Usage**:
```bash
php create_many_notifications.php
```
Or:
```bash
php artisan tinker < tests/Scripts/create_many_notifications.php
```

**What it creates**:
- 50 notifications with various types
- Random statuses (read/unread)
- Different timestamps
- Different notification types (order, settings, user management)

**Use case**: Testing pagination (10 per page), infinite scroll, and notification list performance.

---

## 🚀 Quick Testing Workflow

### Test Email System (First Time Setup)

1. **Configure Email Settings**:
   ```bash
   # Edit setup_email_test.php with your Mailtrap credentials
   php artisan tinker < tests/Scripts/setup_email_test.php
   ```

2. **Start Queue Worker**:
   ```bash
   php artisan queue:work
   ```

3. **Send Test Email**:
   ```bash
   php artisan tinker
   # Paste contents of test_email.php
   ```

4. **Check Mailtrap Inbox**:
   - Should see welcome email
   - Check email styling and content

### Test Notification System

1. **Create Sample Notifications**:
   ```bash
   php artisan tinker < tests/Scripts/create_sample_notifications.php
   ```

2. **Test UI**:
   - Click notification bell icon
   - See 7 new notifications
   - Test mark as read
   - Test view all

3. **Test Pagination** (optional):
   ```bash
   php artisan tinker < tests/Scripts/create_many_notifications.php
   ```
   - Refresh page
   - Test pagination (50 notifications)
   - Test infinite scroll

---

## 📋 Script Details

| Script | Purpose | Creates | Time |
|--------|---------|---------|------|
| setup_email_test.php | Email config setup | 1 email_setting, 1 user_preference | 5s |
| test_email.php | Send test email | 1 email, 1 email_log | 2s |
| create_sample_notifications.php | Sample notifications | 7 notifications | 1s |
| create_many_notifications.php | Many notifications | 50 notifications | 5s |

---

## 🧪 Testing Checklist

### Email System:
- [ ] SMTP configured (via script or admin panel)
- [ ] Queue worker running
- [ ] Test email sent successfully
- [ ] Email appears in Mailtrap
- [ ] Email log created in database
- [ ] User preferences respected

### Notification System:
- [ ] Sample notifications created
- [ ] Notification bell shows count
- [ ] Dropdown displays notifications
- [ ] Mark as read works
- [ ] Pagination works (with 50+ notifications)
- [ ] "View all" navigates correctly

---

## 🔧 Troubleshooting

### Email not sending?
1. Check queue worker is running
2. Check email settings are active
3. Verify user preferences allow the email type
4. Check email logs for errors

### Notifications not appearing?
1. Clear browser cache
2. Check database for notifications
3. Verify user is logged in
4. Check notification preferences

### Scripts failing?
1. Ensure database is migrated
2. Check at least one user exists
3. Verify file permissions
4. Run with `php artisan tinker` for better error messages

---

## 📚 Related Documentation

- **[Email Quick Start](../../docs/QUICK_START.md)** - Email system setup guide
- **[Notifications Quick Start](../../docs/QUICKSTART_NOTIFICATIONS.md)** - Notification system guide
- **[Troubleshooting](../../docs/MAILING_QUICK_REFERENCE.md#troubleshooting)** - Common issues

---

## 💡 Tips

1. **Use Mailtrap** for email testing - Never test with real user emails
2. **Keep queue worker running** - Emails won't send without it
3. **Clear old test data** - Use `php artisan migrate:fresh --seed` to reset
4. **Check logs** - Email logs at `/admin/email-settings` (Logs tab)
5. **Test in stages** - Test email config, then preferences, then sending

---

**Happy Testing! 🚀**
