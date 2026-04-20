# E2E Tests Directory

This directory contains Playwright end-to-end tests that test real user workflows through the browser.

## Quick Start

```bash
# Run all e2e tests
npm run e2e

# Run with interactive UI (debug mode)
npm run e2e:ui

# Run with visible browser window
npm run e2e:headed

# Run specific test file
npm run e2e -- tests/e2e/auth/login.spec.ts

# Run tests in folder
npm run e2e -- tests/e2e/auth/
```

## Directory Structure

```
e2e/
├── auth/                    # Authentication and login tests
│   ├── login.spec.ts       # Login page and user authentication
│   ├── login-behavior.spec.ts  # Login flows and validation
│   └── pages.spec.ts       # Auth pages (forgot password, reset, OTP)
│
├── app/                     # Application feature tests
│   ├── navigation.spec.ts  # Navigation, menus, layout
│   ├── dashboard.spec.ts   # Dashboard rendering (planned)
│   └── crud/               # CRUD operation tests for resources
│       ├── TEMPLATE.spec.ts    # Template for creating CRUD tests
│       ├── tags.spec.ts        # Tags CRUD (planned)
│       └── [resource].spec.ts  # Other resources (planned)
│
├── helpers/                 # Reusable test utilities
│   ├── auth.ts             # Login/logout helpers
│   ├── navigation.ts       # Navigation helpers
│   └── crud.ts             # CRUD operation helpers
│
├── global.setup.ts         # Setup before all tests (DB migration)
└── healthcheck.spec.ts     # Basic health check test
```

## Helper Functions

All helpers are designed for reusability and to keep tests DRY.

### Authentication (`helpers/auth.ts`)
```typescript
import { loginAsUser, logout, isLoggedIn } from '../helpers/auth';

// Login with test credentials
await loginAsUser(page);

// Logout
await logout(page);

// Check login status
const loggedIn = await isLoggedIn(page);
```

### Navigation (`helpers/navigation.ts`)
```typescript
import { navigateTo, navigateToCrudList } from '../helpers/navigation';

// Navigate to path
await navigateTo(page, '/app');

// Navigate to CRUD list
await navigateToCrudList(page, 'users');
```

### CRUD Operations (`helpers/crud.ts`)
```typescript
import { fillAndSubmitForm, findEntityInList } from '../helpers/crud';

// Fill and submit a form
await fillAndSubmitForm(page, { name: 'Test' });

// Find item in list
const found = await findEntityInList(page, 'Test');
```

See [E2E Testing Guide](../../doc/E2E_TESTING_GUIDE.md) for complete helper documentation.

## Writing Tests

### Basic Pattern

```typescript
import { expect, test } from '@playwright/test';
import { loginAsUser } from '../helpers/auth';

test.describe('Feature Name', () => {
    test.beforeEach(async ({ page }) => {
        // Login before each test
        await loginAsUser(page);
    });

    test('user can perform action', async ({ page }) => {
        // Navigate
        await page.goto('/app/resource');

        // Perform action
        await page.locator('button:has-text("Action")').click();

        // Verify
        await expect(page.locator('text=Success')).toBeVisible();
    });
});
```

### Using Helpers

```typescript
import { loginAsUser } from '../helpers/auth';
import { navigateToCrudList } from '../helpers/navigation';
import { fillAndSubmitForm, findEntityInList } from '../helpers/crud';

test('user can create and find item', async ({ page }) => {
    await loginAsUser(page);
    await navigateToCrudList(page, 'tags');

    // Click create
    await page.locator('button:has-text("Create")').click();

    // Fill form
    await fillAndSubmitForm(page, { name: 'New Tag' });

    // Verify
    const found = await findEntityInList(page, 'New Tag');
    expect(found).toBe(true);
});
```

## Creating New CRUD Tests

Use the template to create tests for CRUD resources:

1. **Copy template:**
   ```bash
   cp app/crud/TEMPLATE.spec.ts app/crud/tags.spec.ts
   ```

2. **Customize:**
   ```typescript
   const RESOURCE = 'tags';        // your resource
   const SINGULAR = 'tag';
   const testData = {
       create: { name: 'E2E Test Tag' },
       update: { name: 'Updated Tag' }
   };
   ```

