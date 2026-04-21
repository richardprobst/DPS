import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright';

const siteUrl = process.env.AGENDA_SITE_URL || 'https://desi.pet';
const agendaUrl = new URL('/agenda-de-atendimentos/', siteUrl).toString();
const adminHubUrl = new URL('/wp-admin/admin.php?page=dps-agenda-hub', siteUrl).toString();
const loginUrl = new URL('/wp-login.php', siteUrl).toString();
const ajaxUrl = new URL('/wp-admin/admin-ajax.php', siteUrl).toString();
const qaPetName = process.env.AGENDA_QA_PET_NAME || 'QA Smoke Pet';
const qaDate = process.env.AGENDA_QA_DATE || '';
const outputDir = path.resolve(process.env.AGENDA_SMOKE_OUTPUT_DIR || 'docs/screenshots/2026-04-20/agenda-hardening-smoke');
const operatorSessionFile = process.env.AGENDA_OPERATOR_SESSION_FILE || '';
const adminSessionFile = process.env.AGENDA_ADMIN_SESSION_FILE || '';

const knownConsoleNoise = [
  /mixpanel/i,
  /google ads/i,
  /googlesyndication/i,
  /accounts\.google\.com.*frame-ancestors/i,
  /JQMIGRATE: jQuery is not compatible with Quirks Mode/i,
  /JQMIGRATE: 'ready' event is deprecated/i,
  /JQMIGRATE: jQuery\.fn\.click\(\) event shorthand is deprecated/i,
];

const results = [];

function recordScenario(name, status, detail = '') {
  results.push({ name, status, detail });
}

function extractJsonPayload(text) {
  const cleaned = text.replace(/^\uFEFF+/, '').trim();
  const objectIndex = cleaned.indexOf('{');
  const arrayIndex = cleaned.indexOf('[');
  const candidates = [objectIndex, arrayIndex].filter((value) => value >= 0).sort((left, right) => left - right);

  if (!candidates.length) {
    throw new Error(`Unable to locate JSON payload in response: ${cleaned.slice(0, 300)}`);
  }

  return JSON.parse(cleaned.slice(candidates[0]));
}

async function readJsonResponse(response) {
  return extractJsonPayload(await response.text());
}

function requireEnv(name) {
  const value = process.env[name];
  if (!value) {
    throw new Error(`Missing required environment variable: ${name}`);
  }
  return value;
}

function createIssueCollector(page, issues) {
  page.on('console', (message) => {
    if (!['error', 'warning'].includes(message.type())) {
      return;
    }

    const text = message.text().trim();
    if (!text || knownConsoleNoise.some((pattern) => pattern.test(text))) {
      return;
    }

    issues.push(`[${message.type()}] ${text}`);
  });

  page.on('pageerror', (error) => {
    if (error && error.message === 'Object') {
      return;
    }

    issues.push(`[pageerror] ${error.message}`);
  });
}

async function saveFullPage(page, name) {
  await page.screenshot({
    path: path.join(outputDir, name),
    fullPage: true,
  });
}

async function assertNoHorizontalOverflow(page) {
  const metrics = await page.evaluate(() => {
    const root = document.documentElement;
    const body = document.body;

    return {
      rootClientWidth: root ? root.clientWidth : 0,
      rootScrollWidth: root ? root.scrollWidth : 0,
      bodyScrollWidth: body ? body.scrollWidth : 0,
    };
  });

  assert.ok(
    Math.max(metrics.rootScrollWidth, metrics.bodyScrollWidth) <= metrics.rootClientWidth + 2,
    `Horizontal overflow detected: ${JSON.stringify(metrics)}`
  );
}

async function assertBodyContains(page, pattern) {
  const text = await page.locator('body').innerText();
  assert.match(text, pattern);
}

async function assertVisible(locator, timeout = 15000) {
  await locator.waitFor({ state: 'visible', timeout });
}

async function applyStoredSession(context, sessionFile) {
  const raw = await fs.readFile(sessionFile, 'utf8');
  const parsed = JSON.parse(raw);
  const cookies = Array.isArray(parsed) ? parsed : parsed.cookies;

  assert.ok(Array.isArray(cookies) && cookies.length > 0, `Session file ${sessionFile} does not contain cookies.`);
  await context.addCookies(cookies);
}

