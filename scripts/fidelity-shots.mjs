/**
 * Fidelity capture: full-page screenshots of Home.php at 4 breakpoints into a
 * labelled folder so before/after can be pixel-diffed.
 *   node scripts/fidelity-shots.mjs <label>   (e.g. "before" | "after")
 */
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const label = process.argv[2] || 'run';
const url = 'http://localhost:8000/Home.php';
const widths = [390, 768, 1024, 1280];
const dir = `scripts/fidelity/${label}`;
mkdirSync(dir, { recursive: true });

const browser = await chromium.launch();
for (const w of widths) {
  const page = await browser.newPage({ viewport: { width: w, height: 900 }, deviceScaleFactor: 1 });
  await page.goto(url, { waitUntil: 'load' });
  await page.waitForTimeout(1500);
  await page.evaluate(() => { const l = document.getElementById('loading-screen'); if (l) l.style.display = 'none'; });
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(400);
  await page.screenshot({ path: `${dir}/home-${w}.png`, fullPage: true });
  await page.close();
  console.log('captured', label, w);
}
await browser.close();
console.log('done ->', dir);