3. **Run:**
   ```bash
   npm run e2e -- tests/e2e/app/crud/tags.spec.ts
   ```

## Best Practices

- ✅ Use helper functions (don't repeat code)
- ✅ Use `data-testid` selectors (most stable)
- ✅ Wait for page loads: `await page.waitForLoadState('networkidle')`
- ✅ Test user workflows, not implementation
- ✅ Give tests meaningful names
- ❌ Don't use hardcoded waits (`page.waitForTimeout()`)
- ❌ Don't test internal logic
- ❌ Don't write tests with tight coupling to styles

## Selector Strategy

Use selectors in this priority:
1. `data-testid` (most stable) - Add to your components
2. `role` attributes (ARIA) - `page.getByRole('button', { name: 'Save' })`
3. `name` attributes - `page.locator('input[name="email"]')`
4. Text content (fragile) - `page.locator('text=Delete')`

## Running Tests

### All Tests
```bash
npm run e2e
```

### Specific Folder
```bash
npm run e2e -- tests/e2e/auth/
npm run e2e -- tests/e2e/app/crud/
```

### Specific File
```bash
npm run e2e -- tests/e2e/auth/login.spec.ts
```

### With Options
```bash
# Interactive UI debugging
npm run e2e:ui

# With visible browser
npm run e2e:headed

# With custom timeout
npm run e2e -- --timeout=60000

# Generate report
npm run e2e
npm run e2e:report
```

## Debugging

### Interactive Mode (Best)
```bash
npm run e2e:ui
```
- Step through tests
- Inspect elements
- See network requests
- Pause on breakpoints

### Headed Mode
```bash
npm run e2e:headed
```
- See browser window
- Watch interactions in real-time

### View Report
```bash
npm run e2e
npm run e2e:report
```

### Failed Chromium Screenshots
- Playwright now saves a screenshot automatically for each failed test.
- Files are stored under `test-results/` in the failing test folder (folder names end with `-chromium`).
- Open `playwright-report/index.html` via `npm run e2e:report` to view screenshots, traces, and videos per failure.

## Troubleshooting

**Tests timeout?**
```bash
npm run e2e -- --timeout=60000
```

**Selector not found?**
- Run with `npm run e2e:ui` to inspect
- Use Playwright Inspector: `npm run e2e -- --debug`
- Check if page is fully loaded

**Database not ready?**
```bash
npm run e2e:prepare
npm run e2e
```

**Need to see what's happening?**
```bash
npm run e2e:headed
```

## Documentation

- **[E2E Quick Start](../../doc/E2E_QUICK_START.md)** - Getting started guide
- **[E2E Testing Guide](../../doc/E2E_TESTING_GUIDE.md)** - Complete reference
- **[E2E Implementation Strategy](../../doc/E2E_IMPLEMENTATION_STRATEGY.md)** - Roadmap and phases
- **[E2E Testing Checklist](../../doc/E2E_TESTING_CHECKLIST.md)** - Progress tracking
- **[Playwright Docs](https://playwright.dev)** - Official documentation

## Current Status

**Phase 1: ✅ Complete**
- Helpers created
- Auth tests converted
- Navigation tests added
- CRUD template created

**Phase 2: ⏳ Planned**
- Dashboard tests
- Employee profile tests

**Phase 3: ⏳ Planned**
- Simple CRUD tests (tags, sites, etc.)

**Phase 4: ⏳ Planned**
- Scale remaining CRUD tests

See [E2E Implementation Strategy](../../doc/E2E_IMPLEMENTATION_STRATEGY.md) for details.

## Contact & Support

Questions about:
- **Writing tests?** → See [E2E Testing Guide](../../doc/E2E_TESTING_GUIDE.md)
- **Using helpers?** → See [E2E Testing Guide - Helpers](../../doc/E2E_TESTING_GUIDE.md#helper-functions)
- **Getting started?** → See [E2E Quick Start](../../doc/E2E_QUICK_START.md)
- **Implementation plan?** → See [E2E Implementation Strategy](../../doc/E2E_IMPLEMENTATION_STRATEGY.md)

---

**Last Updated:** 2026-04-20  
**Status:** Phase 1 Complete ✅