async function authenticatePage(context, page, auth, redirectUrl = agendaUrl) {
  if (auth.sessionFile) {
    await applyStoredSession(context, auth.sessionFile);
    await page.goto(redirectUrl, { waitUntil: 'networkidle' });
    return;
  }

  await login(page, auth.username, auth.password, redirectUrl);
}

async function login(page, username, password, redirectUrl = agendaUrl) {
  await page.goto(loginUrl, { waitUntil: 'domcontentloaded' });
  await page.evaluate((nextUrl) => {
    const redirectField = document.querySelector('input[name="redirect_to"]');
    if (redirectField) {
      redirectField.value = nextUrl;
    }
  }, redirectUrl);
  await page.fill('#user_login', username);
  await page.fill('#user_pass', password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }),
    page.click('#wp-submit'),
  ]);

  const bodyText = await page.locator('body').innerText();
  assert.doesNotMatch(bodyText, /Erro|error/i);
}

async function openAgendaTab(page, tab) {
  const button = page.locator(`.dps-agenda-tab-button[data-tab="${tab}"]`).first();
  await assertVisible(button);
  await button.click();
  const selected = await button.getAttribute('aria-selected');
  assert.equal(selected, 'true', `Tab ${tab} was not selected.`);
}

async function findQaRow(page) {
  const rows = page.locator('tr[data-appt-id]');
  const count = await rows.count();
  const expected = qaPetName.trim().toLowerCase();

  for (let index = 0; index < count; index += 1) {
    const row = rows.nth(index);
    const text = (await row.innerText()).trim().toLowerCase();

    if (!text.includes(expected)) {
      continue;
    }

    if (await row.isVisible()) {
      return row;
    }
  }

  throw new Error(`Visible QA row not found for "${qaPetName}".`);
}

async function scopeLooksEmpty(page) {
  const bodyText = await page.locator('body').innerText();
  return /Nenhum atendimento neste recorte|abra a agenda completa/i.test(bodyText);
}

async function openFullAgenda(page) {
  const fullAgendaLink = page
    .locator('a[href*="show_all=1"]')
    .filter({ hasText: /Agenda completa|Ver agenda completa/i })
    .first();

  if ((await fullAgendaLink.count()) > 0 && (await fullAgendaLink.isVisible())) {
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle' }),
      fullAgendaLink.click(),
    ]);
    return;
  }

  const fullAgendaUrl = new URL(agendaUrl);
  fullAgendaUrl.searchParams.set('show_all', '1');
  await page.goto(fullAgendaUrl.toString(), { waitUntil: 'networkidle' });
}

async function openQaDate(page) {
  if (!qaDate) {
    return false;
  }

  const qaAgendaUrl = new URL(agendaUrl);
  qaAgendaUrl.searchParams.set('dps_date', qaDate);
  qaAgendaUrl.searchParams.set('view', 'day');
  qaAgendaUrl.searchParams.delete('show_all');
  await page.goto(qaAgendaUrl.toString(), { waitUntil: 'networkidle' });
  return true;
}

async function ensureQaRowVisible(page, tab) {
  if (tab) {
    await openAgendaTab(page, tab);
  }

  try {
    return await findQaRow(page);
  } catch (initialError) {
    const alreadyInFullAgenda = page.url().includes('show_all=1');
    const emptyScope = await scopeLooksEmpty(page);

    if (emptyScope || !alreadyInFullAgenda) {
      await openFullAgenda(page);

      if (tab) {
        await openAgendaTab(page, tab);
      }

      try {
        return await findQaRow(page);
      } catch (fullAgendaError) {
        if (!(await openQaDate(page))) {
          throw fullAgendaError;
        }

        if (tab) {
          await openAgendaTab(page, tab);
        }

        return await findQaRow(page);
      }
    }

    if (!(await openQaDate(page))) {
      throw initialError;
    }

    if (tab) {
      await openAgendaTab(page, tab);
    }

    return await findQaRow(page);
  }
}

