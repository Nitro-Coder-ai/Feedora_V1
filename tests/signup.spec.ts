import { test, expect } from './fixtures';

// These tests exercise the auth UI itself, so start unauthenticated.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Sign up', () => {
  test('registering a new account redirects to sign-in with a success message', async ({ signUpPage, page }) => {
    const uniqueEmail = `e2e_${Date.now()}@test.local`;

    await signUpPage.goto();
    await signUpPage.register('New Tester', uniqueEmail, 'Test123456');

    await expect(page).toHaveURL(/sign-in\.php/);
    await expect(page.locator('.success-message')).toContainText(/registration successful/i);
  });

  test('duplicate email shows an error', async ({ signUpPage }) => {
    // The setup project already registered TEST_USER, so reuse that email.
    await signUpPage.goto();
    await signUpPage.register('Dupe', 'feedora.e2e@test.local', 'Test123456');

    await expect(signUpPage.errorMessage).toContainText(/registration failed/i);
  });
});
