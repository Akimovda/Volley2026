#!/usr/bin/env node
// Скриншоты и замеры вёрстки dev-сайта через headless Chrome (storage/app/chromium).
// Запуск: node tools/shot.mjs --help
import puppeteer from 'puppeteer-core';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const HELP = `Использование: node tools/shot.mjs <путь|url> [...] [опции]
  --vp 390x844,360x740,1280x800   вьюпорты (по умолчанию 390x844)
  --theme day|night|both          тема (night = body.dark), по умолчанию day
  --app                           UA приложения (VolleyPlayApp → html.is-app)
  --full                          скриншот всей страницы, а не первого экрана
  --hide sel1,sel2                скрыть элементы (+ .phpdebugbar всегда)
  --measure sel                   вывести getBoundingClientRect() всех совпадений
  --click sel                     кликнуть перед снимком (напр. закрыть баннер)
  --scroll N                      прокрутить на N px перед снимком
  --wait N                        доп. пауза, мс (по умолчанию 800)
  --cookie "name=value"           cookie сессии (для закрытых страниц)
  --no-emoji                      не подставлять Twemoji (в headless нет эмодзи-шрифта)
  --out DIR                       куда писать PNG (по умолчанию /tmp/shots)
  --base URL                      базовый адрес (по умолчанию https://volley-bot.store)
Каждый кадр — отдельный browser context (cookie не протекают). Хост резолвится в 127.0.0.1.
Вывод: пути PNG; смотреть их инструментом Read.`;

const argv = process.argv.slice(2);
const opt = { vp: '390x844', theme: 'day', wait: 800, out: '/tmp/shots', base: 'https://volley-bot.store', hide: '', emoji: true };
const targets = [];
for (let i = 0; i < argv.length; i++) {
  const a = argv[i];
  if (a === '--help' || a === '-h') { console.log(HELP); process.exit(0); }
  else if (a === '--app') opt.app = true;
  else if (a === '--full') opt.full = true;
  else if (a === '--no-emoji') opt.emoji = false;
  else if (a.startsWith('--')) opt[a.slice(2)] = argv[++i];
  else targets.push(a);
}
if (!targets.length) { console.log(HELP); process.exit(1); }

const chromeDir = path.join(root, 'storage/app/chromium');
const find = (d) => fs.readdirSync(d, { withFileTypes: true }).flatMap(e => {
  const p = path.join(d, e.name);
  return e.isDirectory() ? find(p) : (e.name === 'chrome' ? [p] : []);
});
const exe = find(chromeDir)[0];
if (!exe) { console.error('Chrome не найден в ' + chromeDir); process.exit(1); }

const base = new URL(opt.base);
const browser = await puppeteer.launch({
  executablePath: exe, headless: true,
  args: ['--no-sandbox', `--host-resolver-rules=MAP ${base.hostname} 127.0.0.1`, '--ignore-certificate-errors', '--font-render-hinting=none'],
});
fs.mkdirSync(opt.out, { recursive: true });
const themes = opt.theme === 'both' ? ['day', 'night'] : [opt.theme];
const UA_BASE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
const slug = (s) => s.replace(/^https?:\/\/[^/]+/, '').replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'root';

for (const t of targets) {
  const url = /^https?:/.test(t) ? t : base.origin + (t.startsWith('/') ? t : '/' + t);
  for (const vp of opt.vp.split(',')) {
    const [w, h] = vp.split('x').map(Number);
    for (const theme of themes) {
      const ctx = await browser.createBrowserContext();
      const page = await ctx.newPage();
      try {
        await page.setViewport({ width: w, height: h, deviceScaleFactor: 2, isMobile: w < 700, hasTouch: w < 700 });
        await page.setUserAgent(UA_BASE + (opt.app ? ' VolleyPlayApp' : ''));
        await page.setExtraHTTPHeaders({ 'Accept-Language': 'ru' });
        if (opt.cookie) {
          const [n, ...v] = opt.cookie.split('=');
          await page.setCookie({ name: n, value: v.join('='), domain: base.hostname, path: '/', secure: true });
        }
        const msgs = [];
        page.on('pageerror', e => msgs.push('pageerror: ' + e.message));
        page.on('console', m => { if (m.type() === 'error') msgs.push('console: ' + m.text()); });
        const resp = await page.goto(url, { waitUntil: 'networkidle2', timeout: 45000 });
        if (theme === 'night') await page.evaluate(() => document.body.classList.add('dark'));
        await page.addStyleTag({ content: `html{scroll-behavior:auto!important}.phpdebugbar,#app-back-btn{display:none!important}${opt.hide ? opt.hide + '{display:none!important}' : ''}` });
        // баннер «установите приложение» (swal) перекрывает страницу в обычном мобильном UA
        try { await page.click('.swal-footer .swal-button-container:first-child button', { delay: 10 }); await new Promise(r => setTimeout(r, 400)); } catch { /* баннера нет */ }
        if (opt.click) { try { await page.click(opt.click); } catch { msgs.push('click: нет ' + opt.click); } }
        if (opt.emoji) {
          try {
            await page.addScriptTag({ url: 'https://cdn.jsdelivr.net/npm/@twemoji/api@latest/dist/twemoji.min.js' });
            await page.evaluate(() => window.twemoji && twemoji.parse(document.body));
          } catch { /* нет сети — эмодзи будут квадратиками */ }
        }
        if (opt.scroll) await page.evaluate(n => window.scrollTo(0, n), Number(opt.scroll));
        await new Promise(r => setTimeout(r, Number(opt.wait)));
        const file = path.join(opt.out, `${slug(url)}__${w}x${h}__${theme}${opt.app ? '__app' : ''}.png`);
        await page.screenshot({ path: file, fullPage: !!opt.full });
        console.log(`${resp.status()} ${file}`);
        if (opt.measure) {
          const rects = await page.$$eval(opt.measure, els => els.map(e => {
            const r = e.getBoundingClientRect();
            return { text: (e.textContent || '').trim().slice(0, 30), x: +r.x.toFixed(1), y: +r.y.toFixed(1), w: +r.width.toFixed(1), h: +r.height.toFixed(1) };
          }));
          console.log(`  measure ${opt.measure}:`, JSON.stringify(rects));
        }
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        if (overflow > 0) console.log(`  ⚠ горизонтальный overflow: +${overflow}px`);
        msgs.slice(0, 5).forEach(m => console.log('  ' + m));
      } catch (e) {
        console.log(`ERR ${url} ${vp} ${theme}: ${e.message}`);
      } finally { await ctx.close(); }
    }
  }
}
await browser.close();
