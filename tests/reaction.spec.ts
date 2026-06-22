import { test, expect } from './fixtures';

test.describe('Reactions', () => {
  test('reacting to a post updates the reaction label', async ({ dashboardPage }) => {
    await dashboardPage.goto();
    const post = dashboardPage.firstPost();
    const likeCount = post.locator('.like-count').first();

    const before = (await likeCount.textContent())?.trim() ?? '';

    await dashboardPage.react(post, 'yummy');

    // updateReactionUI() rewrites the count text (and toggles .post-likes.active),
    // so the label must differ from its pre-click value either way (react/un-react).
    await expect(likeCount).not.toHaveText(before);
  });
});
