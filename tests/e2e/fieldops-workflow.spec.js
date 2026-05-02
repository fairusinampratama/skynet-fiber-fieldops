import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const technician = { email: 'tech@skynet.local', password: 'password' };
const admin = { email: 'admin@skynet.local', password: 'password' };

const odcPhoto = path.resolve('tests/e2e/fixtures/odc-photo.png');
const odpPhoto = path.resolve('tests/e2e/fixtures/odp-photo.png');

function artisanTinker(statement) {
  return execFileSync(
    'docker',
    ['compose', 'exec', '-T', 'app', 'php', 'artisan', 'tinker', '--execute', statement.replace(/\s+/g, ' ').trim()],
    { cwd: process.cwd(), encoding: 'utf8' },
  ).trim();
}

async function login(page, user) {
  await page.goto('/admin/login');
  await page.getByLabel(/Alamat email/i).fill(user.email);
  await page.locator('input[type="password"]').fill(user.password);
  await page.getByRole('button', { name: /Masuk/i }).click();
  await expect(page).toHaveURL(/\/admin$/);
}

async function logout(page) {
  await page.getByRole('button', { name: /user menu/i }).click();
  await page.getByRole('button', { name: /Keluar/i }).click();
  await expect(page).toHaveURL(/\/admin\/login/);
}

async function openAssignments(page) {
  await page.getByRole('link', { name: /Penugasan Lapangan/i }).click();
  await expect(page.getByRole('heading', { name: /Penugasan Lapangan/i, exact: true })).toBeVisible();
}

async function chooseRelationshipOption(page, option) {
  // Use a more robust way to find Filament select buttons
  await page.locator('.fi-fo-select button').filter({ hasText: /Pilih opsi/i }).first().click();
  await page.getByRole('textbox', { name: /Cari/i }).fill(option);
  await page.getByRole('option', { name: option, exact: true }).click();
}

async function createAssignment(page, assetType) {
  await openAssignments(page);
  await page.getByRole('link', { name: /Penugasan Baru/i }).click();
  await expect(page.getByRole('heading', { name: /Penugasan Lapangan/i })).toBeVisible();

  await chooseRelationshipOption(page, 'Malang Deployment');
  await chooseRelationshipOption(page, 'Teknisi Lapangan');
  await chooseRelationshipOption(page, 'Malang Area 01');
  await page.getByLabel(/Jenis Aset/i).selectOption({ label: assetType });
  await page.getByLabel(/Lintang Titik Tugas/i).fill('-7.96660000');
  await page.getByLabel(/Bujur Titik Tugas/i).fill('112.63260000');
  await page.getByRole('button', { name: /Buat/i, exact: true }).click();
  await expect(page.getByText(/Berhasil dibuat/i)).toBeVisible();
}

async function fillPorts(page) {
  const rows = page.locator('.fi-fo-repeater-item');
  await expect(rows).toHaveCount(8);

  for (const portNumber of Array.from({ length: 8 }, (_, index) => index + 1)) {
    const row = rows.nth(portNumber - 1);

    await row.getByLabel(/Nomor Port/i).selectOption(`${portNumber}`);
    await row.getByLabel(/Status/i).selectOption({ label: portNumber % 2 === 0 ? 'Terpakai' : 'Tersedia' });
  }
}

