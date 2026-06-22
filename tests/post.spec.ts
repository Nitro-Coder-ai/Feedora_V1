import { test, expect } from './fixtures';

test.describe('Create post', () => {
  test('publishing a text post adds it to the feed', async ({ dashboardPage, page }) => {
    await dashboardPage.goto();
    const before = await dashboardPage.postCount();

    const marker = `E2E post ${Date.now()}`;
    await dashboardPage.createTextPost(marker);

    // Back on the dashboard, the new post is visible and the count grew.
    await expect(page.getByText(marker)).toBeVisible();
    expect(await dashboardPage.postCount()).toBe(before + 1);
  });
});
