import { test, expect } from './fixtures';

test.describe('Stories', () => {
  test('stories bar renders; opening a story navigates to the story viewer', async ({ dashboardPage, page }) => {
    await dashboardPage.goto();
    await expect(dashboardPage.storiesContainer).toBeVisible();

    const count = await dashboardPage.storyItems.count();
    test.skip(count === 0, 'No active stories in the test database to open.');

    await dashboardPage.storyItems.first().click();
    await expect(page).toHaveURL(/story\.php\?user_id=/);
  });
});
