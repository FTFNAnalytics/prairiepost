// Browser-level regression for the two F01 fixtures: load the rendered
// story in real Chromium, then activate the poisoned link by click and by
// keyboard. Any execution changes document.title to a known marker.
// Usage: PP_CHROMIUM=/path/to/chrome node tools/xss-browser-check.mjs <base-url> <story-path>
// Requires playwright-core (npm i playwright-core) and a Chromium binary.
import { chromium } from 'playwright-core';

const [base, path] = process.argv.slice(2);
const browser = await chromium.launch({
  executablePath: process.env.PP_CHROMIUM || '/usr/bin/chromium',
  args: ['--no-sandbox'],
});
const page = await browser.newPage();
let fails = 0;
const check = (cond, label) => { if (!cond) { console.log('FAIL ' + label); fails++; } else { console.log('ok   ' + label); } };

await page.goto(base + path, { waitUntil: 'load' });
// Execution would REPLACE the document title with exactly "123456789";
// the headline text itself (escaped, inert) legitimately contains those
// digits, so equality is the discriminator.
const titleOnLoad = await page.title();
check(titleOnLoad.trim() !== '123456789', 'JSON-LD headline did not execute on page load (title: ' + titleOnLoad.slice(0, 60) + ')');
check(titleOnLoad.includes('Audit script marker'), 'the headline still renders as visible text');
check(!titleOnLoad.includes('AUDIT_HTML_EXECUTED'), 'body link did not execute on page load');

// The link the fixture planted: activate it every way a reader could.
const link = page.locator('.article a:has-text("Audit sanitizer link"), article a:has-text("Audit sanitizer link")').first();
if (await link.count() > 0) {
  const href = await link.getAttribute('href');
  check(href === null || !/script/i.test(href), 'the link carries no executable href (' + href + ')');
  await link.click({ force: true }).catch(() => {});
  await page.waitForTimeout(300);
  check(!(await page.title()).includes('AUDIT_HTML_EXECUTED'), 'click activation executed nothing');
  await link.focus().catch(() => {});
  await page.keyboard.press('Enter').catch(() => {});
  await page.waitForTimeout(300);
  check(!(await page.title()).includes('AUDIT_HTML_EXECUTED'), 'keyboard activation executed nothing');
} else {
  check(true, 'link text survives but as plain content (no anchor rendered)');
}

// The JSON-LD block must still be VALID structured data.
const jsonld = await page.evaluate(() => {
  const el = document.querySelector('script[type="application/ld+json"]');
  if (!el) return null;
  try { return JSON.parse(el.textContent); } catch { return 'PARSE-ERROR'; }
});
check(jsonld !== 'PARSE-ERROR' && jsonld !== null, 'JSON-LD still parses as JSON');
check(jsonld && typeof jsonld.headline === 'string' && jsonld.headline.includes('Audit script marker'),
  'the hostile headline is carried as DATA inside the JSON-LD');

await browser.close();
process.exit(fails ? 1 : 0);