async function updateAppointmentStatus(page, row, nextStatus) {
  const statusDropdown = row.locator('.dps-status-dropdown').first();
  await assertVisible(statusDropdown);

  const appointmentId = await row.getAttribute('data-appt-id');
  assert.ok(appointmentId, 'Appointment id is missing from the QA row.');

  const currentStatus = await statusDropdown.inputValue();
  if (currentStatus === nextStatus) {
    return { appointmentId, currentStatus };
  }

  const statusResponsePromise = page.waitForResponse((response) => {
    return response.url().includes('admin-ajax.php') && (response.request().postData() || '').includes('action=dps_update_status');
  });

  await statusDropdown.selectOption(nextStatus);
  const statusPayload = await readJsonResponse(await statusResponsePromise);
  assert.equal(statusPayload.success, true, `Status update to ${nextStatus} should succeed.`);

  await page.waitForFunction(
    ({ apptId, expectedStatus }) => {
      const rowElement = Array.from(document.querySelectorAll('tr[data-appt-id]')).find((item) => {
        return item.getAttribute('data-appt-id') === apptId && (item.offsetWidth || item.offsetHeight || item.getClientRects().length);
      });
      const select = rowElement ? rowElement.querySelector('.dps-status-dropdown') : null;
      return !!select && select.value === expectedStatus;
    },
    { apptId: appointmentId, expectedStatus: nextStatus }
  );

  return { appointmentId, currentStatus };
}

async function dismissDialogWithEscape(page) {
  await page.keyboard.press('Escape');
  await page.waitForTimeout(250);
}

async function runGuestScenario(browser) {
  const context = await browser.newContext();
  const page = await context.newPage();
  const issues = [];
  createIssueCollector(page, issues);

  await page.setViewportSize({ width: 1200, height: 1400 });
  await page.goto(agendaUrl, { waitUntil: 'networkidle' });
  await assertBodyContains(page, /Fazer login|Você precisa estar logado/i);
  await assertNoHorizontalOverflow(page);
  await saveFullPage(page, 'guest-1200.png');

  await page.setViewportSize({ width: 375, height: 1200 });
  await page.goto(agendaUrl, { waitUntil: 'networkidle' });
  await assertBodyContains(page, /Fazer login|Você precisa estar logado/i);
  await assertNoHorizontalOverflow(page);
  await saveFullPage(page, 'guest-375.png');

  const guestAjax = await context.request.post(ajaxUrl, {
    form: {
      action: 'dps_get_services_details',
      appt_id: '0',
      nonce: 'invalid',
    },
  });
  const guestText = (await guestAjax.text()).replace(/^\uFEFF+/, '').trim();
  assert.ok(
    guestText === '0' || guestText.includes('"success":false'),
    `Guest AJAX request should not expose data. Received: ${guestText}`
  );
  assert.equal(issues.length, 0, `Unexpected console issues for guest scenario:\n${issues.join('\n')}`);

  await context.close();
}

async function runOperatorAccessScenario(browser, auth) {
  const context = await browser.newContext();
  const page = await context.newPage();
  const issues = [];
  createIssueCollector(page, issues);

  await authenticatePage(context, page, auth, agendaUrl);

  await page.goto(agendaUrl, { waitUntil: 'networkidle' });
  await assertVisible(page.locator('.dps-agenda-wrapper'));
  await assertNoHorizontalOverflow(page);
  await saveFullPage(page, 'operator-agenda-1200.png');

  await page.goto(adminHubUrl, { waitUntil: 'networkidle' });
  await assertBodyContains(page, /Você não tem permissão|Acesso negado|Sem permissão para acessar esta página|Sorry, you are not allowed/i);
  await assertNoHorizontalOverflow(page);
  await saveFullPage(page, 'operator-admin-denied.png');

  const relevantIssues = issues.filter((issue) => !issue.includes('Failed to load resource: the server responded with a status of 403'));
  assert.equal(relevantIssues.length, 0, `Unexpected console issues for operator access scenario:\n${relevantIssues.join('\n')}`);
  await context.close();
}

async function runAdminScenario(browser, auth) {
  const context = await browser.newContext();
  const page = await context.newPage();
  const issues = [];
  createIssueCollector(page, issues);

  await authenticatePage(context, page, auth, agendaUrl);

  await page.goto(agendaUrl, { waitUntil: 'networkidle' });
  await assertVisible(page.locator('.dps-agenda-wrapper'));
  await assertNoHorizontalOverflow(page);

  await page.setViewportSize({ width: 1920, height: 1400 });
  await page.goto(adminHubUrl, { waitUntil: 'networkidle' });
  await assertBodyContains(page, /Agenda|Dashboard/i);
  await assertNoHorizontalOverflow(page);
  await saveFullPage(page, 'admin-hub-1920.png');

  assert.equal(issues.length, 0, `Unexpected console issues for admin scenario:\n${issues.join('\n')}`);
  await context.close();
}

