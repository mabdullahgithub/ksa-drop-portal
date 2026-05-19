# Tests

This folder contains all testing files for the application.

## 📁 Structure

```
tests/
├── Feature/               # Feature tests (HTTP, integration tests)
├── Unit/                  # Unit tests (individual class/method tests)
├── Scripts/              # Utility scripts for manual testing
│   ├── README.md         # Scripts documentation
│   ├── setup_email_test.php
│   ├── test_email.php
│   ├── create_sample_notifications.php
│   └── create_many_notifications.php
└── TestCase.php          # Base test case class
```

---

## 🧪 Running Tests

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Suite
```bash
# Feature tests only
php artisan test --testsuite=Feature

# Unit tests only
php artisan test --testsuite=Unit
```

### Run Specific Test File
```bash
php artisan test tests/Feature/ExampleTest.php
```

### Run with Coverage
```bash
php artisan test --coverage
```

---

## 📜 Manual Testing Scripts

The `Scripts/` folder contains utility scripts for manual testing:

### Email Testing
- **[setup_email_test.php](Scripts/setup_email_test.php)** - Configure email settings
- **[test_email.php](Scripts/test_email.php)** - Send test email with diagnostics

### Notification Testing
- **[create_sample_notifications.php](Scripts/create_sample_notifications.php)** - Create sample notifications (7)
- **[create_many_notifications.php](Scripts/create_many_notifications.php)** - Create many notifications (50)

📖 **[View Scripts Documentation](Scripts/README.md)** for detailed usage instructions.

---

## ✅ Test Categories

### Feature Tests (`Feature/`)
Test complete user workflows and HTTP requests:
- Authentication flows
- User CRUD operations
- Email sending workflows
- Notification creation
- Admin panel access
- API endpoints

### Unit Tests (`Unit/`)
Test individual components in isolation:
- Model methods
- Service classes
- Helper functions
- Utility classes

---

## 🔧 Writing Tests

### Feature Test Example
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class EmailSettingsTest extends TestCase
{
    public function test_admin_can_access_email_settings()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)
            ->get('/admin/email-settings');

        $response->assertOk();
    }
}
```

### Unit Test Example
```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\EmailService;
use App\Models\User;

class EmailServiceTest extends TestCase
{
    public function test_can_check_if_email_system_is_enabled()
    {
        $service = new EmailService();
        
        $this->assertIsBool($service->isEnabled());
    }
}
```

---

## 🎯 Testing Checklist

### Before Running Tests:
- [ ] Database is configured (test database recommended)
- [ ] Dependencies installed (`composer install`)
- [ ] `.env.testing` configured (optional)
- [ ] Database migrated

### What to Test:
- [ ] Authentication flows
- [ ] User management (CRUD)
- [ ] Email sending
- [ ] Notification creation
- [ ] Permission checks
- [ ] Admin panel access
- [ ] API endpoints
- [ ] Form validation

---

## 🚀 Quick Testing Workflow

### 1. Setup Test Environment
```bash
# Copy environment
cp .env .env.testing

# Update to use test database
# Edit .env.testing: DB_DATABASE=database_test.sqlite

# Run migrations
php artisan migrate --env=testing
```

### 2. Run Automated Tests
```bash
php artisan test
```

### 3. Manual Testing
```bash
# Email system
php artisan tinker < tests/Scripts/setup_email_test.php

# Notifications
php artisan tinker < tests/Scripts/create_sample_notifications.php
```

---

## 📊 Test Coverage

### Current Coverage Areas:
- ✅ Basic authentication tests (Laravel default)
- ✅ Manual email testing scripts
- ✅ Manual notification testing scripts

### Areas to Add Tests:
- [ ] Email sending workflows
- [ ] Notification creation
- [ ] User management CRUD
- [ ] Role & permission checks
- [ ] Admin panel features
- [ ] Email preference updates
- [ ] SMTP configuration

---

## 🐛 Testing Tips

1. **Use Factories** - Create test data with factories, not manual creation
2. **Isolate Tests** - Each test should be independent
3. **Test Permissions** - Verify authorization for protected routes
4. **Mock External Services** - Don't send real emails in tests
5. **Use Transactions** - Tests should not affect database permanently
6. **Test Edge Cases** - Don't just test happy paths

---

## 📚 Resources

- **[Laravel Testing Documentation](https://laravel.com/docs/testing)** - Official docs
- **[PHPUnit Documentation](https://phpunit.de/documentation.html)** - Test framework
- **[Laravel Dusk](https://laravel.com/docs/dusk)** - Browser testing (if needed)

### Project Documentation:
- **[Email Testing Guide](../docs/QUICK_START.md)** - Email system testing
- **[Notification Testing](../docs/QUICKSTART_NOTIFICATIONS.md)** - Notification testing
- **[Scripts Documentation](Scripts/README.md)** - Manual testing scripts

---

## 🔄 Continuous Integration

### GitHub Actions Example
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install
      - run: php artisan test
```

---

## 💡 Best Practices

1. **Write Tests First** (TDD) - Define expected behavior before implementing
2. **Keep Tests Fast** - Fast tests get run more often
3. **Test One Thing** - Each test should verify one behavior
4. **Use Descriptive Names** - Test names should describe what they verify
5. **Arrange-Act-Assert** - Structure tests clearly (Given-When-Then)

---

**Happy Testing! 🧪**
