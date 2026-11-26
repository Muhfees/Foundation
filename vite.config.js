import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';
const theme = fileURLToPath(new URL('./web/themes/custom/nusry/', import.meta.url));
export default defineConfig({
  root: theme,
  base: './',
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    target: 'es2022',
    rolldownOptions: { input: `${theme}src/main.js` },
  },
});
