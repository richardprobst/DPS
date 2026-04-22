const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright-core');

const chromePath = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const outDir = path.resolve('docs/screenshots/2026-04-21');
const breakpoints = [375, 600, 840, 1200, 1920];
const urls = {
  registration: 'https://desi.pet/qa-signature-cadastro-20260421-222213/',
  portalPublic: 'https://desi.pet/qa-signature-portal-20260421-222213/',
  portalToken: 'https://desi.pet/qa-signature-portal-20260421-222213/?dps_token=541c4dc4ac7f425a230420620694ad9b939b085d814635e110e51cd649677043',
  portalAfterAuth: 'https://desi.pet/qa-signature-portal-20260421-222213/',
  profileUpdate: 'https://desi.pet/qa-signature-portal-20260421-222213/?dps_action=profile_update&token=adea0c8fbcb7ecfbf722b96735baa5fab083971f22e9d060acc5ebf810e1050c',
  passwordReset: 'https://desi.pet/qa-signature-portal-20260421-222213/?dps_action=portal_password_reset&key=OjuU8dMDDDW0qJpgpsCD&login=qa.signature.20260421-222213%40example.com'
};

async function openPage(context, url, width) {
  const page = await context.newPage({ viewport: { width, height: 1400 } });
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(4500);
  return page;
}

async function captureSeries(context, key, url, checks) {
  const results = [];
  for (const width of breakpoints) {
    const page = await openPage(context, url, width);
    const filename = `${key}-${width}.png`;
    await page.screenshot({ path: path.join(outDir, filename), fullPage: true });
    const content = await page.content();
    const bodyText = await page.locator('body').innerText().catch(() => '');
    const checkResult = {};
    for (const [name, pattern] of Object.entries(checks || {})) {
      checkResult[name] = typeof pattern === 'string' ? content.includes(pattern) || bodyText.includes(pattern) : !!pattern(content, bodyText);
    }
    results.push({ width, title: await page.title(), checks: checkResult });
    await page.close();
  }
  return results;
}

(async () => {
  fs.mkdirSync(outDir, { recursive: true });
  const browser = await chromium.launch({ executablePath: chromePath, headless: true, args: ['--disable-gpu'] });
  const report = {};

  const publicContext = await browser.newContext({ ignoreHTTPSErrors: true });
  report.registration = await captureSeries(publicContext, 'qa-signature-registration', urls.registration, {
    hasSignatureButton: 'CADASTRAR',
    hasTutorStep: 'Dados do tutor',
    hasPetStep: 'Pets do cadastro'
  });

  const regErrorPage = await openPage(publicContext, urls.registration, 840);
  await regErrorPage.locator('button[type="submit"]').click();
  await regErrorPage.waitForTimeout(1200);
  await regErrorPage.screenshot({ path: path.join(outDir, 'qa-signature-registration-errors-840.png'), fullPage: true });
  report.registrationErrors = {
    title: await regErrorPage.title(),
    hasValidationText: (await regErrorPage.locator('body').innerText()).includes('Informe o nome completo do tutor')
  };
  await regErrorPage.close();

  report.portalPublic = await captureSeries(publicContext, 'qa-signature-portal-access', urls.portalPublic, {
    hasMagicLink: 'Enviar link de acesso',
    hasPasswordEntry: 'Entrar com senha',
    hasWhatsappCard: 'Precisa de ajuda com o acesso?'
  });

  const portalFocusPage = await openPage(publicContext, urls.portalPublic, 375);
  await portalFocusPage.keyboard.press('Tab');
  await portalFocusPage.keyboard.press('Tab');
  await portalFocusPage.keyboard.press('Tab');
  await portalFocusPage.waitForTimeout(500);
  await portalFocusPage.screenshot({ path: path.join(outDir, 'qa-signature-portal-access-focus-375.png'), fullPage: true });
  await portalFocusPage.close();

  report.passwordReset = await captureSeries(publicContext, 'qa-signature-portal-reset', urls.passwordReset, {
    hasResetTitle: 'Defina sua senha de acesso',
    hasSubmit: 'Salvar nova senha'
  });
  await publicContext.close();

  const authContext = await browser.newContext({ ignoreHTTPSErrors: true });
  const seedPage = await openPage(authContext, urls.portalToken, 1200);
  report.portalTokenSeed = {
    title: await seedPage.title(),
    textSample: (await seedPage.locator('body').innerText()).slice(0, 300)
  };
  await seedPage.close();
  report.portalAuth = await captureSeries(authContext, 'qa-signature-portal-auth', urls.portalAfterAuth, {
    hidesAccessForm: (content, bodyText) => !content.includes('data-dps-access-form="magic-link"'),
    hasPortalShell: 'Portal do Cliente',
    hasMessagesTab: 'Mensagens'
  });
  await authContext.close();

  const profileContext = await browser.newContext({ ignoreHTTPSErrors: true });
  report.profileUpdate = await captureSeries(profileContext, 'qa-signature-profile-update', urls.profileUpdate, {
    hasTutorData: 'Dados do tutor',
    hasPetData: 'Pets vinculados',
    hasSave: 'Salvar atualizacao'
  });

  const profileAddPetPage = await openPage(profileContext, urls.profileUpdate, 840);
  await profileAddPetPage.locator('[data-dps-profile-add-pet]').click();
  await profileAddPetPage.waitForTimeout(700);
  await profileAddPetPage.screenshot({ path: path.join(outDir, 'qa-signature-profile-update-add-pet-840.png'), fullPage: true });
  report.profileAddPet = {
    hasSecondNewPet: ((await profileAddPetPage.content()).match(/data-dps-new-pet-card/g) || []).length >= 2
  };
  await profileAddPetPage.close();
  await profileContext.close();

  await browser.close();
  fs.writeFileSync(path.resolve('test-results/qa-signature-report.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
})();
