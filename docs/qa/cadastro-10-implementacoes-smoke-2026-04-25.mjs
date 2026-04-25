import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const rootDir = process.env.DPS_QA_ROOT || process.cwd();
const outputDir = path.join(rootDir, 'docs', 'screenshots', '2026-04-25');
const registrationUrl = process.env.DPS_REGISTRATION_URL || 'https://desi.pet/cadastro/';
const wpUser = process.env.DPS_WP_USER || '';
const wpPass = process.env.DPS_WP_PASS || '';
const runRealSubmit = process.env.DPS_REG_REAL_SUBMIT === '1';
const suffix = process.env.DPS_QA_SUFFIX || String(Date.now());
const resultPath = path.join(outputDir, 'cadastro-10melhorias-runtime-check.json');
const petPhotoPath = path.join(os.tmpdir(), `dps-pet-photo-${suffix}.png`);
const petPhotoBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAIAAABMXPacAAAAfklEQVR4nO3YwQnAIBAEQef+65yVBIMtkQ94QGbWwqxz2gq4j1k9AOA/EIBCAQoFKBSgUIBCAQoFKBSgUIBCAQoFKBSgUIBCAQoFKBSgUIBCAQoFKBSgUIBCAQoFKBSgUIBCAQoFKBSgUIBCAQoFKBSgUIBCAQoFKBQ49QANZQH7B1Wd4QAAAABJRU5ErkJggg==';

if (!wpUser || !wpPass) {
    throw new Error('DPS_WP_USER and DPS_WP_PASS are required');
}

fs.mkdirSync(outputDir, { recursive: true });
fs.writeFileSync(petPhotoPath, Buffer.from(petPhotoBase64, 'base64'));

const testData = {
    suffix,
    clientName: `Cliente Codex Real Cadastro ${suffix}`,
    phone: `(11) 9${String(Date.now()).slice(-8, -4)}-${String(Date.now()).slice(-4)}`,
    address: 'Rua Codex QA, 123, Sao Paulo',
    referral: `QA Codex ${suffix}`,
    pets: [
        {
            name: `Luna Codex ${suffix}`,
            species: 'cao',
            size: 'pequeno',
            sex: 'femea',
        },
        {
            name: `Thor Codex ${suffix}`,
            species: 'gato',
            size: 'medio',
            sex: 'macho',
        },
    ],
};

function redacted(value) {
    return String(value || '').replace(/([?&](?:key|token|login|dps_token)=)[^&\s]+/gi, '$1[redacted]');
}

async function waitForForm(page) {
    await page.waitForSelector('#dps-reg-form', { timeout: 30000 });
    await page.waitForTimeout(2200);
}

async function login(page) {
    await page.goto('https://desi.pet/wp-login.php', { waitUntil: 'domcontentloaded' });
    await page.fill('#user_login', wpUser);
    await page.fill('#user_pass', wpPass);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null),
        page.click('#wp-submit'),
    ]);
    await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => null);

    const loggedIn = await page.locator('#wpadminbar').count().catch(() => 0);
    if (!loggedIn && !page.url().includes('/wp-admin')) {
        throw new Error('WP login did not complete');
    }
}

async function inspectCurrentPage(page, width) {
    return page.evaluate((breakpoint) => {
        const doc = document.documentElement;
        const body = document.body;
        const form = document.querySelector('#dps-reg-form');
        const address = document.querySelector('#dps-client-address');
        const submit = document.querySelector('button[type="submit"]');
        const photoAuthInputs = [...document.querySelectorAll('input[name="client_photo_auth"]')];
        const placeElement = document.querySelector('gmp-place-autocomplete, .dps-place-autocomplete-element');
        const placeStyles = placeElement ? window.getComputedStyle(placeElement) : null;
        const shadowInput = placeElement && placeElement.shadowRoot ? placeElement.shadowRoot.querySelector('input') : null;
        const shadowInputStyles = shadowInput ? window.getComputedStyle(shadowInput) : null;
        const getText = (selector) => {
            const node = document.querySelector(selector);
            return node ? node.textContent.trim() : '';
        };

        return {
            breakpoint,
            formExists: !!form,
            introExists: !!document.querySelector('.dps-registration-intro'),
            draftOptinExists: !!document.querySelector('[data-dps-draft-optin]'),
            restorePanelExists: !!document.querySelector('[data-dps-draft-restore-panel]'),
            fieldGroupCount: document.querySelectorAll('.dps-field-group').length,
            optionalDetailsCount: document.querySelectorAll('.dps-optional-details').length,
            optionalDetailsIndicatorText: getText('.dps-optional-details__indicator'),
            petFieldsets: document.querySelectorAll('#dps-pets-wrapper .dps-pet-fieldset').length,
            photoAuthFieldExists: !!document.querySelector('[data-dps-photo-auth-field]'),
            photoAuthOptions: photoAuthInputs.length,
            photoAuthRequired: photoAuthInputs.length === 2 && photoAuthInputs.every((input) => input.required),
            phoneHintExists: !!document.querySelector('#dps-phone-hint'),
            publicReadonlyOwnerFields: [...document.querySelectorAll('input[readonly]')].filter((input) => /cliente|owner/i.test(input.name || input.id || '')).length,
            addressTag: address ? address.tagName : '',
            placesReady: address ? address.dataset.dpsPlacesReady || '' : '',
            placesMode: address ? address.dataset.dpsPlacesMode || '' : '',
            hasPlaceElement: !!document.querySelector('gmp-place-autocomplete, .dps-place-autocomplete-element'),
            placeElementColor: placeStyles ? placeStyles.color : '',
            placeElementBackground: placeStyles ? placeStyles.backgroundColor : '',
            placeElementColorScheme: placeStyles ? placeStyles.colorScheme : '',
            placeShadowInputColor: shadowInputStyles ? shadowInputStyles.color : '',
            placeShadowInputBackground: shadowInputStyles ? shadowInputStyles.backgroundColor : '',
            hasPacTargetInput: address ? address.classList.contains('pac-target-input') : false,
            hasHiddenAddressSource: address ? address.classList.contains('dps-address-source-hidden') : false,
            duplicateCheckEnabled: !!(window.dpsRegistrationData && window.dpsRegistrationData.duplicateCheck && window.dpsRegistrationData.duplicateCheck.enabled),
            draftConfigEnabled: !!(window.dpsRegistrationData && window.dpsRegistrationData.draft && window.dpsRegistrationData.draft.enabled),
            submitDisabled: submit ? submit.disabled : null,
            stepLabel: getText('#dps-step-label'),
            stepCounterExists: !!document.querySelector('#dps-step-counter'),
            progressTopText: getText('.dps-progress-top'),
            scrollWidth: Math.max(doc.scrollWidth, body ? body.scrollWidth : 0),
            viewportWidth: window.innerWidth,
            hasHorizontalOverflow: Math.max(doc.scrollWidth, body ? body.scrollWidth : 0) > window.innerWidth + 1,
        };
    }, width);
}

