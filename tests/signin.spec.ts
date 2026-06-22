import { test, expect } from './fixtures';
import { TEST_USER } from './constants';

// Login tests must start from a logged-out state.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Sign in', () => {
  test('invalid credentials show an error and stay on sign-in', async ({ signInPage, page }) => {
    await signInPage.goto();
    await signInPage.login(TEST_USER.email, 'wrong-password');

    await expect(page).toHaveURL(/sign-in\.php/);
    await expect(signInPage.errorMessage).toContainText(/invalid email or password/i);
  });

  test('valid credentials log the user in', async ({ signInPage, page }) => {
    await signInPage.goto();
    await signInPage.login(TEST_USER.email, TEST_USER.password);

    // Successful login leaves the sign-in page (redirects to profile.php).
    await expect(page).not.toHaveURL(/sign-in\.php/);
    // And a protected page is reachable without bouncing back to sign-in.
    await page.goto('/dashboard.php', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/dashboard\.php/);
  });
});
