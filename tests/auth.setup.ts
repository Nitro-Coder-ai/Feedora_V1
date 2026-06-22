import { test as setup, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { SignUpPage } from './pages/SignUpPage';
import { SignInPage } from './pages/SignInPage';
import { DashboardPage } from './pages/DashboardPage';
import { TEST_USER, STORAGE_STATE } from './constants';

/**
 * One-time authentication + seed.
 *
 *  1. Register the test account (idempotent: a duplicate-email error on re-runs
 *     is fine — we log in regardless).
 *  2. Log in (sign-in.php redirects to profile.php on success).
 *  3. Ensure at least one post exists in the feed so reaction/comment specs
 *     always have a target.
 *  4. Save the authenticated storage state for all other projects to reuse.
 */
setup('authenticate and seed', async ({ page }) => {
  const signUpPage = new SignUpPage(page);
  const signInPage = new SignInPage(page);
  const dashboardPage = new DashboardPage(page);

  // 1. Register (ignore the outcome — may already exist on re-runs).
  await signUpPage.goto();
  await signUpPage.register(TEST_USER.username, TEST_USER.email, TEST_USER.password);
  // After submit we land on sign-in.php (success) or sign-up.php (already exists).

  // 2. Log in.
  await signInPage.goto();
  await signInPage.login(TEST_USER.email, TEST_USER.password);

  // Successful login leaves the auth pages (redirects to profile.php).
  await expect(page).not.toHaveURL(/sign-in\.php/, { timeout: 15_000 });

  // 3. Seed a post if the feed is empty.
  await dashboardPage.goto();
  await expect(dashboardPage.descriptionInput).toBeVisible();
  if ((await dashboardPage.postCount()) === 0) {
    await dashboardPage.createTextPost('Seed post for E2E suite 🍝');
    await expect(dashboardPage.postCards.first()).toBeVisible();
  }

  // 4. Persist storage state.
  fs.mkdirSync(path.dirname(STORAGE_STATE), { recursive: true });
  await page.context().storageState({ path: STORAGE_STATE });
});