async function setCheckbox(page, selector, checked) {
    const locator = page.locator(selector);
    if (await locator.count()) {
        const current = await locator.isChecked();
        if (current !== checked) {
            if (checked) {
                await locator.check({ force: true });
            } else {
                await locator.uncheck({ force: true });
            }
        }
    }
}

async function runFlow(page) {
    await page.setViewportSize({ width: 1200, height: 900 });
    await page.goto(`${registrationUrl}?codex_qa=${encodeURIComponent(suffix)}&flow=1`, { waitUntil: 'domcontentloaded' });
    await waitForForm(page);

    await page.click('#dps-next-step');
    await page.waitForSelector('.dps-js-error', { timeout: 10000 });
    await page.waitForTimeout(300);
    const blankErrors = await page.locator('.dps-js-error').allTextContents();
    const firstInvalidName = await page.evaluate(() => {
        const active = document.activeElement;
        return active ? active.getAttribute('name') || active.id || active.tagName : '';
    });

    await page.fill('input[name="client_name"]', testData.clientName);
    await page.fill('input[name="client_phone"]', testData.phone);
    await page.fill('input[name="client_address"]', testData.address);
    await page.fill('input[name="client_referral"]', testData.referral);
    await page.check('input[name="client_photo_auth"][value="1"]', { force: true });
    await setCheckbox(page, 'input[name="dps_admin_skip_confirmation"]', true);
    await setCheckbox(page, 'input[name="dps_admin_send_welcome"]', false);
    await setCheckbox(page, '[data-dps-draft-optin]', true);

    await page.waitForTimeout(1800);
    const draftStatusAfterSave = await page.locator('[data-dps-draft-status]').textContent().catch(() => '');

    await page.reload({ waitUntil: 'domcontentloaded' });
    await waitForForm(page);
    const restoreAvailable = await page.locator('[data-dps-draft-restore]').count().catch(() => 0);
    if (restoreAvailable) {
        await page.click('[data-dps-draft-restore]');
        await page.waitForTimeout(300);
    }
    const restoredName = await page.inputValue('input[name="client_name"]').catch(() => '');
    await setCheckbox(page, 'input[name="dps_admin_skip_confirmation"]', true);
    await setCheckbox(page, 'input[name="dps_admin_send_welcome"]', false);

    await page.click('#dps-next-step');
    await page.waitForSelector('.dps-step[data-step="2"].dps-step-active', { timeout: 10000 });

    const firstPet = page.locator('#dps-pets-wrapper .dps-pet-fieldset').nth(0);
    await firstPet.locator('input[name="pet_name[]"]').fill(testData.pets[0].name);
    await firstPet.locator('select[name="pet_species[]"]').selectOption(testData.pets[0].species);
    await firstPet.locator('select[name="pet_size[]"]').selectOption(testData.pets[0].size);
    await firstPet.locator('select[name="pet_sex[]"]').selectOption(testData.pets[0].sex);
    await firstPet.locator('.dps-optional-details').evaluate((node) => {
        node.open = true;
    });
    await firstPet.locator('input[name="pet_photo[]"]').setInputFiles(petPhotoPath);
    await page.waitForTimeout(250);
    await page.screenshot({ path: path.join(outputDir, 'cadastro-pet-photo-step-1200.png'), fullPage: true });

    await page.click('#dps-add-pet');
    await page.waitForFunction(() => document.querySelectorAll('#dps-pets-wrapper .dps-pet-fieldset').length === 2);
    const secondPet = page.locator('#dps-pets-wrapper .dps-pet-fieldset').nth(1);
    await secondPet.locator('input[name="pet_name[]"]').fill(testData.pets[1].name);
    await secondPet.locator('select[name="pet_species[]"]').selectOption(testData.pets[1].species);
    await secondPet.locator('select[name="pet_size[]"]').selectOption(testData.pets[1].size);
    await secondPet.locator('select[name="pet_sex[]"]').selectOption(testData.pets[1].sex);

    await page.click('#dps-next-step-2');
    await page.waitForSelector('.dps-step[data-step="3"].dps-step-active', { timeout: 10000 });
    await page.check('#dps-summary-confirm', { force: true });
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(250);
    await page.screenshot({ path: path.join(outputDir, 'cadastro-10melhorias-flow-1200.png'), fullPage: true });

    const flowState = await page.evaluate(() => {
        const summaryContent = document.querySelector('#dps-summary-content')?.textContent || '';

        return {
            stepLabel: document.querySelector('#dps-step-label')?.textContent.trim() || '',
            petFieldsets: document.querySelectorAll('#dps-pets-wrapper .dps-pet-fieldset').length,
            aggressiveNames: [...document.querySelectorAll('input[name^="pet_aggressive"]')].map((input) => input.getAttribute('name')),
            productPrefBlocks: document.querySelectorAll('#dps-product-prefs-wrapper .dps-product-prefs-pet').length,
            summaryHasClient: summaryContent.includes('Cliente Codex Real Cadastro'),
            summaryHasPetPhoto: summaryContent.includes('dps-pet-photo-'),
            summaryHasPhotoAuth: summaryContent.includes('Fotos nas redes sociais') && summaryContent.includes('Autorizado'),
            petPhotoInputs: document.querySelectorAll('input[name="pet_photo[]"]').length,
            petPhotoPreviewFilled: document.querySelectorAll('.dps-pet-photo-preview.is-filled').length,
            submitDisabled: document.querySelector('button[type="submit"]')?.disabled ?? null,
        };
    });

    let submitState = { attempted: false };
    if (runRealSubmit) {
        submitState.attempted = true;
        await Promise.all([
            page.waitForURL(/registered=1/, { timeout: 45000 }).catch(() => null),
            page.click('button[type="submit"]'),
        ]);
        await page.waitForLoadState('domcontentloaded', { timeout: 30000 }).catch(() => null);
        await page.waitForSelector('.dps-reg-success', { timeout: 30000 }).catch(() => null);
        submitState = {
            attempted: true,
            url: page.url(),
            successVisible: await page.locator('.dps-reg-success').count().then((count) => count > 0).catch(() => false),
            successText: await page.locator('.dps-reg-success').textContent().catch(() => ''),
        };
    }

    return {
        blankErrors,
        firstInvalidName,
        draftStatusAfterSave,
        restoreAvailable: !!restoreAvailable,
        restoredName,
        flowState,
        submitState,
    };
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ ignoreHTTPSErrors: true });
const page = await context.newPage();
const consoleMessages = [];
const networkRequests = [];

