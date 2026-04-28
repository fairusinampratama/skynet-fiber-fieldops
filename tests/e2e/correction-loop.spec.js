import { expect, test } from '@playwright/test';
import path from 'node:path';

const technician = { email: 'tech@skynet.local', password: 'password' };
const admin = { email: 'admin@skynet.local', password: 'password' };
const odcPhoto = path.resolve('tests/e2e/fixtures/odc-photo.png');
const odpPhoto = path.resolve('tests/e2e/fixtures/odp-photo.png');

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

async function openSubmissions(page) {
  await page.getByRole('complementary').getByRole('link', { name: 'Submissions' }).click();
  await expect(page.getByRole('heading', { name: 'Submissions', exact: true })).toBeVisible();
}

async function chooseRelationshipOption(page, option) {
  await page.getByRole('button', { name: 'Select an option' }).first().click();
  await page.getByRole('textbox', { name: 'Search' }).fill(option);
  await page.getByRole('option', { name: option, exact: true }).click();
}

async function uploadPhoto(page, index, file, filename) {
  await page.locator('input[type="file"]').nth(index).setInputFiles(file);
  await expect(page.getByRole('alert').filter({ hasText: `${filename} Upload complete` })).toBeVisible();
}

async function fillPorts(page) {
  const rows = page.locator('.fi-fo-repeater-item');
  await expect(rows).toHaveCount(16);

  for (const assetType of ['ODC', 'ODP']) {
    const assetOffset = assetType === 'ODC' ? 0 : 8;

    for (const portNumber of Array.from({ length: 8 }, (_, index) => index + 1)) {
      const row = rows.nth(assetOffset + portNumber - 1);

      await row.getByLabel('Asset type').selectOption({ label: assetType });
      await row.getByLabel('Port number').selectOption(`${portNumber}`);
      await row.getByLabel('Status').selectOption({ label: portNumber % 2 === 0 ? 'Used' : 'Available' });
    }
  }
}

async function createAndSubmitTechnicianSubmission(page, odcBoxId, odpBoxId) {
  await openSubmissions(page);
  await page.getByRole('link', { name: 'New submission' }).click();
  await expect(page.getByRole('heading', { name: 'Create Submission' })).toBeVisible();

  await chooseRelationshipOption(page, 'Malang Deployment');
  await chooseRelationshipOption(page, 'Team Alpha');
  await chooseRelationshipOption(page, 'Malang Area 01');

  await page.getByLabel('ODC Box ID').fill(odcBoxId);
  await uploadPhoto(page, 0, odcPhoto, 'odc-photo.png');
  await page.getByLabel('Odc latitude').fill('-7.96662000');
  await page.getByLabel('Odc longitude').fill('112.63263200');

  await page.getByLabel('ODP Box ID').fill(odpBoxId);
  await uploadPhoto(page, 1, odpPhoto, 'odp-photo.png');
  await page.getByLabel('Odp latitude').fill('-7.96700000');
  await page.getByLabel('Odp longitude').fill('112.63300000');
  await page.getByLabel('Odp core color').selectOption({ label: 'Biru' });

  await fillPorts(page);
  await page.getByRole('textbox', { name: 'Notes', exact: true }).fill('Created for correction-loop E2E.');
  await page.getByRole('button', { name: 'Create', exact: true }).click();
  await expect(page.getByText('Created')).toBeVisible();

  const row = page.getByRole('row').filter({ hasText: odcBoxId });
  await row.getByRole('button', { name: 'Submit' }).click();
  await page.getByRole('button', { name: 'Confirm' }).click();
  await expect(row.getByText('Submitted')).toBeVisible();
}

test('technician can correct and resubmit after admin correction request', async ({ page }) => {
  const unique = Date.now();
  const odcBoxId = `ODC-CORR-${unique}`;
  const odpBoxId = `ODP-CORR-${unique}`;

  await login(page, technician);
  await createAndSubmitTechnicianSubmission(page, odcBoxId, odpBoxId);
  await logout(page);

  await login(page, admin);
  await openSubmissions(page);

  const adminRow = page.getByRole('row').filter({ hasText: odcBoxId });
  await adminRow.getByRole('button', { name: 'Request Correction' }).click();
  await expect(page.getByRole('heading', { name: 'Request Correction' })).toBeVisible();
  await page.getByLabel('Review notes').fill('Please update the technician notes.');
  await page.getByRole('button', { name: 'Submit', exact: true }).click();
  await expect(adminRow.getByText('Correction Needed')).toBeVisible();
  await logout(page);

  await login(page, technician);
  await openSubmissions(page);

  const technicianRow = page.getByRole('row').filter({ hasText: odcBoxId });
  await expect(technicianRow).toContainText('Correction Needed');
  await technicianRow.getByRole('link', { name: 'Edit' }).click();
  await expect(page.getByRole('heading', { name: 'Edit Submission' })).toBeVisible();
  await page.getByRole('textbox', { name: 'Notes', exact: true }).fill('Technician notes updated after correction request.');
  await page.getByRole('button', { name: 'Save changes' }).click();
  await expect(page.getByText('Saved')).toBeVisible();

  await openSubmissions(page);
  const updatedRow = page.getByRole('row').filter({ hasText: odcBoxId });
  await updatedRow.getByRole('button', { name: 'Submit' }).click();
  await page.getByRole('button', { name: 'Confirm' }).click();
  await expect(updatedRow.getByText('Resubmitted')).toBeVisible();
});
