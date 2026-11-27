import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { checkAssets } from '../../scripts/check-assets.mjs';

test('asset gate rejects broken builds, traversal, and excessive payloads', () => {
  const root = mkdtempSync(join(tmpdir(), 'nusry-assets-'));
  try {
    mkdirSync(join(root, '.vite'));
    const manifest = { 'src/main.js': { file: 'main.js', css: ['main.css'] } };
    const save = () => writeFileSync(join(root, '.vite/manifest.json'), JSON.stringify(manifest));
    save();
    assert.throws(() => checkAssets(root), /ENOENT/);
    writeFileSync(join(root, 'main.js'), 'console.log("demo");');
    writeFileSync(join(root, 'main.css'), 'body{color:red}');
    writeFileSync(join(root, 'font.woff2'), '0123456789');
    const heroFile = join(root, 'hero-mobile.webp');
    writeFileSync(heroFile, 'webp-bytes');
    const result = checkAssets(root, undefined, heroFile);
    assert.ok(result['.js'] > 0);
    assert.equal(result['.woff2'], 10);
    assert.equal(result.mobileHero, 10);
    assert.throws(() => checkAssets(root, { '.js': 1, '.css': 1, '.woff2': 1 }), /exceeds budget/);
    assert.throws(() => checkAssets(root, undefined, heroFile, 5), /Mobile hero exceeds budget/);
    manifest['src/main.js'].file = '../outside.js'; save();
    assert.throws(() => checkAssets(root), /escapes build directory/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});
