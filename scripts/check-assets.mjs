import { readFileSync, readdirSync } from 'node:fs';
import { resolve, extname, sep } from 'node:path';
import { gzipSync } from 'node:zlib';
import { pathToFileURL } from 'node:url';

export function checkAssets(directory, limits = { '.js': 150_000, '.css': 60_000 }) {
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
  const totals = { '.js': 0, '.css': 0 };
  for (const file of readdirSync(root, { recursive: true })) {
    const extension = extname(file);
    if (extension in totals) totals[extension] += gzipSync(readFileSync(resolve(root, file))).length;
  }
  for (const [extension, bytes] of Object.entries(totals)) {
    if (bytes > limits[extension]) throw new Error(`${extension} exceeds gzip budget: ${bytes}`);
  }
  return totals;
}
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  console.log(checkAssets('web/themes/custom/nusry/dist'));
}