async function runOperatorFlowScenario(browser, auth) {
  const context = await browser.newContext();
  const page = await context.newPage();
  const issues = [];
  createIssueCollector(page, issues);

  await authenticatePage(context, page, auth, agendaUrl);
  await page.setViewportSize({ width: 1200, height: 1400 });
  await page.goto(agendaUrl, { waitUntil: 'networkidle' });
  await assertNoHorizontalOverflow(page);

  let row = await ensureQaRowVisible(page, 'visao-rapida');
  const servicesTrigger = row.locator('.dps-services-link, .dps-services-popup-btn').first();
  if ((await servicesTrigger.count()) > 0) {
    await servicesTrigger.click();
    await assertVisible(page.locator('.dps-agenda-dialog-overlay, .dps-services-modal'));
    await saveFullPage(page, 'operator-services-modal.png');
    await dismissDialogWithEscape(page);
    const focusReturned = await servicesTrigger.evaluate((element) => element === document.activeElement);
    assert.equal(focusReturned, true, 'Focus did not return to the services trigger after closing the modal.');
  }

  row = await ensureQaRowVisible(page, 'operacao');
  const statusDropdown = row.locator('.dps-status-dropdown').first();
  await assertVisible(statusDropdown);
  const appointmentId = await row.getAttribute('data-appt-id');
  const versionValue = await statusDropdown.getAttribute('data-appt-version');
  assert.ok(appointmentId, 'Appointment id is missing from the QA row.');

  const invalidNoncePayload = await page.evaluate(async ({ apptId, version }) => {
    const parsePayload = (text) => {
      const cleaned = text.replace(/^\uFEFF+/, '').trim();
      const objectIndex = cleaned.indexOf('{');
      const arrayIndex = cleaned.indexOf('[');
      const candidates = [objectIndex, arrayIndex].filter((value) => value >= 0).sort((left, right) => left - right);

      if (!candidates.length) {
        throw new Error(`Unable to parse JSON payload: ${cleaned.slice(0, 300)}`);
      }

      return JSON.parse(cleaned.slice(candidates[0]));
    };

    const params = new URLSearchParams();
    params.set('action', 'dps_update_status');
    params.set('id', apptId);
    params.set('status', 'finalizado');
    params.set('version', version);
    params.set('nonce', 'invalid');
    const response = await fetch(window.DPS_AG_Addon.ajax, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: params.toString(),
      credentials: 'same-origin',
    });
    return parsePayload(await response.text());
  }, { apptId: appointmentId, version: versionValue || '1' });
  assert.equal(invalidNoncePayload.success, false, 'Invalid nonce should fail.');
  assert.equal(invalidNoncePayload.data?.error_code, 'invalid_nonce', 'Invalid nonce should return error_code=invalid_nonce.');

  const conflictPayload = await page.evaluate(async ({ apptId }) => {
    const parsePayload = (text) => {
      const cleaned = text.replace(/^\uFEFF+/, '').trim();
      const objectIndex = cleaned.indexOf('{');
      const arrayIndex = cleaned.indexOf('[');
      const candidates = [objectIndex, arrayIndex].filter((value) => value >= 0).sort((left, right) => left - right);

      if (!candidates.length) {
        throw new Error(`Unable to parse JSON payload: ${cleaned.slice(0, 300)}`);
      }

      return JSON.parse(cleaned.slice(candidates[0]));
    };

    const params = new URLSearchParams();
    params.set('action', 'dps_update_status');
    params.set('id', apptId);
    params.set('status', 'finalizado');
    params.set('version', '999');
    params.set('nonce', window.DPS_AG_Addon.nonce_status);
    const response = await fetch(window.DPS_AG_Addon.ajax, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: params.toString(),
      credentials: 'same-origin',
    });
    return parsePayload(await response.text());
  }, { apptId: appointmentId });
  assert.equal(conflictPayload.success, false, 'Version conflict should fail.');
  assert.equal(conflictPayload.data?.error_code, 'version_conflict', 'Conflict should return error_code=version_conflict.');

  await updateAppointmentStatus(page, row, 'pendente');
  row = await ensureQaRowVisible(page, 'operacao');
  let expandButton = row.locator('.dps-expand-panels-btn').first();
  await expandButton.click();
  assert.equal(await expandButton.getAttribute('aria-expanded'), 'true', 'Expanded panel button did not toggle.');

  let detailRow = page.locator(`.dps-detail-row[data-appt-id="${appointmentId}"]`).first();
  await assertVisible(detailRow);
  assert.equal(await detailRow.locator('.dps-checklist-panel').count(), 0, 'Checklist must stay hidden while the appointment is pending.');

  await updateAppointmentStatus(page, row, 'finalizado');
  row = await ensureQaRowVisible(page, 'operacao');
  expandButton = row.locator('.dps-expand-panels-btn').first();
  detailRow = page.locator(`.dps-detail-row[data-appt-id="${appointmentId}"]`).first();
  if ((await expandButton.getAttribute('aria-expanded')) !== 'true') {
    await expandButton.click();
  }
  assert.equal(await expandButton.getAttribute('aria-expanded'), 'true', 'Expanded panel button should stay open after finalizing.');
  await assertVisible(detailRow);
  assert.equal(await detailRow.locator('.dps-checklist-panel').count(), 1, 'Checklist must appear after finalizing the appointment.');
  await assertNoHorizontalOverflow(page);
  await saveFullPage(page, 'operator-operacao-expandido.png');

  const firstChecklistDone = detailRow.locator('.dps-checklist-btn--done').first();
  if ((await firstChecklistDone.count()) > 0) {
    const progressBefore = await detailRow.locator('.dps-checklist-progress-text').innerText();
    await firstChecklistDone.click();
    await page.waitForFunction(
      ({ apptId, before }) => {
        const detail = document.querySelector(`.dps-detail-row[data-appt-id="${apptId}"]`);
        const progress = detail ? detail.querySelector('.dps-checklist-progress-text') : null;
        return !!progress && progress.textContent.trim() !== before;
      },
      { apptId: appointmentId, before: progressBefore }
    );
    const progressAfter = await detailRow.locator('.dps-checklist-progress-text').innerText();
    assert.notEqual(progressAfter, progressBefore, 'Checklist progress did not advance.');
  }

  const checkinButton = detailRow.locator('.dps-checkin-btn--checkin').first();
  if ((await checkinButton.count()) > 0) {
    await checkinButton.click();
    await page.waitForFunction((apptId) => {
      const detail = document.querySelector(`.dps-detail-row[data-appt-id="${apptId}"]`);
      const status = detail ? detail.querySelector('.dps-checkin-status') : null;
      return !!status && /check-in/i.test(status.textContent);
    }, appointmentId);
    const statusText = await detailRow.locator('.dps-checkin-status').innerText();
    assert.match(statusText, /Check-in/i);
  }

  const checkoutButton = detailRow.locator('.dps-checkin-btn--checkout').first();
  if ((await checkoutButton.count()) > 0) {
    await checkoutButton.click();
    await page.waitForFunction((apptId) => {
      const detail = document.querySelector(`.dps-detail-row[data-appt-id="${apptId}"]`);
      const status = detail ? detail.querySelector('.dps-checkin-status') : null;
      return !!status && /check-out/i.test(status.textContent);
    }, appointmentId);
    const statusText = await detailRow.locator('.dps-checkin-status').innerText();
    assert.match(statusText, /Check-out/i);
  }

  row = await ensureQaRowVisible(page, 'detalhes');
  const taxidogRequest = row.locator('.dps-taxidog-request-btn').first();
  if ((await taxidogRequest.count()) > 0) {
    await taxidogRequest.click();
    const confirm = page.locator('[data-dialog-action="confirm"]').first();
    await assertVisible(confirm);
    await confirm.click();
    await page.waitForFunction((apptId) => {
      const rowElement = Array.from(document.querySelectorAll('tr[data-appt-id]')).find((item) => {
        return item.getAttribute('data-appt-id') === apptId && (item.offsetWidth || item.offsetHeight || item.getClientRects().length);
      });
      return !!rowElement && /Solicitado|Abrir no mapa|TaxiDog/i.test(rowElement.textContent || '');
    }, appointmentId);
    row = await ensureQaRowVisible(page, 'detalhes');
    assert.match(await row.innerText(), /Solicitado|Abrir no mapa|TaxiDog/i);
    const taxidogToast = page.locator('.dps-toast:visible').first();
    if ((await taxidogToast.count()) > 0) {
      assert.match(await taxidogToast.innerText(), /TaxiDog|Status atualizado|sucesso/i);
    }
  }

  row = await ensureQaRowVisible(page, 'visao-rapida');
  const resendButton = row.locator('.dps-resend-payment-btn').first();
  if ((await resendButton.count()) > 0) {
    await resendButton.click();
    const confirm = page.locator('[data-dialog-action="confirm"]').first();
    await assertVisible(confirm);
    await confirm.click();
    const paymentToast = page.locator('.dps-toast:visible').first();
    await assertVisible(paymentToast);
    assert.match(await paymentToast.innerText(), /reenviado|reenvio|pagamento/i);
    await assertVisible(row.locator('.dps-payment-attempt-summary'));
  }

  const breakpoints = [
    { width: 375, height: 1200, file: 'operator-operacao-375.png' },
    { width: 600, height: 1300, file: 'operator-operacao-600.png' },
    { width: 840, height: 1300, file: 'operator-operacao-840.png' },
    { width: 1200, height: 1400, file: 'operator-operacao-1200.png' },
    { width: 1920, height: 1400, file: 'operator-operacao-1920.png' },
  ];

  for (const breakpoint of breakpoints) {
    await page.setViewportSize({ width: breakpoint.width, height: breakpoint.height });
    await page.goto(agendaUrl, { waitUntil: 'networkidle' });
    await ensureQaRowVisible(page, 'operacao');
    if (breakpoint.width === 375) {
      const mobileRow = await ensureQaRowVisible(page, 'operacao');
      const mobileExpandButton = mobileRow.locator('.dps-expand-panels-btn').first();
      await mobileExpandButton.click();
      const mobileAppointmentId = await mobileRow.getAttribute('data-appt-id');
      const mobileDetailRow = page.locator(`.dps-detail-row[data-appt-id="${mobileAppointmentId}"]`).first();
      await assertVisible(mobileDetailRow);
      const mobileDisplay = await mobileDetailRow.evaluate((element) => window.getComputedStyle(element).display);
      assert.notEqual(mobileDisplay, 'table-row', 'Expanded mobile detail row must not render as table-row.');
    }
    await assertNoHorizontalOverflow(page);
    await saveFullPage(page, breakpoint.file);
  }

  assert.equal(issues.length, 0, `Unexpected console issues for operator flow scenario:\n${issues.join('\n')}`);
  await context.close();
}

