import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const BASE_URL = (process.env.BASE_URL || 'https://byebyepw.test:8443').replace(/\/$/, '');
const WP_ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
const WP_ADMIN_PASS = process.env.WP_ADMIN_PASS || 'admin123!';
const RESULTS_DIR = process.env.RESULTS_DIR || '/results';

fs.mkdirSync(RESULTS_DIR, { recursive: true });

const browser = await chromium.launch({
  headless: true,
  // Intentionally NO --ignore-certificate-errors: local CA is trusted in NSS + OS trust store.
});

const consoleWarnings = [];
const consoleErrors = [];
const pageErrors = [];

try {
  const context = await browser.newContext({
    ignoreHTTPSErrors: false,
    viewport: { width: 1280, height: 900 },
  });
  const page = await context.newPage();

  page.on('console', (msg) => {
    const text = msg.text();
    if (msg.type() === 'error') {
      consoleErrors.push(text);
    } else if (msg.type() === 'warning' || text.includes('JQMIGRATE')) {
      consoleWarnings.push(text);
    }
  });
  page.on('pageerror', (err) => pageErrors.push(err.message));

  // Attach CDP WebAuthn virtual authenticator (resident key + user verification)
  const cdp = await context.newCDPSession(page);
  await cdp.send('WebAuthn.enable');
  const { authenticatorId } = await cdp.send('WebAuthn.addVirtualAuthenticator', {
    options: {
      protocol: 'ctap2',
      transport: 'internal',
      hasResidentKey: true,
      hasUserVerification: true,
      isUserVerified: true,
      automaticPresenceSimulation: true,
    },
  });

  // 1. Load wp-login.php over strictly verified HTTPS
  const loginRes = await page.goto(`${BASE_URL}/wp-login.php`, { waitUntil: 'networkidle' });
  if (!loginRes || loginRes.status() !== 200) {
    throw new Error(`Expected HTTP 200 on wp-login.php, got ${loginRes ? loginRes.status() : 'null'}`);
  }

  const securityChecks = await page.evaluate(async () => {
    const isSecureContext = window.isSecureContext;
    const hasPublicKeyCredential = typeof window.PublicKeyCredential !== 'undefined';
    let uvpaa = false;
    if (hasPublicKeyCredential && typeof window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable === 'function') {
      uvpaa = await window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
    }
    return {
      origin: window.location.origin,
      protocol: window.location.protocol,
      isSecureContext,
      hasPublicKeyCredential,
      uvpaa,
      title: document.title,
    };
  });

  if (!securityChecks.isSecureContext || !securityChecks.hasPublicKeyCredential) {
    throw new Error(`WebAuthn secure context check failed: ${JSON.stringify(securityChecks)}`);
  }

  await page.screenshot({ path: path.join(RESULTS_DIR, '01-wp-login.png'), fullPage: true });

  // 2. Log into WordPress admin and visit Profile page
  await page.fill('#user_login', WP_ADMIN_USER);
  await page.fill('#user_pass', WP_ADMIN_PASS);
  await Promise.all([
    page.waitForURL(/\/wp-admin\//, { timeout: 20000 }),
    page.click('#wp-submit'),
  ]);

  await page.goto(`${BASE_URL}/wp-admin/profile.php`, { waitUntil: 'networkidle' });
  await page.screenshot({ path: path.join(RESULTS_DIR, '02-wp-profile.png'), fullPage: true });

  const adminBody = await page.locator('body').innerText();
  const hasHttpsWarning = adminBody.includes('Your site is not using HTTPS');
  if (hasHttpsWarning) {
    throw new Error('Unexpected HTTPS warning notice detected in wp-admin!');
  }

  const summary = {
    ok: true,
    baseUrl: BASE_URL,
    authenticatorId,
    securityChecks,
    hasHttpsWarning,
    consoleErrorCount: consoleErrors.length,
    consoleWarningCount: consoleWarnings.length,
    pageErrorCount: pageErrors.length,
    consoleErrors,
    consoleWarnings,
    pageErrors,
    screenshots: [
      path.join(RESULTS_DIR, '01-wp-login.png'),
      path.join(RESULTS_DIR, '02-wp-profile.png'),
    ],
  };

  console.log(JSON.stringify(summary, null, 2));
} finally {
  await browser.close();
}
