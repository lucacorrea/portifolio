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
  browser = await chromium.launch({ headless: true, executablePath: process.env.URBANIX_BROWSER_EXECUTABLE || undefined });
  const context = await browser.newContext({ viewport: { width: 1366, height: 768 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });

  await page.goto(urlFor('login.html'));
  await page.evaluate(() => localStorage.clear());
  await page.reload();
  await page.fill('#loginEmail', 'admin@empresa.com');
  await page.fill('#loginPassword', '123456');
  await page.click('button[type="submit"]');
  await page.waitForURL(/index\.html$/);
  assert(await page.isVisible('[data-page="dashboard"] .metric'), 'Dashboard dinamico nao foi renderizado.');

  await page.goto(urlFor('configuracoes.html'));
  await page.fill('#companyName', 'Urbanix QA Empreendimentos');
  await page.click('[data-settings-form] button[type="submit"]');
  await page.reload();
  assert(await page.inputValue('#companyName') === 'Urbanix QA Empreendimentos', 'Configuracao nao persistiu.');

  await page.goto(urlFor('financeiro.html'));
  const initialPayments = await page.evaluate(() => Urbanix.Store.query('payments').length);
  await page.locator('[data-receivable]:visible').first().click();
  await page.locator('.urbanix-dialog footer .btn-primary-app').click();
  await page.locator('.urbanix-dialog footer .btn-primary-app').click();
  await page.waitForFunction(count => Urbanix.Store.query('payments').length === count + 1, initialPayments);
  const financeResult = await page.evaluate(() => {
    const state = Urbanix.Store.getState();
    const payment = state.payments.at(-1);
    const installment = state.installments.find(item => item.id === payment.installmentId);
    const receivable = state.accountsReceivable.find(item => item.installmentId === payment.installmentId);
    return { installment: installment?.status, receivable: receivable?.status };
  });
  assert(financeResult.installment === 'paid' && financeResult.receivable === 'paid', 'Recebimento nao atualizou parcela e titulo.');
  await page.click('[data-fin-tab="cash"]');
  await page.waitForSelector('#cashChart');
  assert(await page.evaluate(() => Boolean(Chart.getChart(document.getElementById('cashChart')))), 'Grafico do fluxo de caixa nao foi inicializado.');

  await page.goto(urlFor('contratos.html'));
  await page.locator('[data-contract]').first().click();
  assert(await page.isVisible('.installment-grid'), 'View completa do contrato nao abriu.');
  await page.keyboard.press('Escape');

  await page.goto(urlFor('engenharia.html'));
  const measurementCount = await page.evaluate(() => Urbanix.Store.query('measurements').length);
  await page.click('[data-new-measurement]');
  await page.fill('#measurementQuantity', '25');
  await page.fill('#measurementProgress', '5');
  await page.fill('#measurementAmount', '25000');
  await page.fill('#measurementResponsible', 'Engenheira QA');
  await page.locator('.urbanix-dialog footer .btn-primary-app').click();
  await page.waitForFunction(count => Urbanix.Store.query('measurements').length === count + 1, measurementCount);
  await page.locator('[data-measurement-action="submit"]').last().click();
  await page.locator('.urbanix-dialog footer .btn-primary-app').click();
  await page.waitForFunction(() => Urbanix.Store.query('measurements').at(-1).status === 'submitted');
  await page.locator('[data-measurement-action="approve"]').last().click();
  await page.locator('.urbanix-dialog footer .btn-primary-app').click();
  await page.waitForFunction(() => Urbanix.Store.query('measurements').at(-1).status === 'approved');
  const measurementLinked = await page.evaluate(() => {
    const state = Urbanix.Store.getState();
    return state.accountsPayable.some(item => item.measurementId === state.measurements.at(-1).id);
  });
  assert(measurementLinked, 'Medicao aprovada nao gerou conta a pagar.');

  await page.goto(urlFor('compras.html'));
  const requestCount = await page.evaluate(() => Urbanix.Store.query('purchaseRequests').length);
  await page.click('[data-new-request]');
  await page.fill('#requestMaterial', 'Tubo PVC QA');
  await page.fill('#requestQuantity', '40');
  await page.fill('#requestAmount', '3200');
  await page.locator('.urbanix-dialog footer .btn-primary-app').click();
  await page.waitForFunction(count => Urbanix.Store.query('purchaseRequests').length === count + 1, requestCount);
  await page.locator('[data-advance-purchase]').last().click();
  await page.locator('.urbanix-dialog footer .btn-primary-app').click();
  await page.waitForFunction(() => Urbanix.Store.query('purchaseRequests').at(-1).status === 'approval');
  await page.locator('[data-advance-purchase]').last().click();
  await page.locator('.urbanix-dialog footer .btn-primary-app').click();
  await page.waitForFunction(() => Urbanix.Store.query('purchaseRequests').at(-1).status === 'order');
  await page.locator('[data-advance-purchase]').last().click();
  await page.locator('.urbanix-dialog footer .btn-primary-app').click();
  await page.waitForFunction(() => Urbanix.Store.query('purchaseRequests').at(-1).status === 'received');
  const purchaseResult = await page.evaluate(() => {
    const state = Urbanix.Store.getState();
    const request = state.purchaseRequests.at(-1);
    return {
      stock: state.inventory.some(item => item.material === request.material && item.balance >= request.quantity),
      payable: state.accountsPayable.some(item => item.purchaseRequestId === request.id)
    };
  });
  assert(purchaseResult.stock && purchaseResult.payable, 'Recebimento nao atualizou estoque e financeiro.');

  await page.goto(urlFor('relatorios.html'));
  await page.click('[data-report="inventory"]');
  assert((await page.textContent('[data-report-title]')).toLowerCase().includes('estoque'), 'Relatorio de estoque nao abriu.');
  assert(await page.locator('[data-report-preview] table').count() === 1, 'Relatorio nao exibiu tabela.');

  await page.goto(urlFor('portal-cliente.html'));
  await page.click('[data-portal-tab="support"]');
  assert(await page.isVisible('.portal-readonly-note') && await page.locator('[data-support-form]').count() === 0, 'Previa administrativa do portal nao esta somente leitura.');

  const protectedPages = ['index.html', 'contratos.html', 'financeiro.html', 'engenharia.html', 'compras.html', 'relatorios.html', 'portal-cliente.html', 'configuracoes.html'];
  await page.setViewportSize({ width: 375, height: 812 });
  for (const file of protectedPages) {
    await page.goto(urlFor(file));
    const audit = await page.evaluate(() => ({
      overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      deadActions: document.querySelectorAll('[data-demo-toast]:not([data-urbanix-handled])').length
    }));
    assert(audit.overflow <= 1, `${file} possui overflow horizontal de ${audit.overflow}px.`);
    assert(audit.deadActions === 0, `${file} ainda possui ${audit.deadActions} acoes genericas ativas.`);
  }

  assert(errors.length === 0, `Erros de pagina/console: ${errors.join(' | ')}`);
  console.log('OK domain smoke: financeiro, contratos, medicoes, compras, estoque, configuracoes, relatorios, portal e mobile.');
})().catch(error => {
  console.error(error.stack || error);
  process.exitCode = 1;
}).finally(async () => {
  if (browser) await browser.close();
});
