import { execFileSync } from 'node:child_process';

const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000';

function run(command, args) {
  execFileSync(command, args, {
    cwd: process.cwd(),
    stdio: 'inherit',
  });
}

async function waitForApp() {
  const loginUrl = `${baseURL}/admin/login`;
  const deadline = Date.now() + 60_000;
  let lastError;

  while (Date.now() < deadline) {
    try {
      const response = await fetch(loginUrl, { method: 'HEAD' });

      if (response.ok) {
        return;
      }

      lastError = new Error(`Unexpected status ${response.status} from ${loginUrl}`);
    } catch (error) {
      lastError = error;
    }

    await new Promise((resolve) => setTimeout(resolve, 1_000));
  }

  throw lastError ?? new Error(`Timed out waiting for ${loginUrl}`);
}

export default async function globalSetup() {
  run('docker', ['compose', 'up', '-d']);
  await waitForApp();
  run('docker', ['compose', 'exec', '-T', 'app', 'php', 'artisan', 'migrate:fresh', '--seed', '--force']);
  await waitForApp();
}
