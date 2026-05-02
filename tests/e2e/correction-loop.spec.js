import { expect, test } from '@playwright/test';
import path from 'node:path';

const technician = { email: 'tech@skynet.local', password: 'password' };
const admin = { email: 'admin@skynet.local', password: 'password' };
const odcPhoto = path.resolve('tests/e2e/fixtures/odc-photo.png');

async function login(page, user) {
  await page.goto('/admin/login');
  await page.getByLabel('Email address').fill(user.email);
  await page.getByRole('textbox', { name: 'Password' }).fill(user.password);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).toHaveURL(/\/admin$/);
}

async function logout(page) {
  await page.getByRole('button', { name: /user menu/i }).click();
  await page.getByRole('button', { name: 'Sign out' }).click();
  await expect(page).toHaveURL(/\/admin\/login/);
}

async function openAssignments(page) {
  await page.getByRole('complementary').getByRole('link', { name: 'Penugasan Lapangan' }).click();
  await expect(page.getByRole('heading', { name: 'Penugasan Lapangan', exact: true })).toBeVisible();
}

async function chooseRelationshipOption(page, option) {
  await page.getByRole('button', { name: 'Select an option' }).first().click();
  await page.getByRole('textbox', { name: 'Search' }).fill(option);
  await page.getByRole('option', { name: option, exact: true }).click();
}

async function createOdcAssignment(page) {
  await openAssignments(page);
  await page.getByRole('link', { name: 'Penugasan Baru' }).click();
  await chooseRelationshipOption(page, 'Malang Deployment');
  await chooseRelationshipOption(page, 'Teknisi Lapangan');
  await chooseRelationshipOption(page, 'Malang Area 01');
  await page.getByLabel('Jenis Aset').selectOption({ label: 'ODC' });
  await page.getByLabel('Lintang Titik Tugas').fill('-7.96660000');
  await page.getByLabel('Bujur Titik Tugas').fill('112.63260000');
  await page.getByRole('button', { name: 'Create', exact: true }).click();
  await expect(page.getByText('Created')).toBeVisible();
}

async function fillPorts(page) {
  const rows = page.locator('.fi-fo-repeater-item');
  await expect(rows).toHaveCount(8);

  for (const portNumber of Array.from({ length: 8 }, (_, index) => index + 1)) {
    const row = rows.nth(portNumber - 1);

    await row.getByLabel('Nomor Port').selectOption(`${portNumber}`);
    await row.getByLabel('Status').selectOption({ label: portNumber % 2 === 0 ? 'Terpakai' : 'Tersedia' });
  }
}

async function completeAndSubmitAssignment(page, boxId, notes) {
  await page.context().grantPermissions(['geolocation']);
  await page.context().setGeolocation({ latitude: -7.96662, longitude: 112.632632 });

  await openAssignments(page);
  const assignmentRow = page.getByRole('row').filter({ hasText: boxId }).or(page.getByRole('row').filter({ hasText: 'Ditugaskan' })).first();
  await assignmentRow.getByRole('link', { name: 'Edit' }).click();

  await page.getByLabel(/Box ID/).fill(boxId);
  await page.locator('input[type="file"]').setInputFiles(odcPhoto);
  await page.getByRole('button', { name: 'Ambil Lokasi GPS' }).click();
  await expect(page.getByLabel('Lintang Lokasi Laporan')).toHaveValue('-7.96662000');
  await expect(page.getByLabel('Bujur Lokasi Laporan')).toHaveValue('112.63263200');
  await fillPorts(page);
  await page.getByRole('textbox', { name: 'Catatan', exact: true }).fill(notes);
  await page.getByRole('button', { name: 'Save changes' }).click();
  await expect(page.getByText('Saved')).toBeVisible();

  await openAssignments(page);
  const completedRow = page.getByRole('row').filter({ hasText: boxId });
  await completedRow.getByRole('button', { name: 'Kirim Laporan' }).click();
  await page.getByRole('button', { name: 'Confirm' }).click();
  await expect(completedRow).toContainText(/Diajukan|Diajukan Ulang/);
}

test('technician can correct and resubmit after admin correction request', async ({ page }) => {
  const odcBoxId = `ODC-CORR-${Date.now()}`;

  await login(page, admin);
  await createOdcAssignment(page);
  await logout(page);

  await login(page, technician);
  await completeAndSubmitAssignment(page, odcBoxId, 'Dibuat untuk pengujian koreksi E2E.');
  await logout(page);

  await login(page, admin);
  await openAssignments(page);

  const adminRow = page.getByRole('row').filter({ hasText: odcBoxId });
  await adminRow.getByRole('button', { name: 'Minta Koreksi' }).click();
  await expect(page.getByRole('heading', { name: 'Minta Koreksi' })).toBeVisible();
  await page.getByLabel('Catatan Review').fill('Mohon perbarui catatan teknisi.');
  await page.getByRole('button', { name: 'Submit', exact: true }).click();
  await expect(adminRow.getByText('Perlu Koreksi')).toBeVisible();
  await logout(page);

  await login(page, technician);
  await completeAndSubmitAssignment(page, odcBoxId, 'Catatan teknisi diperbarui setelah permintaan koreksi.');

  await openAssignments(page);
  await expect(page.getByRole('row').filter({ hasText: odcBoxId }).getByText('Diajukan Ulang')).toBeVisible();
});
