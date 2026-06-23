/**
 * Responsive screenshotter (Playwright). Captures the Home page and its
 * "Meet the Chefs" section at several viewport widths so layout problems are
 * actually visible. Usage: node scripts/responsive-shots.mjs [url]
 */
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const url = process.argv[2] || 'http://localhost:8000/Home.php';
const widths = [390, 768, 1024, 1280];
mkdirSync('scripts/shots', { recursive: true });

const browser = await chromium.launch();
for (const w of widths) {
  const page = await browser.newPage({ viewport: { width: w, height: 900 }, deviceScaleFactor: 1 });
  await page.goto(url, { waitUntil: 'load' });
  await page.waitForTimeout(1200);
  // disable the loading screen overlay if present
  await page.evaluate(() => { const l = document.getElementById('loading-screen'); if (l) l.style.display = 'none'; });

  const chefs = page.locator('.chefs-section');
  if (await chefs.count()) {
    await chefs.scrollIntoViewIfNeeded();
    await page.waitForTimeout(500);
    await chefs.screenshot({ path: `scripts/shots/chefs-${w}.png` }).catch(e => console.log('chefs shot fail', w, e.message));
  }
  if (w === 390 || w === 1280) {
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(300);
    await page.screenshot({ path: `scripts/shots/full-${w}.png`, fullPage: true }).catch(e => console.log('full fail', w, e.message));
  }
  await page.close();
  console.log('captured', w);
}
await browser.close();
console.log('done -> scripts/shots/');
