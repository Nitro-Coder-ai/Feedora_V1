import { test, expect } from './fixtures';

test.describe('Comments', () => {
  test('submitting a comment shows it in the modal and increments the count', async ({ dashboardPage, page }) => {
    await dashboardPage.goto();
    const post = dashboardPage.firstPost();
    const postId = await post.getAttribute('data-post-id'); // .post-card carries data-post-id
    expect(postId).toBeTruthy();

    // Parse the current count from "N comment(s)".
    const countLabel = dashboardPage.commentCount(postId!);
    const initial = parseInt((await countLabel.textContent())?.match(/\d+/)?.[0] ?? '0', 10);

    await dashboardPage.openComments(post);
    const text = `Nice recipe! ${Date.now()}`;
    await dashboardPage.submitComment(text);

    // The new comment renders into the modal body...
    await expect(page.locator('#commentsBody')).toContainText(text);
    // ...and the post's comment count increments.
    await expect(countLabel).toContainText(String(initial + 1));
  });
});
