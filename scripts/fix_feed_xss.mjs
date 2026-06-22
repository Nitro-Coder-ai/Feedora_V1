/**
 * One-off: escape user-controlled values interpolated into innerHTML in the feed
 * JS (stored-XSS fix). Adds escapeHtml() and wraps the known sinks. Literal
 * (non-regex) replacement so template-literal syntax is preserved exactly.
 */
import { readFileSync, writeFileSync } from 'node:fs';

const helper =
`
// XSS guard: escape user-controlled values before they enter innerHTML.
function escapeHtml(value) {
  if (value === null || value === undefined) return '';
  return String(value).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
  });
}
`;

const repls = [
  [`\${user.NAME || 'User'}`,          `\${escapeHtml(user.NAME || 'User')}`],
  [`\${user.NAME || 'Unknown User'}`,  `\${escapeHtml(user.NAME || 'Unknown User')}`],
  [`\${reaction.NAME}`,                `\${escapeHtml(reaction.NAME)}`],
  [`\${comment.NAME}`,                 `\${escapeHtml(comment.NAME)}`],
  [`\${comment.COMMENT_TEXT}`,         `\${escapeHtml(comment.COMMENT_TEXT)}`],
];

for (const f of ['assets/js/dashboard.js', 'assets/js/profile_details.js', 'assets/js/profile_settings.js']) {
  let s = readFileSync(f, 'utf8');
  let n = 0;
  for (const [a, b] of repls) {
    const count = s.split(a).length - 1;
    if (count) { s = s.split(a).join(b); n += count; }
  }
  if (!s.includes('function escapeHtml(')) {
    const nl = s.indexOf('\n');               // insert after the header comment line
    s = s.slice(0, nl + 1) + helper + s.slice(nl + 1);
  }
  writeFileSync(f, s);
  console.log(`  ${f}: ${n} sink(s) escaped`);
}
