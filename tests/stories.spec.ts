import { test, expect } from './fixtures';
import { execSync } from 'child_process';

test.describe('Stories', () => {
  test('stories bar renders; opening a story navigates to the story viewer', async ({ dashboardPage, page }) => {
    await dashboardPage.goto();
    await expect(dashboardPage.storiesContainer).toBeVisible();

    const count = await dashboardPage.storyItems.count();
    test.skip(count === 0, 'No active stories in the test database to open.');

    await dashboardPage.storyItems.first().click();
    await expect(page).toHaveURL(/story\.php\?user_id=/);
  });

  test('uploading a story via premium grid board works correctly', async ({ dashboardPage, page }) => {
    await dashboardPage.goto();
    
    const fileInput = page.locator('#storyMedia');
    await expect(fileInput).toBeAttached();

    // Select files to upload
    const testFilePath = 'uploads/posts/post_6a39c7425bae7.webp';
    await fileInput.setInputFiles(testFilePath);

    // Verify modal is visible
    const modal = page.locator('#storyUploadModal');
    await expect(modal).toBeVisible();

    // Verify preview card, badge and remove button
    const previewItem = modal.locator('.story-preview-item');
    await expect(previewItem).toBeVisible();
    await expect(previewItem.locator('.story-preview-badge')).toContainText('image');
    
    const removeBtn = previewItem.locator('.story-preview-remove');
    await expect(removeBtn).toBeVisible();

    // Verify '+' card is visible
    const addCard = modal.locator('.story-preview-add-card');
    await expect(addCard).toBeVisible();

    // Click remove button and verify modal closes
    await removeBtn.click();
    await expect(modal).not.toBeVisible();

    // Upload again to complete the process
    await fileInput.setInputFiles(testFilePath);
    await expect(modal).toBeVisible();

    // Click upload stories button
    const uploadBtn = modal.locator('#uploadStoriesBtn');
    await expect(uploadBtn).toBeVisible();
    
    // Clicking upload will POST to story.php and reload
    await Promise.all([
      page.waitForNavigation({ url: '**/dashboard.php', waitUntil: 'domcontentloaded', timeout: 30000 }),
      uploadBtn.click()
    ]);

    // Verify our story is now in the stories list
    await expect(dashboardPage.storyItems.first()).toBeVisible();
  });

  test('rejects video stories longer than 1 minute', async ({ dashboardPage, page }) => {
    await dashboardPage.goto();

    // Mock getVideoDuration to return 75 seconds (longer than 60s limit)
    await page.evaluate(() => {
      window.getVideoDuration = () => Promise.resolve(75);
    });

    const fileInput = page.locator('#storyMedia');
    await expect(fileInput).toBeAttached();

    // Try to set a video file exceeding 60s limit
    await fileInput.setInputFiles({
      name: 'video_too_long.mp4',
      mimeType: 'video/mp4',
      buffer: Buffer.from('dummy video content')
    });

    // Check that the error toast is displayed
    const toast = page.locator('.toast.error');
    await expect(toast).toBeVisible();
    await expect(toast).toContainText('Videos too long (Max 1 min)');

    // Verify modal is NOT opened because file was rejected
    const modal = page.locator('#storyUploadModal');
    await expect(modal).not.toBeVisible();
  });

  test('cycles through stories of multiple users correctly', async ({ dashboardPage, page }) => {
    // Prevent stories from auto-advancing (duration set to 60s) during test run
    await page.addInitScript(() => {
      window.StoryImageDuration = 60000;
    });

    // 1. Clear active stories to make the order predictable
    execSync(
      `php "C:/Users/moham/.gemini/antigravity-ide/brain/63946f9b-1e2a-4b77-afa8-9a4161fcb1b7/scratch/agent_query.php" "DELETE FROM STORIES"`,
      { env: { ...process.env, FEEDORA_DB: 'Feedora_test' } }
    );

    // 2. Upload a story for E2E Tester (ID 3)
    await dashboardPage.goto();
    const fileInput = page.locator('#storyMedia');
    const testFilePath = 'uploads/posts/post_6a39c7425bae7.webp';
    await fileInput.setInputFiles(testFilePath);
    const uploadBtn = page.locator('#uploadStoriesBtn');
    await Promise.all([
      page.waitForNavigation({ url: '**/dashboard.php', waitUntil: 'domcontentloaded', timeout: 30000 }),
      uploadBtn.click()
    ]);

    // 3. Seed an active story for John Doe (ID 1) that is slightly older
    execSync(
      `php "C:/Users/moham/.gemini/antigravity-ide/brain/63946f9b-1e2a-4b77-afa8-9a4161fcb1b7/scratch/agent_query.php" "INSERT INTO STORIES (USER_ID, IMAGE_URL, MEDIA_TYPE, VISIBILITY, CREATED_AT, EXPIRES_AT) VALUES (1, 'https://res.cloudinary.com/dio1bvxbv/image/upload/f_auto,q_auto/v1782090013/feedora/stories/story_1.jpg', 'image', 'public', DATE_SUB(NOW(), INTERVAL 5 MINUTE), DATE_ADD(NOW(), INTERVAL 23 HOUR))"`,
      { env: { ...process.env, FEEDORA_DB: 'Feedora_test' } }
    );

    // 4. Reload dashboard so both stories show up
    await page.reload({ waitUntil: 'domcontentloaded' });

    // Verify both stories are listed
    const count = await dashboardPage.storyItems.count();
    expect(count).toBeGreaterThanOrEqual(2);

    // Click the first story (E2E Tester's, since it has the latest story time)
    await dashboardPage.storyItems.first().click();
    await expect(page).toHaveURL(/story\.php\?user_id=3/);

    // 5. Navigate to Next User (John Doe, ID 1)
    const nextBtn = page.locator('.story-nav-next');
    await expect(nextBtn).toBeVisible();
    await Promise.all([
      page.waitForNavigation({ url: '**/story.php?user_id=1', waitUntil: 'domcontentloaded', timeout: 30000 }),
      nextBtn.click()
    ]);

    // 6. Navigate back to Previous User's last story (E2E Tester, ID 3)
    const prevBtn = page.locator('.story-nav-prev');
    await expect(prevBtn).toBeVisible();
    await Promise.all([
      page.waitForNavigation({ url: '**/story.php?user_id=3*', waitUntil: 'domcontentloaded', timeout: 30000 }),
      prevBtn.click()
    ]);
    
    // Verify we landed on E2E Tester's stories page
    await expect(page).toHaveURL(/story\.php\?user_id=3/);
  });
});

