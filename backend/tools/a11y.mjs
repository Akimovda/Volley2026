#!/usr/bin/env node
// Контраст и доступность через axe-core. Использование:
//   node tools/a11y.mjs /events [--theme night] [--vp 390x844] [--base URL]
import puppeteer from 'puppeteer-core';
import { AxePuppeteer } from '@axe-core/puppeteer';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const argv = process.argv.slice(2);
const opt = { theme: 'day', vp: '390x844', base: 'https://volley-bot.store' };
const targets = [];
for (let i = 0; i < argv.length; i++) argv[i].startsWith('--') ? (opt[argv[i].slice(2)] = argv[++i]) : targets.push(argv[i]);
if (!targets.length) { console.log('node tools/a11y.mjs /events [--theme day|night] [--vp WxH]'); process.exit(1); }

const find = (d) => fs.readdirSync(d, { withFileTypes: true }).flatMap(e => {
  const p = path.join(d, e.name);
  return e.isDirectory() ? find(p) : (e.name === 'chrome' ? [p] : []);
});
const host = new URL(opt.base).hostname;
const browser = await puppeteer.launch({
  executablePath: find(path.join(root, 'storage/app/chromium'))[0], headless: true,
  args: ['--no-sandbox', `--host-resolver-rules=MAP ${host} 127.0.0.1`, '--ignore-certificate-errors'],
});
const [w, h] = opt.vp.split('x').map(Number);
for (const t of targets) {
  const ctx = await browser.createBrowserContext();
  const page = await ctx.newPage();
  await page.setViewport({ width: w, height: h });
  await page.setExtraHTTPHeaders({ 'Accept-Language': 'ru' });
  await page.goto(new URL(t, opt.base).href, { waitUntil: 'networkidle2', timeout: 45000 });
  try { await page.click('.swal-footer .swal-button-container:first-child button'); } catch {}
  if (opt.theme === 'night') await page.evaluate(() => document.body.classList.add('dark'));
  await new Promise(r => setTimeout(r, 600));
  const res = await new AxePuppeteer(page).withRules(['color-contrast', 'label', 'image-alt', 'button-name', 'link-name']).analyze();
  console.log(`\n${t} [${opt.theme} ${opt.vp}] нарушений: ${res.violations.length}`);
  for (const v of res.violations) {
    console.log(`- ${v.id} (${v.impact}): ${v.nodes.length} элем.`);
    for (const n of v.nodes.slice(0, 4)) console.log(`    ${n.target.join(' ')} — ${(n.any[0]?.message || '').slice(0, 120)}`);
  }
  await ctx.close();
}
await browser.close();
