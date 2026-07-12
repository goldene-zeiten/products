import { nodeResolve } from '@rollup/plugin-node-resolve';
import commonjs from '@rollup/plugin-commonjs';
import typescript from '@rollup/plugin-typescript';

const plugins = [nodeResolve(), commonjs(), typescript({ tsconfig: './tsconfig.json' })];

// TYPO3 backend's own importmap already provides lit and its own JS/TS
// modules at runtime (see EXT:backend Configuration/JavaScriptModules.php),
// so keep these bare imports untouched in the output instead of bundling
// copies of them.
const typo3External = [/^lit(\/.*)?$/, /^@typo3\//];

/** @type {import('rollup').RollupOptions[]} */
export default [
  {
    input: 'Build/TypeScript/src/backend/CategoryTree.ts',
    output: {
      file: 'Resources/Public/JavaScript/backend/category-tree.js',
      format: 'es',
      sourcemap: true,
    },
    external: typo3External,
    plugins,
  },
  {
    input: 'Build/TypeScript/src/backend/ProductVisibilityToggle.ts',
    output: {
      file: 'Resources/Public/JavaScript/backend/product-visibility-toggle.js',
      format: 'es',
      sourcemap: true,
    },
    external: typo3External,
    plugins,
  },
];
