import { type Page, type Locator, expect } from '@playwright/test';

/**
 * Page object for dashboard.php — the authenticated feed.
 * Covers the post composer, feed, food reactions, comments modal, stories bar.
 */
export class DashboardPage {
  readonly page: Page;

  // Composer
  readonly descriptionInput: Locator;
  readonly imageInput: Locator;
  readonly postButton: Locator;

  // Feed
  readonly feed: Locator;
  readonly postCards: Locator;

  // Stories
  readonly storiesContainer: Locator;
  readonly storyItems: Locator;

  // Comments modal
  readonly commentsModal: Locator;
  readonly commentInput: Locator;
  readonly commentSubmit: Locator;

  constructor(page: Page) {
    this.page = page;
    this.descriptionInput = page.locator('#postDescription');
    this.imageInput = page.locator('#postImage');
    this.postButton = page.locator('#postButton');

    this.feed = page.locator('.posts-feed');
    this.postCards = page.locator('.post-card');

    this.storiesContainer = page.locator('.stories-container');
    // Real stories are <a class="story-item">; the static "Add Story" tile is a
    // <div class="story-item add-story"> and must be excluded.
    this.storyItems = page.locator('a.story-item');

    this.commentsModal = page.locator('#commentsModal');
    this.commentInput = page.locator('#commentInput');
    this.commentSubmit = page.locator('#commentSubmit');
  }

  async goto() {
    // The PHP built-in server is single-threaded; wait for DOM, not every asset.
    await this.page.goto('/dashboard.php', { waitUntil: 'domcontentloaded' });
  }

  /** Create a text-only post. post.php redirects back to dashboard.php (~1s server sleep). */
  async createTextPost(text: string) {
    await this.descriptionInput.fill(text);
    await Promise.all([
      this.page.waitForURL('**/dashboard.php', { waitUntil: 'domcontentloaded', timeout: 20_000 }),
      this.postButton.click(),
    ]);
  }

  /** Number of posts currently in the feed. */
  async postCount(): Promise<number> {
    return this.postCards.count();
  }

  /** The newest post is rendered first in the feed. */
  firstPost(): Locator {
    return this.postCards.first();
  }

  /** React to a post with the given reaction type (hover reveals the icons, then click). */
  async react(post: Locator, type: 'yummy' | 'delicious' | 'tasty' | 'love' | 'amazing') {
    const likeContainer = post.locator('.post-likes-container');
    await likeContainer.hover();
    const icon = post.locator(`.reaction-icon[data-reaction="${type}"]`);
    await icon.click();
  }

  /** Open the comments modal for a post. */
  async openComments(post: Locator) {
    await post.locator('.post-comments').click();
    await expect(this.commentsModal).toBeVisible();
  }

  async submitComment(text: string) {
    await this.commentInput.fill(text);
    await this.commentSubmit.click();
  }

  /** Locator for a post's comment-count label (#comment-count-<postId>). */
  commentCount(postId: string): Locator {
    return this.page.locator(`#comment-count-${postId}`);
  }
}
