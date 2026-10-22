import { test, expect } from '@playwright/test';

const PROTOCOL_VERSION = '2025-06-18';

// Talks to the MCP endpoint like an assistant: JSON-RPC over POST, within the session "initialize" opens.
async function rpc(request, sessionId, message) {
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json, text/event-stream',
  };
  if (sessionId) {
    headers['Mcp-Session-Id'] = sessionId;
    headers['MCP-Protocol-Version'] = PROTOCOL_VERSION;
  }

  return request.post('/mcp', { headers, data: message });
}

async function openSession(request) {
  const response = await rpc(request, null, {
    jsonrpc: '2.0',
    id: 1,
    method: 'initialize',
    params: {
      protocolVersion: PROTOCOL_VERSION,
      capabilities: {},
      clientInfo: { name: 'playwright', version: '1.0.0' },
    },
  });
  expect(response.status()).toBe(200);
  const sessionId = response.headers()['mcp-session-id'];
  expect(sessionId).toBeTruthy();
  await rpc(request, sessionId, { jsonrpc: '2.0', method: 'notifications/initialized' });

  return sessionId;
}

async function callTool(request, sessionId, name, args) {
  const response = await rpc(request, sessionId, {
    jsonrpc: '2.0',
    id: 10,
    method: 'tools/call',
    params: { name, arguments: args },
  });
  expect(response.status()).toBe(200);
  const result = (await response.json()).result;
  expect(result.isError ?? false).toBe(false);

  return result.structuredContent;
}

test('an assistant searches the shop, fills a basket and hands the customer over to the checkout', async ({
  request,
  page,
}) => {
  const sessionId = await openSession(request);

  const found = await callTool(request, sessionId, 'search_products', { query: 'Blue Cotton Shirt' });
  const shirt = found.items.find((item) => item.title === 'Blue Cotton Shirt');
  expect(shirt).toBeTruthy();

  const { basketToken } = await callTool(request, sessionId, 'create_basket', {});
  await callTool(request, sessionId, 'add_to_basket', { basketToken, product: shirt.uid, quantity: 2 });
  const { checkoutUrl } = await callTool(request, sessionId, 'start_checkout', { basketToken });

  await page.goto(checkoutUrl);

  await expect(page).toHaveURL(/\/checkout/);
  await expect(page.getByRole('heading', { name: 'Checkout: Address' })).toBeVisible();
  await expect(page.getByRole('link', { name: /^Basket 2 / })).toBeVisible();

  await page.goto('/basket');
  const row = page.locator('tr', { hasText: 'Blue Cotton Shirt' });
  await expect(row).toBeVisible();
  await expect(row.locator('input[name="tx_productscore_basket[quantity]"]')).toHaveValue('2');
});
