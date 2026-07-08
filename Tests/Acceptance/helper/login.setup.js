import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test as setup } from '@playwright/test';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(__dirname, '../.auth/login.json');

setup('authenticate as the demo shopper', async ({ page }) => {
  await page.goto('/login');
  const loginFrame = page.locator('.frame-type-felogin_login');
  await loginFrame.locator('input[name="user"]').fill('shopper1');
  await loginFrame.locator('input[type="password"]').fill('shopper-password');
  await loginFrame.locator('input[type=submit]').click();
  await page.context().storageState({ path: authFile });
});
