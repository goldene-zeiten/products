import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import prettier from 'eslint-config-prettier';
import prettierPlugin from 'eslint-plugin-prettier';
import globals from 'globals';

export default tseslint.config(
  js.configs.recommended,
  ...tseslint.configs.recommended,
  prettier,
  {
    plugins: {
      prettier: prettierPlugin,
    },
    rules: {
      'prettier/prettier': 'error',
      '@typescript-eslint/no-explicit-any': 'off',
    },
  },
  {
    languageOptions: {
      globals: {
        ...globals.browser,
        TYPO3: 'readonly',
      },
    },
  },
  {
    files: ['Tests/Acceptance/**/*.js'],
    languageOptions: {
      globals: {
        ...globals.node,
      },
    },
  },
  {
    ignores: [
      '**/node_modules/**',
      '**/.Build/**',
      '**/original/**',
      '**/Resources/Public/JavaScript/backend/**',
      '**/dist/**',
      '**/var/**',
      '**/Documentation-GENERATED-temp/**',
    ],
  },
);
