/**
 * Deterministic fidelity proof: capture getComputedStyle for a broad element set
 * at several widths. Run once labelled "new" (current Home.min.css), then restore
 * the .bak and run "old", then diff the two JSON files.
 *   node scripts/computed-compare.mjs <label>
 */
import { chromium } from '@playwright/test';
import { writeFileSync, mkdirSync } from 'node:fs';

const label = process.argv[2] || 'run';
const url = 'http://localhost:8000/Home.php';
const widths = [390, 768, 1024, 1280];
mkdirSync('scripts/fidelity', { recursive: true });

// Properties most likely to reveal a visual regression.
const PROPS = ['display','position','top','right','bottom','left','width','max-width',
  'margin-top','margin-right','margin-bottom','margin-left',
  'padding-top','padding-right','padding-bottom','padding-left',
  'font-size','font-weight','font-family','line-height','color','background-color',
  'background-image','box-shadow','flex-direction','grid-template-columns',
  'justify-content','align-items','justify-items','text-align','white-space',
  'vertical-align','transform','transition','border-radius','min-height','min-width','overflow-x'];

const SELECTORS = ['.hero-container','.hero-content','.hero-image-container','.hero-button',
  '.hero-button.primary-button','.hero-button.secondary-button','.newsletter-button',
  '.signin-button','.follow-button','.live-tab','.subscribe-btn','.explore-button',
  '.live-button','.cta-button','.load-more-button','.nav-link','.chef-title','.chef-card',
  '.chef-name','.search-input','.search-button','.recipe-container','.live-cooking-container',
  '.chefs-container','.community-container','.explore-container','.testimonials-container',
  '.recipe-section','.live-cooking-section','.chefs-section','.recipe-card','.live-card',
  '.post-card','.recipe-image','.chef-image','.live-tabs','.explore-title','.testimonials-title',
  '.testimonials-header','.explore-title-container','.recipe-title','.live-title','.chefs-title',
  '.community-title','.hiw-title','.explore-title-icon','.testimonials-icon','.title-icon',
  '.live-title-icon','.community-title-icon','.stats-container','.feature-container',
  '.recipe-image-container','.post-image','.chef-avatar'];

const out = {};
const browser = await chromium.launch();
for (const w of widths) {
  const page = await browser.newPage({ viewport: { width: w, height: 900 }, deviceScaleFactor: 1 });
  await page.goto(url, { waitUntil: 'load' });
  await page.waitForTimeout(800);
  const data = await page.evaluate(({ SELECTORS, PROPS }) => {
    const res = {};
    for (const sel of SELECTORS) {
      const el = document.querySelector(sel);
      if (!el) { res[sel] = null; continue; }
      const cs = getComputedStyle(el);
      const o = {};
      for (const p of PROPS) o[p] = cs.getPropertyValue(p);
      res[sel] = o;
    }
    return res;
  }, { SELECTORS, PROPS });
  out[w] = data;
  await page.close();
}
await browser.close();
writeFileSync(`scripts/fidelity/computed-${label}.json`, JSON.stringify(out, null, 2));
console.log('wrote scripts/fidelity/computed-' + label + '.json');
