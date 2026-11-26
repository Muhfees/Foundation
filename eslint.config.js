import js from '@eslint/js';
import globals from 'globals';
export default [
  { ignores: ['vendor/**', 'node_modules/**', '.tools/**', '.local/**', 'web/core/**', 'web/modules/contrib/**', 'web/sites/**', 'web/themes/custom/nusry/dist/**', 'test-results/**', 'playwright-report/**'] },
  js.configs.recommended,
  { languageOptions: { globals: { ...globals.node, ...globals.browser } } },
];