async function main() {
  await fs.mkdir(outputDir, { recursive: true });

  const operatorAuth = operatorSessionFile
    ? { sessionFile: operatorSessionFile }
    : {
        username: requireEnv('AGENDA_OPERATOR_USER'),
        password: requireEnv('AGENDA_OPERATOR_PASSWORD'),
      };
  const adminAuth = adminSessionFile
    ? { sessionFile: adminSessionFile }
    : {
        username: requireEnv('AGENDA_ADMIN_USER'),
        password: requireEnv('AGENDA_ADMIN_PASSWORD'),
      };

  const browser = await chromium.launch({ headless: true });

  try {
    await runGuestScenario(browser);
    recordScenario('guest', 'passed');

    await runOperatorAccessScenario(browser, operatorAuth);
    recordScenario('operator-access', 'passed');

    await runAdminScenario(browser, adminAuth);
    recordScenario('admin', 'passed');

    await runOperatorFlowScenario(browser, operatorAuth);
    recordScenario('operator-flow', 'passed');
  } catch (error) {
    recordScenario('failure', 'failed', error instanceof Error ? error.stack || error.message : String(error));
    throw error;
  } finally {
    await browser.close();
    await fs.writeFile(
      path.join(outputDir, 'smoke-report.json'),
      JSON.stringify(
        {
          generatedAt: new Date().toISOString(),
          siteUrl,
          agendaUrl,
          adminHubUrl,
          results,
        },
        null,
        2
      ),
      'utf8'
    );
  }
}

main().catch((error) => {
  console.error(error instanceof Error ? error.stack || error.message : error);
  process.exit(1);
});
