/**
 * Asset minifier (esbuild). Produces *.min.css / *.min.js next to each source.
 *
 *   node scripts/build-assets.mjs
 *
 * IMPORTANT: JS is minified with identifier mangling DISABLED. These are classic
 * (non-module) scripts whose top-level functions are called from inline HTML
 * (onclick="openMobileMenu()", window.deletePost, ...). Mangling top-level names
 * would rename those globals and break the handlers, so we only collapse
 * whitespace + syntax. CSS gets full minification (safe).
 *
 * Re-run after editing any source CSS/JS. The PHP asset() helper auto-serves the
 * .min file when present and appends ?v=<filemtime> for cache-busting.
 */
import { readFileSync, writeFileSync, readdirSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import esbuild from 'esbuild';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

const jsOpts  = { loader: 'js',  minifyWhitespace: true, minifySyntax: true, minifyIdentifiers: false, legalComments: 'none' };
const cssOpts = { loader: 'css', minify: true, legalComments: 'none' };

// Collect targets: every css/js under assets/, plus the root-level shared assets.
const targets = [];
for (const sub of ['assets/css', 'assets/js']) {
  const dir = join(root, sub);
  if (!existsSync(dir)) continue;
  for (const name of readdirSync(dir)) {
    if (/\.min\.(css|js)$/.test(name)) continue;       // don't minify a minified file
    if (/\.(css|js)$/.test(name)) targets.push(join(sub, name));
  }
}
for (const f of ['Home.css', 'fonts.css', 'Home.js']) {
  if (existsSync(join(root, f))) targets.push(f);
}

let totalIn = 0, totalOut = 0, count = 0, failed = 0;
for (const rel of targets) {
  const abs = join(root, rel);
  const isCss = rel.endsWith('.css');
  const src = readFileSync(abs, 'utf8');
  try {
    const { code } = await esbuild.transform(src, isCss ? cssOpts : jsOpts);
    const outRel = rel.replace(/\.(css|js)$/, '.min.$1');
    writeFileSync(join(root, outRel), code);
    totalIn += src.length; totalOut += code.length; count++;
    const pct = ((1 - code.length / src.length) * 100).toFixed(0);
    console.log(`  ${rel}  ${src.length} -> ${code.length}  (-${pct}%)`);
  } catch (e) {
    failed++;
    console.error(`  FAIL ${rel}: ${e.message}`);
  }
}
console.log(`\nMinified ${count} files: ${totalIn} -> ${totalOut} bytes (-${((1 - totalOut / totalIn) * 100).toFixed(0)}%). Failed: ${failed}.`);
process.exit(failed ? 1 : 0);
