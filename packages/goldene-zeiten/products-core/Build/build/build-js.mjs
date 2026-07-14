/*
 * Compiles this extension's TypeScript sources 1:1 into ES modules under
 * Resources/Public/JavaScript/. Nothing is bundled: `lit` and `@typo3/*` imports
 * stay bare specifiers that TYPO3's importmap resolves at runtime, and sibling
 * modules keep their relative `./*.js` specifiers, so the browser loads exactly
 * the files tsc emitted.
 */
import { execFileSync } from 'node:child_process';
import { existsSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const buildDir = dirname(dirname(fileURLToPath(import.meta.url)));
const sourceDir = join(buildDir, 'Sources', 'TypeScript');
const outputDir = resolve(buildDir, '..', 'Resources', 'Public', 'JavaScript');

const banner = `/*
 * This file is part of the TYPO3 CMS extension "products_core".
 *
 * It is free software; you can redistribute it and/or modify it under the terms
 * of the GNU General Public License, either version 2 of the License, or any
 * later version.
 *
 * Generated from Build/Sources/TypeScript - do not edit directly.
 */
`;

/** Every .ts source maps 1:1 onto a generated .js of the same relative path. */
const generatedFiles = (dir, extension) => {
  if (!existsSync(dir)) {
    return [];
  }
  return readdirSync(dir).flatMap((entry) => {
    const path = join(dir, entry);
    if (statSync(path).isDirectory()) {
      return generatedFiles(path, extension);
    }
    return path.endsWith(extension) && !path.endsWith('.d.ts') ? [path] : [];
  });
};

const expected = new Set(
  generatedFiles(sourceDir, '.ts').map((path) => relative(sourceDir, path).replace(/\.ts$/, '.js')),
);

// Drop artefacts of sources that were renamed or deleted, so a stale .js can
// never survive in the extension and get shipped.
for (const path of generatedFiles(outputDir, '.js')) {
  const relativePath = relative(outputDir, path);
  if (
    !expected.has(relativePath) &&
    readFileSync(path, 'utf8').startsWith('/*\n * This file is part of the TYPO3 CMS extension "products_core".')
  ) {
    rmSync(path);
  }
}

// The workspace's own TypeScript, resolved explicitly: `npx tsc` would happily download an unrelated
// package of that name from the registry when node_modules is not installed, and compile nothing.
const tsc = createRequire(import.meta.url).resolve('typescript/bin/tsc');
execFileSync(process.execPath, [tsc, '--project', join(buildDir, 'tsconfig.json')], { stdio: 'inherit' });

for (const relativePath of expected) {
  const path = join(outputDir, relativePath);
  if (existsSync(path)) {
    writeFileSync(path, banner + readFileSync(path, 'utf8'));
  }
}

console.log(`Compiled ${expected.size} TypeScript module(s) into ${relative(process.cwd(), outputDir)}`);
