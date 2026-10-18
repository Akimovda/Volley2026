#!/usr/bin/env node
// Сравнение двух PNG (до/после правки). Использование:
//   node tools/shot-diff.mjs before.png after.png [diff.png]
// Выводит долю отличающихся пикселей; diff.png — красным подсвечены отличия.
import fs from 'node:fs';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';

const [a, b, out = '/tmp/shots/diff.png'] = process.argv.slice(2);
if (!a || !b) { console.log('node tools/shot-diff.mjs before.png after.png [diff.png]'); process.exit(1); }
const A = PNG.sync.read(fs.readFileSync(a)), B = PNG.sync.read(fs.readFileSync(b));
if (A.width !== B.width || A.height !== B.height) {
  console.log(`Размеры различаются: ${A.width}x${A.height} vs ${B.width}x${B.height} (высота страницы изменилась)`);
  process.exit(2);
}
const diff = new PNG({ width: A.width, height: A.height });
const n = pixelmatch(A.data, B.data, diff.data, A.width, A.height, { threshold: 0.1 });
fs.writeFileSync(out, PNG.sync.write(diff));
console.log(`${n} px отличается (${(100 * n / (A.width * A.height)).toFixed(2)}%) → ${out}`);
