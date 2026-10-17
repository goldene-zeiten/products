// Logs into the TYPO3 backend of the acceptance instance with the admin user setupInstance.sh creates.
export async function loginToBackend(page) {
  await page.goto('/typo3/');
  await page.locator('#t3-username').fill('admin');
  await page.locator('#t3-password').fill('AcceptanceTest123!');
  await Promise.all([page.waitForURL(/\/typo3\/(main|module)/), page.locator('#t3-login-submit').click()]);
}
