'use strict';

const path = require('node:path');
const { pathToFileURL } = require('node:url');
const playwrightPath = process.env.URBANIX_PLAYWRIGHT_PATH || 'playwright';
const { chromium } = require(playwrightPath);
let browser;

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

(async () => {
  const projectRoot = path.resolve(__dirname, '..');
  const urlFor = file => pathToFileURL(path.join(projectRoot, file)).href;
  browser = await chromium.launch({
    headless: true,
    executablePath: process.env.URBANIX_BROWSER_EXECUTABLE || undefined
  });
  const context = await browser.newContext({ viewport: { width: 1366, height: 768 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });

  await page.goto(urlFor('login.html'));
  await page.evaluate(() => localStorage.clear());
  await page.reload();
  await page.fill('#loginEmail', 'admin@empresa.com');
  await page.fill('#loginPassword', 'senha-incorreta');
  await page.click('button[type="submit"]');
  assert((await page.textContent('#loginFeedback')).includes('inválidos'), 'Login inválido não exibiu feedback.');

  await page.fill('#loginPassword', '123456');
  await page.click('button[type="submit"]');
  await page.waitForURL(/index\.html$/);
  assert(await page.isVisible('[data-user-menu]'), 'Menu do usuário não foi renderizado.');
  const persisted = await page.evaluate(() => JSON.parse(localStorage.getItem('urbanix.erp.v1')));
  assert(persisted && persisted.meta.version === 1, 'Estado versionado não foi persistido.');
  assert(persisted.users.length === 4 && persisted.customers.length === 15, 'Seed demonstrativo incompleto.');
  assert(persisted.units.find(item => item.id === 'unit-amz-a02').reservationId === 'reservation-1', 'Relação unidade/reserva inconsistente.');
  const orphanCounts = await page.evaluate(() => {
    const state = Urbanix.Store.getState();
    const contracts = new Set(state.contracts.map(item => item.id));
    const installments = new Set(state.installments.map(item => item.id));
    return {
      sales: state.sales.length,
      contracts: state.contracts.length,
      installments: state.installments.filter(item => !contracts.has(item.contractId)).length,
      payments: state.payments.filter(item => !contracts.has(item.contractId) || !installments.has(item.installmentId)).length,
      receivables: state.accountsReceivable.filter(item => !contracts.has(item.contractId) || !installments.has(item.installmentId)).length
    };
  });
  assert(orphanCounts.sales === 10 && orphanCounts.contracts === 10, 'Seed deve possuir 10 vendas e 10 contratos.');
  assert(orphanCounts.installments + orphanCounts.payments + orphanCounts.receivables === 0, 'Seed financeiro possui referências órfãs.');

  await page.evaluate(() => Urbanix.Store.create('customers', { id: 'customer-smoke', name: 'Cliente Smoke', cpf: '999.888.777-66', email: 'smoke@exemplo.com', status: 'prospect' }));
  await page.evaluate(() => Urbanix.Store.update('customers', 'customer-smoke', { status: 'active' }));
  await page.reload();
  assert(await page.evaluate(() => Urbanix.Store.find('customers', 'customer-smoke').status) === 'active', 'CRUD não persistiu criação/edição após reload.');
  await page.evaluate(() => Urbanix.Store.remove('customers', 'customer-smoke'));
  await page.reload();
  assert(await page.evaluate(() => Urbanix.Store.find('customers', 'customer-smoke')) === null, 'CRUD não persistiu exclusão após reload.');

  await page.click('[data-global-search]');
  await page.fill('#globalSearchPanel input', 'Maria');
  await page.waitForSelector('#globalSearchPanel .search-results a');
  assert((await page.textContent('#globalSearchPanel')).includes('Maria Oliveira'), 'Busca global não localizou cliente.');
  await page.keyboard.press('Escape');

  await page.click('[data-notifications]');
  assert(await page.isVisible('#notificationsPanel [data-read-all]'), 'Painel de notificações não abriu.');
  await page.click('#notificationsPanel [data-read-all]');
  assert(await page.isHidden('[data-notification-count]'), 'Contador não foi atualizado ao marcar notificações como lidas.');

  await page.click('[data-user-menu]');
  await page.click('#userPanel [data-theme]');
  const themeAfterClick = await page.getAttribute('html', 'data-theme');
  const storedThemeAfterClick = await page.evaluate(() => JSON.parse(localStorage.getItem('urbanix.erp.v1')).settings.theme);
  assert(themeAfterClick === 'dark', `Tema escuro não foi aplicado (DOM: ${themeAfterClick}; store: ${storedThemeAfterClick}).`);
  await page.reload();
  assert(await page.getAttribute('html', 'data-theme') === 'dark', 'Tema não persistiu após reload.');

  await page.setViewportSize({ width: 768, height: 900 });
  assert(await page.isVisible('[data-menu-toggle]'), 'Controle da sidebar não apareceu em 768px.');

  await page.setViewportSize({ width: 375, height: 812 });
  await page.click('[data-menu-toggle]');
  assert(await page.getAttribute('[data-menu-toggle]', 'aria-expanded') === 'true', 'Sidebar mobile não abriu.');
  await page.keyboard.press('Escape');
  assert(await page.getAttribute('[data-menu-toggle]', 'aria-expanded') === 'false', 'Sidebar mobile não fechou com Escape.');

  for (const file of ['empreendimentos.html', 'tabela-precos.html', 'clientes.html', 'configuracoes.html']) {
    await page.goto(urlFor(file));
    const unlabeledFields = await page.locator('input:not([type="hidden"]), select').evaluateAll(fields => fields.filter(field => !field.labels?.length && !field.getAttribute('aria-label') && !field.getAttribute('aria-labelledby')).map(field => field.outerHTML));
    assert(unlabeledFields.length === 0, `${file} possui campos sem label programático: ${unlabeledFields.join(' | ')}`);
  }
  await page.goto(urlFor('empreendimentos.html'));
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  assert(overflow <= 0, `Empreendimentos possui ${overflow}px de overflow horizontal em 375px.`);

  await page.goto(urlFor('tabela-precos.html'));
  await page.fill('.table-tools input', 'A-08');
  const visibleRows = await page.locator('.table-app tbody tr:visible').count();
  assert(visibleRows === 1, `Busca da tabela deveria mostrar 1 linha; mostrou ${visibleRows}.`);
  await page.fill('.table-tools input', 'unidade inexistente');
  assert(await page.isVisible('.table-empty'), 'Tabela não exibiu estado vazio.');
  await page.emulateMedia({ media: 'print' });
  assert(await page.isHidden('.sidebar'), 'Sidebar não foi ocultada na impressão.');

  const anonymousContext = await browser.newContext();
  const anonymousPage = await anonymousContext.newPage();
  await anonymousPage.goto(urlFor('index.html'));
  await anonymousPage.waitForURL(/login\.html\?next=/);
  assert(errors.length === 0, `Erros no navegador: ${errors.join(' | ')}`);

  await anonymousContext.close();
  await context.close();
  await browser.close();
  console.log('OK browser smoke: login, integridade, CRUD, busca, tema, acessibilidade, 375/768/1366, tabela, impressão e redirect.');
})().catch(error => {
  console.error(error.stack || error.message);
  if (browser) browser.close().catch(() => {});
  process.exitCode = 1;
});
