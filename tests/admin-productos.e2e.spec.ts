import { expect, test } from '@playwright/test';

const SKUS = {
  first: '01711',
  second: '02580',
  third: '16764',
};

async function goToAdminProductos(page: import('@playwright/test').Page) {
  await page.goto('/api/admin-productos.php');
  await page.waitForLoadState('domcontentloaded');
  await loginIfNeeded(page);
  await expect(page.locator('#skuInput')).toBeVisible();
}

async function loginIfNeeded(page: import('@playwright/test').Page) {
  const loginButton = page.locator('button:has-text("Iniciar Sesión")');
  if (!(await loginButton.isVisible().catch(() => false))) return;

  const user = process.env.ADMIN_USER;
  const pass = process.env.ADMIN_PASS;
  test.skip(!user || !pass, 'Definí ADMIN_USER y ADMIN_PASS para ejecutar E2E autenticado.');

  await page.locator('input[placeholder*="usuario" i]').fill(user as string);
  await page.locator('input[placeholder*="contraseña" i], input[type="password"]').fill(pass as string);
  await loginButton.click();
  await page.waitForLoadState('domcontentloaded');
}

async function searchSku(page: import('@playwright/test').Page, sku: string) {
  const input = page.locator('#skuInput');
  const button = page.locator('#searchBtn');

  await input.fill(sku);
  await button.click();
  await expect(page.locator('#prodNombre')).not.toHaveText('-', { timeout: 30_000 });
  await expect(page.locator('#productInfo')).toHaveClass(/visible/);
}

test.describe('admin-productos smoke/regression', () => {
  test('carga un SKU y muestra estado consistente', async ({ page }) => {
    await goToAdminProductos(page);
    await searchSku(page, SKUS.first);

    const statusText = (await page.locator('#statusText').innerText()).trim();
    expect(statusText.length).toBeGreaterThan(0);

    const publishVisible = await page.locator('#publishSection').isVisible();
    const updateVisible = await page.locator('#updateSection').isVisible();
    expect(publishVisible || updateVisible).toBeTruthy();
  });

  test('al cambiar rapido de SKU no arrastra boton publicado', async ({ page }) => {
    await goToAdminProductos(page);
    await searchSku(page, SKUS.first);

    await page.locator('#skuInput').fill(SKUS.second);
    await page.locator('#searchBtn').click();

    await expect(page.locator('#pubSku')).toContainText(SKUS.second, { timeout: 30_000 });

    const statusText = (await page.locator('#statusText').innerText()).trim();
    if (statusText.includes('no publicado')) {
      await expect(page.locator('#btnPublicarDirecto')).toHaveText(/Publicar en WooCommerce/);
    }
  });

  test('cambiar de SKU durante carga mantiene SKU final correcto', async ({ page }) => {
    await goToAdminProductos(page);

    await page.locator('#skuInput').fill(SKUS.third);
    await page.locator('#searchBtn').click();
    await page.locator('#skuInput').fill(SKUS.first);
    await page.locator('#searchBtn').click();

    await expect(page.locator('#prodNombre')).not.toHaveText('-', { timeout: 30_000 });

    const pubSku = (await page.locator('#pubSku').textContent())?.trim() ?? '';
    const updSku = (await page.locator('#updSku').textContent())?.trim() ?? '';
    const visibleSku = pubSku && pubSku !== '-' ? pubSku : updSku;

    expect(visibleSku).toContain(SKUS.first);
  });

  test('flujo de publicacion manual opcional', async ({ page }) => {
    test.skip(
      process.env.RUN_PUBLISH_FLOW !== 'true',
      'Set RUN_PUBLISH_FLOW=true para ejecutar publicacion real.'
    );

    await goToAdminProductos(page);
    await searchSku(page, SKUS.second);

    const statusText = (await page.locator('#statusText').innerText()).toLowerCase();
    test.skip(!statusText.includes('no publicado'), 'SKU de prueba no esta en estado no publicado.');

    await page.locator('#btnPublicarDirecto').click();
    await expect(page.locator('#btnPublicarDirecto')).toHaveText(/Publicado|Reintentar/, {
      timeout: 45_000,
    });
  });
});
