'use strict';

const path = require('node:path');
const { pathToFileURL } = require('node:url');
const playwrightPath = process.env.URBANIX_PLAYWRIGHT_PATH || 'playwright';
const { chromium } = require(playwrightPath);
let browser;

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

async function login(page, urlFor, email) {
  await page.goto(urlFor('login.html'));
  await page.evaluate(() => localStorage.clear());
  await page.reload();
  await page.fill('#loginEmail', email);
  await page.fill('#loginPassword', '123456');
  await page.click('button[type="submit"]');
}

(async () => {
  const projectRoot = path.resolve(__dirname, '..');
  const urlFor = file => pathToFileURL(path.join(projectRoot, file)).href;
  browser = await chromium.launch({ headless: true, executablePath: process.env.URBANIX_BROWSER_EXECUTABLE || undefined });
  const context = await browser.newContext({ viewport: { width: 1366, height: 768 }, acceptDownloads: true });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });

  await login(page, urlFor, 'cliente@empresa.com');
  await page.waitForURL(/portal-cliente\.html/);
  assert(await page.evaluate(() => Urbanix.App.sessionUser()?.roleCode === 'client'), 'Login cliente nao iniciou a sessao esperada.');

  for (const file of ['index.html', 'financeiro.html', 'configuracoes.html']) {
    await page.goto(urlFor(file));
    await page.waitForURL(/portal-cliente\.html/);
  }

  await page.goto(`${urlFor('portal-cliente.html')}?previewCustomer=customer-3&customer=customer-4`);
  const isolated = await page.evaluate(() => {
    const user = Urbanix.App.sessionUser();
    const portal = Urbanix.Portal.resolveContext(user);
    return {
      userCustomerId: user.customerId,
      customerId: portal.customer?.id,
      contractCustomerId: portal.contract?.customerId,
      documentCustomerIds: [...new Set(portal.documents.map(item => item.customerId))],
      installmentContractIds: [...new Set(portal.installments.map(item => item.contractId))],
      workEnterpriseId: portal.work?.enterpriseId,
      contractEnterpriseId: portal.contract?.enterpriseId
    };
  });
  assert(isolated.userCustomerId === 'customer-2' && isolated.customerId === 'customer-2', 'Parametro externo alterou a identidade do cliente.');
  assert(isolated.contractCustomerId === 'customer-2', 'Contrato de outro cliente apareceu no portal.');
  assert(isolated.documentCustomerIds.every(id => id === 'customer-2'), 'Documento de outro cliente apareceu no portal.');
  assert(isolated.installmentContractIds.length === 1, 'Parcelas de contratos diferentes foram misturadas.');
  assert(isolated.workEnterpriseId === isolated.contractEnterpriseId, 'Obra exibida nao pertence ao empreendimento contratado.');
  assert(await page.locator('[data-portal-customer-select], a[href="index.html"]').count() === 0, 'Cliente recebeu controles da previa administrativa.');
  assert(await page.locator('[data-global-search], [data-quick-actions], a[href="crm.html"]').count() === 0, 'Topbar administrativa apareceu no portal do cliente.');

  const tabKeys = ['home', 'property', 'finance', 'contract', 'documents', 'work', 'support'];
  for (const tab of tabKeys) {
    await page.click(`[data-portal-tab="${tab}"]`);
    assert(await page.getAttribute(`[data-portal-tab="${tab}"]`, 'aria-selected') === 'true', `A aba ${tab} nao foi selecionada.`);
    assert((await page.textContent('#portal-panel')).trim().length > 20, `A aba ${tab} nao possui conteudo.`);
  }
  await page.focus('[data-portal-tab="home"]');
  await page.keyboard.press('ArrowRight');
  assert(await page.getAttribute('[data-portal-tab="property"]', 'aria-selected') === 'true', 'Setas do teclado nao navegam entre as abas.');
  const propertyImage = page.locator('.portal-property-visual img');
  assert(await propertyImage.count() === 1, 'Meu imovel nao renderizou uma imagem real.');
  assert(Boolean(await propertyImage.getAttribute('alt')) && await propertyImage.getAttribute('loading') === 'eager', 'Imagem do imovel nao possui alt ou carregamento adequado.');
  await propertyImage.evaluate(image => image.dispatchEvent(new Event('error')));
  assert(await propertyImage.isHidden() && await page.isVisible('.portal-property-visual .portal-image-fallback'), 'Imagem do imovel nao possui fallback funcional.');

  await page.click('[data-portal-tab="finance"]');
  const financeCounts = await page.evaluate(() => {
    const ctx = Urbanix.Portal.resolveContext(Urbanix.App.sessionUser());
    return { expected: ctx.installments.length, rendered: document.querySelectorAll('[data-portal-installment]').length };
  });
  assert(financeCounts.rendered === financeCounts.expected, 'Grade financeira nao corresponde ao contrato do cliente.');
  await page.locator('[data-portal-installment]').first().click();
  assert(await page.isVisible('.portal-modal-details'), 'Detalhe traduzido da parcela nao abriu.');
  await page.keyboard.press('Escape');

  await page.click('[data-portal-tab="documents"]');
  const documentCounts = await page.evaluate(() => {
    const ctx = Urbanix.Portal.resolveContext(Urbanix.App.sessionUser());
    return { expected: ctx.documents.length, rendered: document.querySelectorAll('[data-download-document]').length };
  });
  assert(documentCounts.rendered === documentCounts.expected, 'Documentos renderizados nao correspondem ao contrato.');
  const expectedFile = await page.locator('[data-download-document]').first().evaluate(button => {
    const item = Urbanix.Store.find('documents', button.dataset.downloadDocument);
    return item.fileName;
  });
  const downloadPromise = page.waitForEvent('download');
  await page.locator('[data-download-document]').first().click();
  const download = await downloadPromise;
  assert(download.suggestedFilename() === expectedFile, 'Download demonstrativo usou nome ou extensao incoerente.');

  await page.click('[data-portal-tab="work"]');
  const workCounts = await page.evaluate(() => {
    const ctx = Urbanix.Portal.resolveContext(Urbanix.App.sessionUser());
    return {
      phases: document.querySelectorAll('.portal-work-phase').length,
      expectedPhases: ctx.state.services.filter(item => item.workId === ctx.work.id && item.customerVisible).length,
      photos: document.querySelectorAll('.portal-photo-gallery img').length,
      expectedPhotos: ctx.state.workPhotos.filter(item => item.workId === ctx.work.id).length
    };
  });
  assert(workCounts.phases === 6 && workCounts.phases === workCounts.expectedPhases, 'Timeline da obra nao exibiu as seis fases vinculadas.');
  assert(workCounts.photos === workCounts.expectedPhotos, 'Galeria nao corresponde a obra do contrato.');
  assert(await page.locator('.portal-photo-gallery img').evaluateAll(images => images.every(image => image.alt && image.loading === 'lazy')), 'Galeria possui imagem sem alt ou lazy loading.');
  const galleryImage = page.locator('.portal-photo-gallery img').first();
  await galleryImage.evaluate(image => image.dispatchEvent(new Event('error')));
  assert(await galleryImage.isHidden() && await page.locator('.portal-photo-gallery .portal-image-fallback').first().isVisible(), 'Galeria nao possui fallback funcional.');

  await page.click('[data-portal-tab="support"]');
  const initialSupportCount = await page.evaluate(() => Urbanix.Store.query('supportRequests').length);
  await page.click('[data-support-form] button[type="submit"]');
  assert(await page.evaluate(count => Urbanix.Store.query('supportRequests').length === count, initialSupportCount), 'Atendimento invalido foi persistido.');
  assert(await page.isVisible('[data-support-error]'), 'Atendimento invalido nao exibiu orientacao.');
  await page.selectOption('#supportSubject', 'Obra');
  await page.fill('#supportMessage', 'Gostaria de uma atualizacao sobre a etapa de pavimentacao.');
  await page.click('[data-support-form] button[type="submit"]');
  const createdSupport = await page.evaluate(count => {
    const state = Urbanix.Store.getState();
    const item = state.supportRequests.at(-1);
    return {
      count: state.supportRequests.length,
      customerId: item.customerId,
      contractId: item.contractId,
      createdByUserId: item.createdByUserId,
      visible: Boolean(document.querySelector(`[data-support-ticket="${item.id}"]`))
    };
  }, initialSupportCount);
  assert(createdSupport.count === initialSupportCount + 1, 'Atendimento valido nao foi persistido.');
  assert(createdSupport.customerId === 'customer-2' && createdSupport.createdByUserId === 'user-client', 'Atendimento foi salvo com identidade incorreta.');
  assert(createdSupport.contractId === 'contract-1' && createdSupport.visible, 'Atendimento nao foi vinculado ao contrato ou exibido no historico.');
  await page.reload();
  assert(await page.locator('[data-support-ticket]').count() >= 2, 'Historico de atendimento nao permaneceu apos recarregar.');

  await page.click('[data-portal-tab="contract"]');
  await page.evaluate(() => { window.__portalPrinted = false; window.print = () => { window.__portalPrinted = true; }; });
  await page.click('[data-contract-print]');
  assert(await page.evaluate(() => window.__portalPrinted), 'Impressao demonstrativa do contrato nao foi acionada.');
  assert(await page.locator('[data-demo-toast]').count() === 0, 'O portal ainda contem acao generica sem fluxo proprio.');

  for (const viewport of [{ width: 375, height: 812 }, { width: 768, height: 900 }, { width: 1366, height: 768 }]) {
    await page.setViewportSize(viewport);
    await page.goto(`${urlFor('portal-cliente.html')}#support`);
    const audit = await page.evaluate(() => ({
      overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      unlabeled: Array.from(document.querySelectorAll('input, select, textarea')).filter(field => !field.labels?.length && !field.getAttribute('aria-label') && !field.getAttribute('aria-labelledby')).length,
      selectedTabs: document.querySelectorAll('[role="tab"][aria-selected="true"]').length,
      activeTabVisible: (() => { const nav = document.querySelector('.portal-nav').getBoundingClientRect(); const tab = document.querySelector('[role="tab"][aria-selected="true"]').getBoundingClientRect(); return { pass: tab.left >= nav.left - 1 && tab.right <= nav.right + 1, navLeft: nav.left, navRight: nav.right, tabLeft: tab.left, tabRight: tab.right, scrollLeft: document.querySelector('.portal-nav').scrollLeft }; })()
    }));
    assert(audit.overflow <= 1, `Portal possui overflow de ${audit.overflow}px em ${viewport.width}px.`);
    assert(audit.unlabeled === 0, `Portal possui ${audit.unlabeled} campos sem label em ${viewport.width}px.`);
    assert(audit.selectedTabs === 1, 'Navegacao por abas nao manteve um unico item selecionado.');
    assert(audit.activeTabVisible.pass, `Aba ativa ficou fora da area visivel em ${viewport.width}px: ${JSON.stringify(audit)}.`);
    if (viewport.width <= 768) {
      await page.click('[data-portal-tab="home"]');
      await page.evaluate(() => { document.querySelector('.portal-nav').scrollLeft = 0; });
      await page.click('[data-go-tab="support"]');
      await page.waitForTimeout(180);
      const shortcutAudit = await page.evaluate(() => {
        const nav = document.querySelector('.portal-nav').getBoundingClientRect();
        const tab = document.querySelector('[data-portal-tab="support"]').getBoundingClientRect();
        const panel = document.getElementById('portal-panel');
        const panelRect = panel.getBoundingClientRect();
        const styles = getComputedStyle(panel);
        return { focus: document.activeElement === panel, visible: tab.left >= nav.left - 1 && tab.right <= nav.right + 1, outline: styles.outlineStyle !== 'none' && parseFloat(styles.outlineWidth) >= 3, panelTop: panelRect.top, navBottom: nav.bottom, scrollY: window.scrollY, panelOffsetTop: panel.offsetTop, scrollHeight: document.documentElement.scrollHeight };
      });
      assert(shortcutAudit.focus, `Atalho nao transferiu foco util em ${viewport.width}px.`);
      assert(shortcutAudit.visible, `Atalho nao revelou a aba ativa em ${viewport.width}px.`);
      assert(shortcutAudit.outline, `Painel focado nao exibiu indicador visual em ${viewport.width}px.`);
      assert(shortcutAudit.panelTop >= shortcutAudit.navBottom + 8, `Atalho posicionou o painel sob a navegacao fixa em ${viewport.width}px: ${JSON.stringify(shortcutAudit)}.`);
    }
  }

  await page.evaluate(() => Urbanix.Store.update('users', 'user-client', { customerId: 'customer-1' }));
  await page.reload();
  assert((await page.textContent('.portal-empty')).includes('Nenhum contrato'), 'Cliente sem contrato recebeu fallback de outro cliente.');
  await page.evaluate(() => Urbanix.Store.update('users', 'user-client', { customerId: 'customer-2' }));

  const validation = await page.evaluate(() => {
    const base = Urbanix.Store.getState();
    const rejects = mutate => {
      const copy = JSON.parse(JSON.stringify(base));
      mutate(copy);
      try { Urbanix.Store.validateState(copy); return false; } catch { return true; }
    };
    return {
      client: rejects(state => { state.users.find(item => item.id === 'user-client').customerId = 'customer-orphan'; }),
      document: rejects(state => { state.documents[0].customerId = 'customer-3'; }),
      support: rejects(state => { state.supportRequests[0].contractId = 'contract-2'; })
    };
  });
  assert(validation.client && validation.document && validation.support, 'Store aceitou vinculos orfaos do portal.');

  await login(page, urlFor, 'admin@empresa.com');
  await page.waitForURL(/index\.html$/);
  await page.goto(`${urlFor('portal-cliente.html')}?previewCustomer=customer-2`);
  assert(await page.isVisible('[data-portal-customer-select]'), 'Administrador nao recebeu o seletor da previa.');
  assert(await page.isVisible('a[href="index.html"]'), 'Previa administrativa nao oferece retorno ao painel.');
  await page.selectOption('[data-portal-customer-select]', 'customer-3');
  await page.waitForURL(/previewCustomer=customer-3/);
  assert((await page.textContent('.portal-heading')).includes('Fernanda'), 'Previa administrativa nao alternou para o cliente selecionado.');
  const previewSupportCount = await page.evaluate(() => Urbanix.Store.query('supportRequests').length);
  await page.click('[data-portal-tab="support"]');
  assert(await page.isVisible('[data-portal-readonly]') && await page.isVisible('.portal-readonly-note'), 'Previa administrativa nao esta identificada como somente leitura.');
  assert(await page.locator('[data-support-form], [data-portal-theme]').count() === 0, 'Previa administrativa ainda oferece controles mutaveis do portal.');
  assert(await page.evaluate(count => Urbanix.Store.query('supportRequests').length === count, previewSupportCount), 'Previa administrativa alterou atendimentos.');

  assert(errors.length === 0, `Erros de pagina/console: ${errors.join(' | ')}`);
  console.log('OK portal smoke: login, RBAC demonstrativo, isolamento, 7 abas, financeiro, documentos, obra, atendimento, empty states e responsividade.');
})().catch(error => {
  console.error(error.stack || error);
  process.exitCode = 1;
}).finally(async () => {
  if (browser) await browser.close();
});
