import { expect, test } from '@playwright/test';

const admin = { email: 'admin@skynet.local', password: 'password' };

async function login(page, user) {
  await page.goto('/admin/login');
  await page.getByLabel('Email address').fill(user.email);
  await page.getByRole('textbox', { name: 'Password' }).fill(user.password);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).toHaveURL(/\/admin$/);
}

test('admin dashboard renders stats and core resources are reachable', async ({ page }) => {
  await login(page, admin);

  await expect(page.getByText('Total OLT')).toBeVisible();
  await expect(page.getByText('Total ODC')).toBeVisible();
  await expect(page.getByText('Total ODP')).toBeVisible();
  await expect(page.getByText('Total Kapasitas')).toBeVisible();
  await expect(page.getByText('Pelanggan Aktif')).toBeVisible();
  await expect(page.getByText('Port Kosong')).toBeVisible();
  await expect(page.getByRole('link', { name: /ODP Kritis/ })).toBeVisible();
  await expect(page.getByRole('link', { name: /PON Bermasalah/ })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Alert Operasional' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'ODP Kritis' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'PON Bermasalah' })).toBeVisible();

  await page.mouse.wheel(0, 1200);
  await expect(page.getByRole('heading', { name: 'Distribusi Status Port ODP' })).toBeVisible();

  for (const resource of ['Projects', 'Teams', 'Areas', 'Users', 'Submissions', 'OLT Assets', 'OLT PON Ports', 'ODC Assets', 'ODP Assets']) {
    await page.getByRole('link', { name: resource }).click();
    await expect(page.getByRole('heading', { name: resource, exact: true })).toBeVisible();
  }
});
