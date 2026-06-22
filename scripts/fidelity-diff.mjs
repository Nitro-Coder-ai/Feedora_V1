/**
 * Pixel-diff before/ vs after/ screenshots. Reports per-breakpoint mismatch.
 * Uses Playwright's bundled pngjs + pixelmatch is not bundled, so we do a simple
 * raw-byte compare after decoding with the PNG parser from pngjs (bundled w/ pw).
 */
import { readFileSync, existsSync } from 'node:fs';
import { PNG } from 'playwright-core/lib/utilsBundle';

function load(p) { return PNG.sync.read(readFileSync(p)); }

const widths = [390, 768, 1024, 1280];
let worst = 0;
for (const w of widths) {
  const a = `scripts/fidelity/before/home-${w}.png`;
  const b = `scripts/fidelity/after/home-${w}.png`;
  if (!existsSync(a) || !existsSync(b)) { console.log(w, 'MISSING'); continue; }
  const A = load(a), B = load(b);
  if (A.width !== B.width || A.height !== B.height) {
    console.log(`${w}px: SIZE DIFF before=${A.width}x${A.height} after=${B.width}x${B.height}`);
    worst = Math.max(worst, 100);
    continue;
  }
  let diff = 0;
  const len = Math.min(A.data.length, B.data.length);
  for (let i = 0; i < len; i += 4) {
    const dr = Math.abs(A.data[i] - B.data[i]);
    const dg = Math.abs(A.data[i+1] - B.data[i+1]);
    const db = Math.abs(A.data[i+2] - B.data[i+2]);
    if (dr + dg + db > 24) diff++; // tolerance for AA/jpeg noise
  }
  const total = (len / 4);
  const pct = (diff / total * 100);
  worst = Math.max(worst, pct);
  console.log(`${w}px: ${A.width}x${A.height}  diff pixels=${diff}/${total}  (${pct.toFixed(4)}%)`);
}
console.log(`\nWORST mismatch: ${worst.toFixed(4)}%  -> ${worst < 0.05 ? 'PASS (pixel-identical within AA tolerance)' : 'REVIEW NEEDED'}`);