page.on('console', (message) => {
    consoleMessages.push({
        type: message.type(),
        text: redacted(message.text()).slice(0, 500),
    });
});

page.on('request', (request) => {
    const url = request.url();
    if (/maps|admin-ajax|cadastro|wp-login|recaptcha/i.test(url)) {
        networkRequests.push(redacted(url));
    }
});

try {
    await login(page);

    const breakpoints = [375, 600, 840, 1200, 1920];
    const breakpointResults = [];
    for (const width of breakpoints) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(`${registrationUrl}?codex_qa=${encodeURIComponent(suffix)}&bp=${width}`, { waitUntil: 'domcontentloaded' });
        await waitForForm(page);
        const filename = `cadastro-10melhorias-admin-${width}.png`;
        await page.screenshot({ path: path.join(outputDir, filename), fullPage: true });
        breakpointResults.push({
            screenshot: filename,
            ...(await inspectCurrentPage(page, width)),
        });
    }

    const flow = await runFlow(page);
    const result = {
        generatedAt: new Date().toISOString(),
        registrationUrl,
        runRealSubmit,
        testData,
        breakpoints: breakpointResults,
        flow,
        consoleMessages,
        networkRequests: [...new Set(networkRequests)].slice(0, 80),
    };

    fs.writeFileSync(resultPath, JSON.stringify(result, null, 2));

    const hasOverflow = breakpointResults.some((item) => item.hasHorizontalOverflow);
    const submitFailed = runRealSubmit && !flow.submitState.successVisible;
    if (hasOverflow || submitFailed) {
        throw new Error(`Smoke failed: overflow=${hasOverflow} submitFailed=${submitFailed}`);
    }
} finally {
    await browser.close();
    fs.rmSync(petPhotoPath, { force: true });
}