async function completeAssignment(page, boxId, photo, coreColor = null) {
  await page.context().grantPermissions(['geolocation']);
  await page.context().setGeolocation({ latitude: -7.96662, longitude: 112.632632 });

  await openAssignments(page);
  const row = page.getByRole('row').filter({ hasText: /Ditugaskan/i }).first();
  await row.getByRole('link', { name: /Edit/i }).click();
  await expect(page.getByRole('heading', { name: /Penugasan Lapangan/i })).toBeVisible();

  await page.getByLabel(/Box ID/i).fill(boxId);
  await page.locator('input[type="file"]').setInputFiles(photo);
  await page.getByRole('button', { name: /Ambil Lokasi GPS/i }).click();
  await expect(page.getByLabel(/Lintang Lokasi Laporan/i)).toHaveValue('-7.96662000');
  await expect(page.getByLabel(/Bujur Lokasi Laporan/i)).toHaveValue('112.63263200');

  if (coreColor) {
    await page.getByLabel(/Warna Core ODP/i).selectOption({ label: coreColor });
  }

  await fillPorts(page);
  await page.getByRole('textbox', { name: /Catatan/i, exact: true }).fill(`Selesai ${boxId} via Playwright.`);
  await page.getByRole('button', { name: /Simpan perubahan/i }).click();
  await expect(page.getByText(/Berhasil disimpan/i)).toBeVisible();

  await openAssignments(page);
  const completedRow = page.getByRole('row').filter({ hasText: boxId });
  await completedRow.getByRole('button', { name: /Kirim Laporan/i }).click();
  await page.getByRole('button', { name: /Konfirmasi/i }).click();
  await expect(completedRow.getByText(/Diajukan/i)).toBeVisible();
}

async function approveAssignment(page, boxId) {
  await openAssignments(page);
  const row = page.getByRole('row').filter({ hasText: boxId });
  await row.getByRole('button', { name: /Setujui/i }).click();
  await page.getByLabel(/Catatan Review/i).fill('Disetujui oleh pengujian E2E.');
  await page.getByRole('button', { name: /Kirim/i, exact: true }).click();
  await expect(page.getByText(/Penugasan disetujui/i)).toBeVisible();
  await expect(row.getByText(/Disetujui/i)).toBeVisible();
}

test('admin assignments can be completed by technician and approved into official assets', async ({ page }) => {
  const unique = Date.now();
  const odcBoxId = `ODC-E2E-${unique}`;
  const odpBoxId = `ODP-E2E-${unique}`;

  await login(page, admin);
  await createAssignment(page, 'ODC');
  await createAssignment(page, 'ODP');
  await logout(page);

  await login(page, technician);
  await completeAssignment(page, odcBoxId, odcPhoto);
  await completeAssignment(page, odpBoxId, odpPhoto, 'Biru');
  await logout(page);

  await login(page, admin);
  await approveAssignment(page, odcBoxId);
  await approveAssignment(page, odpBoxId);

  await page.getByRole('link', { name: /Aset ODC/i }).click();
  await expect(page.getByRole('row').filter({ hasText: odcBoxId })).toBeVisible();

  await page.getByRole('link', { name: /Aset ODP/i }).click();
  const odpRow = page.getByRole('row').filter({ hasText: odpBoxId });
  await expect(odpRow).toBeVisible();
  await expect(odpRow).toContainText(/Biru/i);

  const assertionString = artisanTinker(`
    $odcSubmission = App\\Models\\Submission::where('box_id', '${odcBoxId}')->firstOrFail();
    $odpSubmission = App\\Models\\Submission::where('box_id', '${odpBoxId}')->firstOrFail();
    $odc = App\\Models\\OdcAsset::where('box_id', '${odcBoxId}')->firstOrFail();
    $odp = App\\Models\\OdpAsset::where('box_id', '${odpBoxId}')->firstOrFail();
    echo json_encode([
      'odcSubmissionStatus' => $odcSubmission->status->value,
      'odpSubmissionStatus' => $odpSubmission->status->value,
      'odcSourceSubmissionId' => (int) $odc->source_submission_id,
      'odpSourceSubmissionId' => (int) $odp->source_submission_id,
      'odcSubmissionId' => (int) $odcSubmission->id,
      'odpSubmissionId' => (int) $odpSubmission->id,
      'odcPortCount' => $odc->ports()->count(),
      'odpPortCount' => $odp->ports()->count(),
    ]);
  `);
  
  const assertion = JSON.parse(assertionString);

  expect(assertion).toEqual({
    odcSubmissionStatus: 'approved',
    odpSubmissionStatus: 'approved',
    odcSourceSubmissionId: assertion.odcSubmissionId,
    odpSourceSubmissionId: assertion.odpSubmissionId,
    odcSubmissionId: assertion.odcSubmissionId,
    odpSubmissionId: assertion.odpSubmissionId,
    odcPortCount: 8,
    odpPortCount: 8,
  });
});
