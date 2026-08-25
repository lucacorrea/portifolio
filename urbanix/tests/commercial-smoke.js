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
  const urlFor = (file, query = '') => `${pathToFileURL(path.join(projectRoot, file)).href}${query}`;
  browser = await chromium.launch({
    headless: true,
    executablePath: process.env.URBANIX_BROWSER_EXECUTABLE || undefined
  });
  const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
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

  const seedRelations = await page.evaluate(() => {
    const data = Urbanix.Store.getState();
    const reservation = data.reservations.find(item => item.id === 'reservation-1');
    const proposal = data.proposals.find(item => item.id === reservation.proposalId);
    return {
      valid: Urbanix.Store.validateState(data),
      linked: proposal.unitId === reservation.unitId && proposal.customerId === reservation.customerId,
      reservedWithoutRecord: data.units.filter(unit => unit.status === 'reserved' && !data.reservations.some(item => item.id === unit.reservationId && item.status === 'active')).length
    };
  });
  assert(seedRelations.valid && seedRelations.linked && seedRelations.reservedWithoutRecord === 0, 'Seed comercial possui relações inconsistentes.');

  const unitId = await page.evaluate(() => {
    const data = Urbanix.Store.getState();
    return data.units.find(unit => unit.enterpriseId === 'ent-amazonas' && unit.status === 'available' && !data.proposals.some(proposal => proposal.unitId === unit.id && !['rejected', 'converted'].includes(proposal.status))).id;
  });
  await page.goto(urlFor('mapa-unidades.html'));
  await page.click(`[data-unit-id="${unitId}"]`);
  assert((await page.textContent('[data-lot-area]')).includes('m²'), 'Detalhe do mapa foi sobrescrito pelo handler legado.');
  await page.click('[data-unit-propose]');
  await page.waitForURL(/propostas\.html\?action=new&unitId=/);
  await page.waitForSelector('[data-proposal-form]');
  assert(await page.inputValue('#proposalUnit') === unitId, 'Deep-link do mapa não pré-selecionou a unidade.');

  await page.selectOption('#proposalCustomer', 'customer-6');
  await page.selectOption('#proposalBroker', 'broker-joao');
  await page.click('[data-next]');
  await page.fill('#proposalDiscount', '1200');
  await page.fill('#proposalDown', '10000');
  await page.fill('#proposalInstallments', '7');
  const validUntil = await page.evaluate(() => {
    const date = new Date();
    date.setDate(date.getDate() + 5);
    return date.toISOString().slice(0, 10);
  });
  await page.fill('#proposalValidity', validUntil);
  await page.click('[data-next]');
  assert((await page.textContent('[data-proposal-summary]')).includes('7x'), 'Resumo da proposta não atualizou as condições.');
  await page.click('[data-save]');
  await page.waitForFunction(id => !document.querySelector('[data-proposal-form]') && Urbanix.Store.getState().proposals.some(item => item.unitId === id && item.customerId === 'customer-6'), unitId);
  const proposalId = await page.evaluate(id => Urbanix.Store.getState().proposals.find(item => item.unitId === id && item.customerId === 'customer-6').id, unitId);

  await page.click(`[data-proposal-approve="${proposalId}"]`);
  assert(await page.evaluate(id => Urbanix.Store.find('proposals', id).status, proposalId) === 'approved', 'Proposta não foi aprovada.');
  await page.click(`[data-proposal-reserve="${proposalId}"]`);
  const reservationResult = await page.evaluate(id => {
    const data = Urbanix.Store.getState();
    const proposal = data.proposals.find(item => item.id === id);
    const reservation = data.reservations.find(item => item.proposalId === id && item.status === 'active');
    return { reservationId: reservation?.id, unitStatus: data.units.find(item => item.id === proposal.unitId)?.status, count: data.reservations.length };
  }, proposalId);
  assert(reservationResult.reservationId && reservationResult.unitStatus === 'reserved', 'Reserva não bloqueou a unidade atomicamente.');

  const duplicateReservation = await page.evaluate(id => {
    const data = Urbanix.Store.getState();
    const proposal = data.proposals.find(item => item.id === id);
    const same = Urbanix.Commercial.createReservation({ proposalId: id, customerId: proposal.customerId, unitId: proposal.unitId, brokerId: proposal.brokerId });
    let conflict = '';
    try { Urbanix.Commercial.createReservation({ customerId: 'customer-7', unitId: proposal.unitId, brokerId: 'broker-ana' }); } catch (error) { conflict = error.message; }
    return { sameId: same.id, conflict, count: Urbanix.Store.getState().reservations.length };
  }, proposalId);
  assert(duplicateReservation.sameId === reservationResult.reservationId && duplicateReservation.count === reservationResult.count, 'Reserva idempotente criou duplicidade.');
  assert(duplicateReservation.conflict.includes('não está disponível'), 'Dupla reserva para outro cliente não foi bloqueada.');

  await page.click(`[data-proposal-sale="${proposalId}"]`);
  await page.locator('.urbanix-dialog footer button').filter({ hasText: 'Concluir venda' }).click();
  await page.waitForFunction(id => Urbanix.Store.find('proposals', id).status === 'converted', proposalId);
  const saleResult = await page.evaluate(id => {
    const data = Urbanix.Store.getState();
    const proposal = data.proposals.find(item => item.id === id);
    const sale = data.sales.find(item => item.id === proposal.saleId);
    const contract = data.contracts.find(item => item.id === sale.contractId);
    const installments = data.installments.filter(item => item.contractId === contract.id);
    const before = { sales: data.sales.length, contracts: data.contracts.length, installments: data.installments.length };
    const repeated = Urbanix.Commercial.convertProposalToSale(id);
    const after = Urbanix.Store.getState();
    const reservation = after.reservations.find(item => item.id === sale.reservationId);
    Urbanix.Store.mutate('test.expired-converted-reservation', draft => {
      draft.reservations.find(item => item.id === reservation.id).expiresAt = '2000-01-01T00:00:00.000Z';
    });
    Urbanix.Commercial.expireReservations();
    const finalState = Urbanix.Store.getState();
    return {
      saleId: sale.id,
      saleNumber: sale.number,
      repeatedId: repeated.id,
      stable: before.sales === after.sales.length && before.contracts === after.contracts.length && before.installments === after.installments.length,
      status: finalState.units.find(item => item.id === proposal.unitId).status,
      reservationStatus: finalState.reservations.find(item => item.id === reservation.id).status,
      installmentCount: installments.length,
      expectedCount: contract.installmentCount,
      installmentTotal: Math.round(installments.reduce((sum, item) => sum + item.amount, 0) * 100),
      balance: Math.round((contract.totalAmount - contract.downPayment) * 100),
      receivables: data.accountsReceivable.filter(item => item.contractId === contract.id).length,
      valid: Urbanix.Store.validateState(finalState)
    };
  }, proposalId);
  assert(saleResult.saleId === saleResult.repeatedId && saleResult.stable, 'Conversão de venda/contrato não é idempotente.');
  assert(saleResult.status === 'sold' && saleResult.reservationStatus === 'converted', 'Expiração reabriu unidade vendida ou não encerrou a reserva.');
  assert(saleResult.installmentCount === saleResult.expectedCount && saleResult.receivables === saleResult.expectedCount, 'Contrato não gerou parcelas/recebíveis completos.');
  assert(saleResult.installmentTotal === saleResult.balance && saleResult.valid, 'Parcelas divergem do saldo ou estado final é inválido.');

  const reservationSale = await page.evaluate(() => {
    const data = Urbanix.Store.getState();
    const unit = data.units.find(item => item.status === 'available');
    const reservation = Urbanix.Commercial.createReservation({ customerId: 'customer-9', unitId: unit.id, brokerId: 'broker-joao', hours: 24 });
    const sale = Urbanix.Commercial.convertProposalToSale(null, reservation.id, { amount: unit.listPrice, downPayment: 9000, installmentCount: 3 });
    const repeated = Urbanix.Commercial.convertProposalToSale(null, reservation.id, { amount: unit.listPrice, downPayment: 9000, installmentCount: 3 });
    const finalState = Urbanix.Store.getState();
    return {
      saleId: sale.id,
      repeatedId: repeated.id,
      reservationId: sale.reservationId,
      reservationStatus: finalState.reservations.find(item => item.id === reservation.id).status,
      unitStatus: finalState.units.find(item => item.id === unit.id).status,
      contract: Boolean(finalState.contracts.find(item => item.id === sale.contractId))
    };
  });
  assert(reservationSale.saleId === reservationSale.repeatedId && reservationSale.reservationId && reservationSale.contract, 'Venda avulsa da reserva não gerou contrato idempotente.');
  assert(reservationSale.reservationStatus === 'converted' && reservationSale.unitStatus === 'sold', 'Venda avulsa não encerrou a reserva/unidade corretamente.');

  const expiredResult = await page.evaluate(() => {
    const data = Urbanix.Store.getState();
    const unit = data.units.find(item => item.status === 'available');
    const reservation = Urbanix.Commercial.createReservation({ customerId: 'customer-10', unitId: unit.id, brokerId: 'broker-ana', hours: 24 });
    Urbanix.Store.mutate('test.expired-active-reservation', draft => {
      draft.reservations.find(item => item.id === reservation.id).expiresAt = '2000-01-01T00:00:00.000Z';
    });
    Urbanix.Commercial.expireReservations();
    return { reservationStatus: Urbanix.Store.find('reservations', reservation.id).status, unitStatus: Urbanix.Store.find('units', unit.id).status };
  });
  assert(expiredResult.reservationStatus === 'expired' && expiredResult.unitStatus === 'available', 'Expiração não liberou a reserva ativa correspondente.');

  const releaseResult = await page.evaluate(() => {
    const data = Urbanix.Store.getState();
    const unit = data.units.find(item => item.status === 'available');
    const reservation = Urbanix.Commercial.createReservation({ customerId: 'customer-8', unitId: unit.id, brokerId: 'broker-ana', hours: 24 });
    const first = Urbanix.Commercial.releaseReservation(reservation.id);
    const second = Urbanix.Commercial.releaseReservation(reservation.id);
    return { same: first.id === second.id, status: Urbanix.Store.find('units', unit.id).status };
  });
  assert(releaseResult.same && releaseResult.status === 'available', 'Liberação de reserva não foi segura/idempotente.');

  await page.goto(urlFor('vendas.html'));
  await page.waitForSelector('.content .table-tools input');
  await page.fill('.content .table-tools input', saleResult.saleNumber);
  await page.click(`[data-view-sale="${saleResult.saleId}"]`);
  assert((await page.textContent('.urbanix-dialog')).includes('Linha do tempo'), 'Detalhe/timeline da venda não abriu.');
  await page.click('[data-dialog-close]');

  await page.goto(urlFor('crm.html'));
  await page.selectOption('[data-lead-stage="lead-13"]', 'qualification');
  assert(await page.evaluate(() => Urbanix.Store.find('leads', 'lead-13').stage) === 'qualification', 'Alternativa acessível do CRM não persistiu a etapa.');
  await page.click('[data-new-lead]');
  await page.fill('#leadName', 'Lead QA Comercial');
  await page.fill('#leadPhone', '97999998888');
  await page.fill('#leadEmail', 'lead.qa@exemplo.com');
  await page.selectOption('#leadEnterprise', 'ent-amazonas');
  await page.selectOption('#leadBroker', 'broker-joao');
  await page.fill('#leadValue', '99000');
  await page.click('[data-lead-form] button[type="submit"]');
  const leadConversion = await page.evaluate(() => {
    const lead = Urbanix.Store.getState().leads.find(item => item.email === 'lead.qa@exemplo.com');
    const before = Urbanix.Store.getState().customers.length;
    const first = Urbanix.Commercial.convertLead(lead.id);
    const second = Urbanix.Commercial.convertLead(lead.id);
    return { same: first.id === second.id, created: Urbanix.Store.getState().customers.length - before, customerId: first.id };
  });
  assert(leadConversion.same && leadConversion.created === 1, 'Conversão de lead duplicou o cliente.');

  await page.goto(urlFor('clientes.html'));
  await page.click('[data-new-customer]');
  await page.fill('#customerName', 'Cliente CRUD QA');
  await page.fill('#customerCpf', '98765432100');
  await page.fill('#customerPhone', '97988887777');
  await page.fill('#customerEmail', 'cliente.crud.qa@exemplo.com');
  await page.selectOption('#customerBroker', 'broker-ana');
  await page.click('[data-customer-form] button[type="submit"]');
  await page.waitForSelector('.content .table-tools input');
  await page.fill('.content .table-tools input', 'cliente.crud.qa@exemplo.com');
  const customerId = await page.evaluate(() => Urbanix.Store.getState().customers.find(item => item.email === 'cliente.crud.qa@exemplo.com').id);
  await page.click(`[data-view-customer="${customerId}"]`);
  assert((await page.textContent('.urbanix-dialog')).includes('Cliente CRUD QA'), 'Quick view do cliente não abriu.');
  await page.click('[data-dialog-close]');
  await page.click(`[data-edit-customer="${customerId}"]`);
  await page.fill('#customerName', 'Cliente CRUD Editado');
  await page.click('[data-customer-form] button[type="submit"]');
  assert(await page.evaluate(id => Urbanix.Store.find('customers', id).name, customerId) === 'Cliente CRUD Editado', 'Edição do cliente não persistiu.');
  await page.fill('.content .table-tools input', 'cliente.crud.qa@exemplo.com');
  await page.click(`[data-delete-customer="${customerId}"]`);
  await page.locator('.urbanix-dialog footer button').filter({ hasText: 'Excluir' }).click();
  assert(await page.evaluate(id => Urbanix.Store.find('customers', id), customerId) === null, 'Exclusão controlada do cliente sem vínculos falhou.');

  await page.setViewportSize({ width: 375, height: 812 });
  for (const file of ['empreendimentos.html', 'mapa-unidades.html', 'tabela-precos.html', 'crm.html', 'clientes.html', 'propostas.html', 'reservas.html', 'vendas.html']) {
    await page.goto(urlFor(file));
    const audit = await page.evaluate(() => ({ overflow: document.documentElement.scrollWidth - innerWidth, generic: document.querySelectorAll('.content [data-demo-toast]').length }));
    assert(audit.overflow <= 0, `${file} possui ${audit.overflow}px de overflow horizontal em 375px.`);
    assert(audit.generic === 0, `${file} manteve ação comercial ligada ao toast genérico.`);
  }
  assert(errors.length === 0, `Erros no navegador: ${errors.join(' | ')}`);

  await context.close();
  await browser.close();
  console.log('OK commercial smoke: mapa, proposta, reserva, venda/contrato, idempotência, CRM, clientes, relações e responsividade.');
})().catch(error => {
  console.error(error.stack || error.message);
  if (browser) browser.close().catch(() => {});
  process.exitCode = 1;
});
