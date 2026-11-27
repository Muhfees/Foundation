import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { resolve, extname, sep } from 'node:path';
import { gzipSync } from 'node:zlib';
import { pathToFileURL } from 'node:url';

export function checkAssets(
  directory,
  limits = { '.js': 150_000, '.css': 60_000, '.woff2': 160_000 },
  heroPath = null,
  heroMaxBytes = 250_000,
) {
  const root = resolve(directory);
  const manifest = JSON.parse(readFileSync(resolve(root, '.vite/manifest.json'), 'utf8'));
  const entry = manifest['src/main.js'];
  if (!entry?.file || !entry.css?.length) throw new Error('Missing theme entry or stylesheet');
  for (const item of Object.values(manifest)) {
    for (const file of [item.file, ...(item.css ?? []), ...(item.assets ?? [])]) {
      if (!resolve(root, file).startsWith(root + sep)) throw new Error('Asset escapes build directory');
      readFileSync(resolve(root, file));
    }
  }
  const totals = { '.js': 0, '.css': 0, '.woff2': 0 };
  for (const file of readdirSync(root, { recursive: true })) {
    const extension = extname(file);
    if (!(extension in totals)) continue;
    const raw = readFileSync(resolve(root, file));
    totals[extension] += extension === '.woff2' ? raw.length : gzipSync(raw).length;
  }
  for (const [extension, bytes] of Object.entries(totals)) {
    if (limits[extension] !== undefined && bytes > limits[extension]) {
      throw new Error(`${extension} exceeds budget: ${bytes} > ${limits[extension]}`);
    }
  }
  if (heroPath && existsSync(heroPath)) {
    const heroBytes = readFileSync(heroPath).length;
    totals.mobileHero = heroBytes;
    if (heroBytes > heroMaxBytes) {
      throw new Error(`Mobile hero exceeds budget: ${heroBytes} > ${heroMaxBytes}`);
    }
  }
  return totals;
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  console.log(checkAssets('web/themes/custom/nusry/dist', undefined, 'web/themes/custom/nusry/assets/images/hero-clinic-mobile.webp'));
}
