import puppeteer from 'puppeteer-core';

const base = process.env.BASE ?? 'http://localhost:8089';
const browser = await puppeteer.launch({
  executablePath: '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
  headless: true,
  args: ['--no-sandbox'],
});
const page = await browser.newPage();
await page.setViewport({ width: 1400, height: 1000 });

const violations = [];
const consoleErrors = [];
page.on('console', (m) => { if (['error', 'warning'].includes(m.type())) consoleErrors.push(m.text().slice(0, 300)); });
page.on('requestfailed', (r) => consoleErrors.push('REQFAILED ' + r.url()));
page.on('response', (r) => { if (r.url().includes('livewire') && r.request().method() === 'POST') console.log('LIVEWIRE POST', r.status(), r.url().slice(-40)); if (r.status() >= 400) consoleErrors.push('HTTP ' + r.status() + ' ' + r.url()); });
page.on('pageerror', (e) => consoleErrors.push('PAGEERROR ' + String(e).slice(0, 300)));
await page.evaluateOnNewDocument(() => {
  window.__v = [];
  document.addEventListener('securitypolicyviolation', (e) => {
    window.__v.push({ dir: e.effectiveDirective, blocked: e.blockedURI, src: e.sourceFile, line: e.lineNumber, sample: e.sample, disp: e.disposition });
  });
});
const collect = async (label) => {
  const v = await page.evaluate(() => window.__v.splice(0));
  for (const x of v) violations.push({ label, ...x });
};
const step = async (label, fn) => {
  try { await fn(); } catch (e) { consoleErrors.push(`STEP ${label} failed: ${String(e).slice(0, 200)}`); }
  await new Promise((r) => setTimeout(r, 1200));
  await collect(label);
};

await page.goto(base + '/admin/login', { waitUntil: 'networkidle0' });
await collect('login page');
const alpineOk = await page.evaluate(() => typeof window.Alpine !== 'undefined');
console.log('Alpine present on login:', alpineOk);
await step('login submit', async () => {
  await page.type('input[type=email]', 'a@a.test');
  await page.type('input[type=password]', 'password');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type=submit]')]);
});
console.log('after login url:', page.url());

await step('dashboard', async () => { await page.goto(base + '/admin', { waitUntil: 'networkidle0' }); });
await step('list page', async () => { await page.goto(base + '/admin/users', { waitUntil: 'networkidle0' }); });
await step('sidebar/user menu', async () => {
  const btn = await page.$('.fi-user-menu-trigger, .fi-user-menu button');
  if (btn) await btn.click();
});
await step('theme dark', async () => {
  const el = await page.evaluateHandle(() => [...document.querySelectorAll('.fi-theme-switcher-btn, button')].find((b) => /dark/i.test(b.getAttribute('aria-label') || b.getAttribute('x-tooltip') || '')) );
  if (el.asElement()) await el.asElement().click();
});
await step('create page', async () => { await page.goto(base + '/admin/users/create', { waitUntil: 'networkidle0' }); });
await step('toggle click', async () => { await page.click('.fi-fo-toggle'); });
console.log('FUNCTIONAL toggle switched on:', await page.evaluate(() => !!document.querySelector('.fi-fo-toggle.fi-toggle-on')));
await step('select open', async () => { await page.click('.fi-select-input-btn'); });
console.log('FUNCTIONAL select dropdown opened:', await page.evaluate(() => !!document.querySelector('.fi-select-input-ctn.fi-select-input-ctn-open, .fi-select-input-dropdown, [role=listbox]')));
await step('datepicker open', async () => { await page.click('.fi-fo-date-time-picker-trigger, .fi-fo-date-time-picker input'); });
await step('markdown click', async () => { await page.click('.fi-fo-markdown-editor, .EasyMDEContainer'); });
await step('color picker', async () => { await page.click('.fi-fo-color-picker input'); });
await step('tags', async () => { await page.type('.fi-fo-tags-input input', 'x\n'); });
await step('code editor', async () => { await page.click('.fi-fo-code-editor, .cm-editor'); });
await step('rich editor type', async () => { await page.click('.tiptap, .ProseMirror'); await page.keyboard.type('hello'); await page.click('.fi-fo-rich-editor-toolbar button'); });
await step('livewire roundtrip: create user', async () => {
  await page.goto(base + '/admin/users/create', { waitUntil: 'networkidle0' });
  await page.type('#form\\.name', 'Bob');
  await page.type('#form\\.email', 'bob@example.test');
  await page.evaluate(() => document.querySelector('form button[type=submit]').click());
  await new Promise((r) => setTimeout(r, 2500));
});
console.log('FUNCTIONAL after create submit url:', page.url());
await step('edit page', async () => { await page.goto(base + '/admin/users/1/edit', { waitUntil: 'networkidle0' }); });
await step('list: search + action modal', async () => { await page.goto(base + '/admin/users', { waitUntil: 'networkidle0' }); await page.type('.fi-ta-search-field input', 'a'); });
await step('wire:navigate click', async () => { await page.click('.fi-sidebar-item a'); });

const unique = new Map();
for (const v of violations) unique.set(`${v.dir}|${v.blocked}|${v.src}|${v.line}`, v);
console.log('\nCSP VIOLATIONS (unique):', unique.size);
for (const v of unique.values()) console.log(JSON.stringify(v));
console.log('\nCONSOLE ERRORS/WARNINGS:');
for (const c of new Set(consoleErrors)) console.log(c);
await browser.close();
