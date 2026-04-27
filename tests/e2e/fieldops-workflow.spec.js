import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const technician = { email: 'tech@skynet.local', password: 'password' };
const admin = { email: 'admin@skynet.local', password: 'password' };

const reviewNotes = 'Approved by E2E workflow test';
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
  await page.getByLabel('Email address').fill(user.email);
  await page.getByLabel('Password').fill(user.password);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).toHaveURL(/\/admin$/);
}

async function logout(page) {
  await page.getByRole('button', { name: /user menu/i }).click();
  await page.getByRole('button', { name: 'Sign out' }).click();
  await expect(page).toHaveURL(/\/admin\/login/);
}

async function openResource(page, name) {
  await page.getByRole('link', { name }).click();
  await expect(page.getByRole('heading', { name, exact: true })).toBeVisible();
}

async function chooseComboboxOption(page, index, option) {
  await page.getByRole('combobox').nth(index).click();
  await page.getByRole('option', { name: option }).click();
}

async function uploadPhoto(page, selector, file, filename) {
  const uploadFinished = page.waitForResponse((response) => (
    response.url().includes('/livewire/update')
    && response.request().method() === 'POST'
    && response.status() === 200
  ));

  await page.locator(selector).setInputFiles(file);
  await uploadFinished;
  await expect(page.getByRole('alert').filter({ hasText: `${filename} Upload complete` })).toBeVisible();
}

async function fillPorts(page) {
  const statuses = ['Available', 'Used', 'Reserved', 'Broken', 'Unknown', 'Available', 'Used', 'Reserved'];
  const rows = page.locator('.fi-fo-repeater-item');

  await expect(rows).toHaveCount(16);

  for (const assetType of ['ODC', 'ODP']) {
    const assetOffset = assetType === 'ODC' ? 0 : 8;

    for (const portNumber of Array.from({ length: 8 }, (_, index) => index + 1)) {
      const row = rows.nth(assetOffset + portNumber - 1);

      await row.getByLabel('Asset type').selectOption({ label: assetType });
      await row.getByLabel('Port number').selectOption(`${portNumber}`);
      await row.getByLabel('Status').selectOption({ label: statuses[portNumber - 1] });
    }
  }
}

test('technician submission can be approved into official ODC and ODP assets', async ({ page }) => {
  const unique = Date.now();
  const odcBoxId = `ODC-E2E-${unique}`;
  const odpBoxId = `ODP-E2E-${unique}`;

  await login(page, technician);
  await openResource(page, 'Submissions');

  await page.getByRole('link', { name: 'New submission' }).click();
  await expect(page.getByRole('heading', { name: 'Create Submission' })).toBeVisible();

  await chooseComboboxOption(page, 0, 'Malang Deployment');
  await chooseComboboxOption(page, 1, 'Team Alpha');
  await chooseComboboxOption(page, 2, 'Malang Area 01');

  await page.getByLabel('ODC Box ID').fill(odcBoxId);
  await uploadPhoto(page, '[id="data.odc_photo_path"] input[type="file"]', odcPhoto, 'odc-photo.png');
  await page.getByLabel('Odc latitude').fill('-7.96662000');
  await page.getByLabel('Odc longitude').fill('112.63263200');

  await page.getByLabel('ODP Box ID').fill(odpBoxId);
  await uploadPhoto(page, '[id="data.odp_photo_path"] input[type="file"]', odpPhoto, 'odp-photo.png');
  await page.getByLabel('Odp latitude').fill('-7.96700000');
  await page.getByLabel('Odp longitude').fill('112.63300000');
  await page.getByLabel('Odp core color').selectOption({ label: 'Biru' });

  await fillPorts(page);
  await page.getByLabel('Notes').fill('Created by Playwright E2E workflow test.');
  await page.getByRole('button', { name: 'Create', exact: true }).click();

  await expect(page.getByText('Created')).toBeVisible();
  await expect(page.getByText(odcBoxId)).toBeVisible();
  await expect(page.getByText(odpBoxId)).toBeVisible();

  const row = page.getByRole('row').filter({ hasText: odcBoxId });
  await row.getByRole('button', { name: 'Submit' }).click();
  await page.getByRole('button', { name: 'Confirm' }).click();
  await expect(row.getByText('Submitted')).toBeVisible();

  await logout(page);

  await login(page, admin);
  await openResource(page, 'Submissions');

  const adminRow = page.getByRole('row').filter({ hasText: odcBoxId });
  await expect(adminRow).toContainText(odpBoxId);
  await adminRow.getByRole('button', { name: 'Approve' }).click();
  await expect(page.getByRole('heading', { name: 'Approve' })).toBeVisible();
  await page.getByLabel('Review notes').fill(reviewNotes);
  await page.getByRole('button', { name: 'Submit', exact: true }).click();
  await expect(page.getByText('Submission approved and assets updated.')).toBeVisible();
  await expect(adminRow.getByText('Approved')).toBeVisible();

  await openResource(page, 'ODC Assets');
  await expect(page.getByRole('row').filter({ hasText: odcBoxId })).toBeVisible();

  await openResource(page, 'ODP Assets');
  const odpRow = page.getByRole('row').filter({ hasText: odpBoxId });
  await expect(odpRow).toBeVisible();
  await expect(odpRow).toContainText('biru');

  const assertion = JSON.parse(artisanTinker(`
    $submission = App\\Models\\Submission::where('odc_box_id', '${odcBoxId}')->firstOrFail();
    $odc = App\\Models\\OdcAsset::where('box_id', '${odcBoxId}')->firstOrFail();
    $odp = App\\Models\\OdpAsset::where('box_id', '${odpBoxId}')->firstOrFail();
    echo json_encode([
      'submissionStatus' => $submission->status->value,
      'odcSourceSubmissionId' => (int) $odc->source_submission_id,
      'odpSourceSubmissionId' => (int) $odp->source_submission_id,
      'submissionId' => (int) $submission->id,
      'odcPortCount' => $odc->ports()->count(),
      'odpPortCount' => $odp->ports()->count(),
    ]);
  `));

  expect(assertion).toEqual({
    submissionStatus: 'approved',
    odcSourceSubmissionId: assertion.submissionId,
    odpSourceSubmissionId: assertion.submissionId,
    submissionId: assertion.submissionId,
    odcPortCount: 8,
    odpPortCount: 8,
  });
});
