import { test, expect } from './fixtures';

// Uses the authenticated storageState from the setup project (default).
test.describe('Dashboard', () => {
  test('feed, composer and stories bar render without page errors', async ({ dashboardPage, page }) => {
    const pageErrors: string[] = [];
    page.on('pageerror', (err) => pageErrors.push(err.message));

    await dashboardPage.goto();

    await expect(page).toHaveURL(/dashboard\.php/);
    await expect(dashboardPage.descriptionInput).toBeVisible(); // composer
    await expect(dashboardPage.feed).toBeVisible();
    await expect(dashboardPage.storiesContainer).toBeVisible();
    expect(await dashboardPage.postCount()).toBeGreaterThan(0); // seeded post

    expect(pageErrors, `Unexpected JS exceptions: ${pageErrors.join('; ')}`).toHaveLength(0);
  });
});
